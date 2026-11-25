#!/usr/bin/env bash
set -euo pipefail

# Ensure TLS certificates exist in /etc/apache2/ssl
/usr/local/bin/generate-certs.sh /etc/apache2/ssl

# Trust the local Dev Root CA inside the container (for curl, PHP OpenSSL, WP-CLI, loopback requests)
if [ -f /etc/apache2/ssl/ca.crt ]; then
  cp /etc/apache2/ssl/ca.crt /usr/local/share/ca-certificates/byebyepw-local-ca.crt
  update-ca-certificates >/dev/null 2>&1 || true
fi

# Bootstrap WordPress idempotently on startup unless skipped
if [ "${SKIP_WP_BOOTSTRAP:-0}" != "1" ]; then
  /usr/local/bin/bootstrap-wp.sh
fi

exec "$@"
