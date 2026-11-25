#!/usr/bin/env bash
set -euo pipefail

FORCE=0
CERT_DIR=""

for arg in "$@"; do
  case "$arg" in
    --force) FORCE=1 ;;
    *) CERT_DIR="$arg" ;;
  esac
done

if [ -z "$CERT_DIR" ]; then
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  if [ -d "$SCRIPT_DIR/.." ] && [ -f "$SCRIPT_DIR/../docker-compose.yml" ]; then
    CERT_DIR="$SCRIPT_DIR/../certs"
  else
    CERT_DIR="/etc/apache2/ssl"
  fi
fi

mkdir -p "$CERT_DIR"

CA_KEY="$CERT_DIR/ca.key"
CA_CRT="$CERT_DIR/ca.crt"
SRV_KEY="$CERT_DIR/server.key"
SRV_CSR="$CERT_DIR/server.csr"
SRV_CRT="$CERT_DIR/server.crt"
CA_CONF="$CERT_DIR/.ca.cnf"
SRV_CONF="$CERT_DIR/.server.cnf"

# Skip if existing CA + leaf cert are already valid for >= 30 days and match
if [ "$FORCE" -eq 0 ] \
  && [ -f "$CA_KEY" ] && [ -f "$CA_CRT" ] \
  && [ -f "$SRV_KEY" ] && [ -f "$SRV_CRT" ] \
  && openssl x509 -checkend 2592000 -noout -in "$SRV_CRT" >/dev/null 2>&1 \
  && openssl verify -CAfile "$CA_CRT" "$SRV_CRT" >/dev/null 2>&1; then
  echo "[certs] Existing trusted dev certificates in $CERT_DIR are valid."
  exit 0
fi

echo "[certs] Generating local Dev Root CA and SAN TLS certificate in $CERT_DIR ..."

cat > "$CA_CONF" <<'EOF'
[req]
default_bits = 2048
prompt = no
default_md = sha256
distinguished_name = dn
x509_extensions = v3_ca

[dn]
C = US
O = Bye Bye Passwords Dev
CN = Bye Bye Passwords Local Dev CA

[v3_ca]
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid:always,issuer
basicConstraints = critical, CA:true
keyUsage = critical, digitalSignature, cRLSign, keyCertSign
EOF

# Preserve existing CA if present and not forced (prevents re-trusting in macOS Keychain)
if [ "$FORCE" -eq 1 ] || [ ! -f "$CA_KEY" ] || [ ! -f "$CA_CRT" ]; then
  openssl genrsa -out "$CA_KEY" 2048 2>/dev/null
  openssl req -x509 -new -nodes -key "$CA_KEY" -sha256 -days 3650 \
    -out "$CA_CRT" -config "$CA_CONF"
  chmod 600 "$CA_KEY"
  chmod 644 "$CA_CRT"
fi

cat > "$SRV_CONF" <<'EOF'
[req]
default_bits = 2048
prompt = no
default_md = sha256
distinguished_name = dn
req_extensions = v3_req

[dn]
C = US
O = Bye Bye Passwords Dev
CN = byebyepw.test

[v3_req]
basicConstraints = critical, CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectKeyIdentifier = hash
subjectAltName = @alt_names

[v3_ext]
basicConstraints = critical, CA:FALSE
keyUsage = critical, digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectKeyIdentifier = hash
authorityKeyIdentifier = keyid,issuer
subjectAltName = @alt_names

[alt_names]
DNS.1 = byebyepw.test
DNS.2 = *.byebyepw.test
DNS.3 = localhost
IP.1 = 127.0.0.1
IP.2 = ::1
EOF

# Leaf cert valid 397 days (satisfies Apple <=825d and Chrome <=398d limits)
openssl genrsa -out "$SRV_KEY" 2048 2>/dev/null
openssl req -new -key "$SRV_KEY" -out "$SRV_CSR" -config "$SRV_CONF"
openssl x509 -req -in "$SRV_CSR" -CA "$CA_CRT" -CAkey "$CA_KEY" -CAcreateserial \
  -out "$SRV_CRT" -days 397 -sha256 -extfile "$SRV_CONF" -extensions v3_ext 2>/dev/null

rm -f "$SRV_CSR" "$CA_CONF" "$SRV_CONF" "$CERT_DIR/ca.srl"
chmod 600 "$SRV_KEY"
chmod 644 "$SRV_CRT"

openssl verify -CAfile "$CA_CRT" "$SRV_CRT"
echo "[certs] Generated CA: $CA_CRT and leaf cert: $SRV_CRT"
