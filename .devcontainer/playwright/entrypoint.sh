#!/usr/bin/env bash
set -euo pipefail

CA_CRT="/certs/ca.crt"
CA_NAME="Bye Bye Passwords Local Dev CA"

if [ -f "$CA_CRT" ]; then
  # 1. Install into OS trust store (OpenSSL, curl, Node.js, Chrome Root Store fallback)
  cp "$CA_CRT" /usr/local/share/ca-certificates/byebyepw-local-ca.crt
  update-ca-certificates >/dev/null 2>&1

  # 2. Install into Chromium NSS database so Chromium trusts HTTPS WITHOUT --ignore-certificate-errors
  for home_dir in /root /home/pwuser; do
    if [ -d "$home_dir" ]; then
      nss_dir="$home_dir/.pki/nssdb"
      mkdir -p "$nss_dir"
      if [ ! -f "$nss_dir/cert9.db" ]; then
        certutil -d "sql:$nss_dir" -N --empty-password
      fi
      certutil -d "sql:$nss_dir" -D -n "$CA_NAME" >/dev/null 2>&1 || true
      certutil -d "sql:$nss_dir" -A -t "CT,C,C" -n "$CA_NAME" -i "$CA_CRT"
    fi
  done

  export NODE_EXTRA_CA_CERTS="$CA_CRT"
fi

exec "$@"
