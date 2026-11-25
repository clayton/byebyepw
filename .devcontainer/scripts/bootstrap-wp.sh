#!/usr/bin/env bash
set -euo pipefail

WP_DIR="/var/www/html"
DB_HOST="${WORDPRESS_DB_HOST:-db}"
DB_NAME="${WORDPRESS_DB_NAME:-wordpress}"
DB_USER="${WORDPRESS_DB_USER:-wordpress}"
DB_PASS="${WORDPRESS_DB_PASSWORD:-wordpress}"

WP_VERSION="${WP_VERSION:-7.2-RC3}"
WP_SITE_URL="${WP_SITE_URL:-https://byebyepw.test:8443}"
WP_ADMIN_USER="${WP_ADMIN_USER:-admin}"
WP_ADMIN_PASS="${WP_ADMIN_PASS:-admin123!}"
WP_ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@byebyepw.test}"
WP_SUBSCRIBER_USER="${WP_SUBSCRIBER_USER:-subscriber}"
WP_SUBSCRIBER_PASS="${WP_SUBSCRIBER_PASS:-subscriber123!}"
WP_SUBSCRIBER_EMAIL="${WP_SUBSCRIBER_EMAIL:-subscriber@byebyepw.test}"

echo "[bootstrap] Waiting for MariaDB at $DB_HOST ..."
for i in $(seq 1 60); do
  if mysqladmin ping -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" --silent >/dev/null 2>&1; then
    break
  fi
  if [ "$i" -eq 60 ]; then
    echo "[bootstrap] ERROR: MariaDB did not become ready in time." >&2
    exit 1
  fi
  sleep 1
done

# Download WordPress core if not yet present
if [ ! -f "$WP_DIR/wp-includes/version.php" ]; then
  /usr/local/bin/switch-wp.sh "$WP_VERSION"
fi

# Create wp-config.php if missing
if [ ! -f "$WP_DIR/wp-config.php" ]; then
  echo "[bootstrap] Creating wp-config.php with WP_DEBUG, WP_DEBUG_LOG, SCRIPT_DEBUG, and HTTPS host handling ..."
  wp config create \
    --path="$WP_DIR" \
    --dbname="$DB_NAME" \
    --dbuser="$DB_USER" \
    --dbpass="$DB_PASS" \
    --dbhost="$DB_HOST" \
    --skip-check \
    --extra-php <<'PHP'
define( 'WP_ENVIRONMENT_TYPE', 'development' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', '0' );
define( 'SCRIPT_DEBUG', true );
define( 'FORCE_SSL_ADMIN', true );

// Allow trusted HTTPS access via either byebyepw.test or localhost without redirect loops,
// ensuring WebAuthn RP ID matches the exact browser hostname.
if ( isset( $_SERVER['HTTP_HOST'] ) && preg_match( '/^(localhost|byebyepw\.test)(:\d+)?$/', $_SERVER['HTTP_HOST'] ) ) {
	define( 'WP_HOME', 'https://' . $_SERVER['HTTP_HOST'] );
	define( 'WP_SITEURL', 'https://' . $_SERVER['HTTP_HOST'] );
}
PHP
  chown www-data:www-data "$WP_DIR/wp-config.php"
fi

# Install WordPress if not yet installed
if ! wp core is-installed --path="$WP_DIR" >/dev/null 2>&1; then
  echo "[bootstrap] Installing WordPress ($WP_SITE_URL) ..."
  wp core install \
    --path="$WP_DIR" \
    --url="$WP_SITE_URL" \
    --title="Bye Bye Passwords Dev" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASS" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email

  wp rewrite structure '/%postname%/' --hard --path="$WP_DIR"
fi

# Ensure subscriber test user exists (idempotent)
if ! wp user get "$WP_SUBSCRIBER_USER" --path="$WP_DIR" >/dev/null 2>&1; then
  echo "[bootstrap] Creating test subscriber user ($WP_SUBSCRIBER_USER) ..."
  wp user create "$WP_SUBSCRIBER_USER" "$WP_SUBSCRIBER_EMAIL" \
    --role=subscriber \
    --user_pass="$WP_SUBSCRIBER_PASS" \
    --display_name="Test Subscriber" \
    --path="$WP_DIR"
fi

# Install & activate WordPress Plugin Check (idempotent)
if ! wp plugin is-installed plugin-check --path="$WP_DIR" >/dev/null 2>&1; then
  echo "[bootstrap] Installing WordPress Plugin Check (plugin-check) ..."
  wp plugin install plugin-check --activate --path="$WP_DIR"
elif ! wp plugin is-active plugin-check --path="$WP_DIR" >/dev/null 2>&1; then
  wp plugin activate plugin-check --path="$WP_DIR"
fi

# Activate Bye Bye Passwords plugin (idempotent)
if ! wp plugin is-active bye-bye-passwords --path="$WP_DIR" >/dev/null 2>&1; then
  echo "[bootstrap] Activating bye-bye-passwords plugin ..."
  wp plugin activate bye-bye-passwords --path="$WP_DIR"
fi

# Ensure debug.log and uploads are writable by Apache (www-data)
touch "$WP_DIR/wp-content/debug.log"
chown www-data:www-data "$WP_DIR/wp-content/debug.log"
chmod 664 "$WP_DIR/wp-content/debug.log"
mkdir -p "$WP_DIR/wp-content/uploads"
chown -R www-data:www-data "$WP_DIR/wp-content/uploads"
if [ -d "$WP_DIR/wp-content/plugins/plugin-check" ]; then
  chown -R www-data:www-data "$WP_DIR/wp-content/plugins/plugin-check"
fi

echo "[bootstrap] Ready!"
echo "  PHP:        $(php -r 'echo PHP_VERSION;')"
echo "  WordPress:  $(wp core version --path="$WP_DIR")"
echo "  HTTPS URLs: https://byebyepw.test:${HTTPS_PORT:-8443}  |  https://localhost:${HTTPS_PORT:-8443}"
echo "  Admin:      $WP_ADMIN_USER / $WP_ADMIN_PASS"
echo "  Subscriber: $WP_SUBSCRIBER_USER / $WP_SUBSCRIBER_PASS"
