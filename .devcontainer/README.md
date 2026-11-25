# Bye Bye Passwords Dev Container (LAMP + Trusted HTTPS + Playwright)

Representative LAMP testing environment for **Bye Bye Passwords** across multiple PHP versions (**8.0, 8.2, 8.4, 8.6**) and multiple WordPress versions (**7.2 RC / nightly / 7.1.x stable**), with real trusted local HTTPS certificates for WebAuthn / Passkeys.

## What's Included

- **Apache 2** (`mod_ssl`, `mod_rewrite`, `mod_headers`) + **MariaDB 11** + selectable **PHP** (`8.0`, `8.2`, `8.4`, `8.6`, default `8.6`)
- **WordPress** selectable via `WP_VERSION` (`7.2-RC3` default, `rc`/`beta`, `nightly`, `latest`/`stable`, or any explicit version like `7.1.5`)
- **Trusted local CA + SAN leaf TLS certificate** (`byebyepw.test`, `*.byebyepw.test`, `localhost`, `127.0.0.1`, `::1`, 397-day leaf validity)
- **Pre-installed tooling**: WP-CLI, WordPress Plugin Check (`plugin-check`), `WP_DEBUG`, `WP_DEBUG_LOG`, `SCRIPT_DEBUG`
- **Plugin mount**: repo root mounted at `/var/www/html/wp-content/plugins/bye-bye-passwords`
- **Test accounts**:
  - Admin: `admin` / `admin123!` (`admin@byebyepw.test`)
  - Subscriber: `subscriber` / `subscriber123!` (`subscriber@byebyepw.test`)
- **Playwright + Chromium** (`playwright` service under `test` profile): trusts the local Root CA in both the OS store and Chromium's NSS DB (`~/.pki/nssdb`) so WebAuthn tests run **without** `--ignore-certificate-errors`.

## Quick Start

```bash
# Start LAMP (defaults: PHP 8.6 + WordPress 7.2-RC3 + trusted HTTPS on :8443)
.devcontainer/bin/dev up --build
```

## Switching PHP & WordPress Versions

```bash
# Switch PHP version (each version is cached as its own image tag)
.devcontainer/bin/dev switch-php 8.4
.devcontainer/bin/dev switch-php 8.2
.devcontainer/bin/dev switch-php 8.0
.devcontainer/bin/dev switch-php 8.6

# Switch WordPress core version (preserves DB & plugin mount)
.devcontainer/bin/dev switch-wp 7.1.5
.devcontainer/bin/dev switch-wp latest
.devcontainer/bin/dev switch-wp rc
.devcontainer/bin/dev switch-wp nightly
.devcontainer/bin/dev switch-wp 7.2-RC3
```

## Running WordPress Plugin Check & WP-CLI

```bash
# Run Plugin Check (excludes .devcontainer automatically)
.devcontainer/bin/dev plugin-check

# Run any WP-CLI command
.devcontainer/bin/dev wp plugin list
.devcontainer/bin/dev debug-log
```

## Running Automated Chromium WebAuthn Tests

```bash
# Runs Playwright with strict TLS verification (ignoreHTTPSErrors: false)
# against https://byebyepw.test:8443 and saves screenshots to .devcontainer/test-results/
.devcontainer/bin/dev test-e2e
```

## Trusting the Local CA on macOS Host

To open `https://localhost:8443` (or `https://byebyepw.test:8443`) in macOS Chromium/Chrome with a green/trusted padlock and working Passkeys:

```bash
ca=".devcontainer/certs/ca.crt"
sudo security add-trusted-cert \
  -d -r trustRoot \
  -k /Library/Keychains/System.keychain \
  "$ca"
```

Optional `/etc/hosts` entry if you want to browse `https://byebyepw.test:8443` on macOS (otherwise `https://localhost:8443` works immediately with zero `/etc/hosts` changes):

```bash
echo "127.0.0.1 byebyepw.test" \
  | sudo tee -a /etc/hosts
```

## Cleanup / Reset

```bash
# Stop containers (keeps DB & WP volumes)
.devcontainer/bin/dev down

# Full wipe (deletes DB & WP volumes)
.devcontainer/bin/dev down -v
```
