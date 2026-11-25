#!/usr/bin/env bash
set -euo pipefail

TARGET="${1:-${WP_VERSION:-7.2-RC3}}"
WP_DIR="/var/www/html"
PLUGIN_DIR="$WP_DIR/wp-content/plugins/bye-bye-passwords"

echo "[wp-switch] Switching WordPress core to: $TARGET"

RESOLVED_VERSION="$TARGET"
DOWNLOAD_URL=""

case "$TARGET" in
  rc|beta|7.2-rc|7.2-beta)
    API_JSON="$(curl -fsSL "https://api.wordpress.org/core/version-check/1.7/?channel=beta")"
    RESOLVED_VERSION="$(printf '%s' "$API_JSON" | jq -r '.offers[] | select(.response=="development" or (.current | test("RC|beta"; "i"))) | .current' | head -n1)"
    DOWNLOAD_URL="$(printf '%s' "$API_JSON" | jq -r '.offers[] | select(.response=="development" or (.current | test("RC|beta"; "i"))) | .download' | head -n1)"
    if [ -z "$RESOLVED_VERSION" ] || [ "$RESOLVED_VERSION" = "null" ]; then
      echo "[wp-switch] ERROR: Could not resolve latest beta/RC release from WordPress API." >&2
      exit 1
    fi
    echo "[wp-switch] Resolved beta/RC channel to $RESOLVED_VERSION ($DOWNLOAD_URL)"
    ;;
  stable|latest)
    RESOLVED_VERSION="latest"
    ;;
  nightly)
    RESOLVED_VERSION="nightly"
    ;;
  *RC*|*rc*|*beta*|*alpha*)
    DOWNLOAD_URL="https://downloads.wordpress.org/release/wordpress-${TARGET}.zip"
    ;;
esac

install_from_zip() {
  local url="$1"
  local tmp_dir
  tmp_dir="$(mktemp -d)"
  trap 'rm -rf "$tmp_dir"' RETURN

  echo "[wp-switch] Downloading $url ..."
  curl -fsSL "$url" -o "$tmp_dir/wordpress.zip"
  unzip -q "$tmp_dir/wordpress.zip" -d "$tmp_dir"

  # Replace WordPress core directories/files without touching wp-config.php or wp-content/plugins/bye-bye-passwords
  rm -rf "$WP_DIR/wp-admin" "$WP_DIR/wp-includes"
  cp -a "$tmp_dir/wordpress/wp-admin" "$WP_DIR/wp-admin"
  cp -a "$tmp_dir/wordpress/wp-includes" "$WP_DIR/wp-includes"

  for f in "$tmp_dir/wordpress"/*; do
    base="$(basename "$f")"
    case "$base" in
      wp-admin|wp-includes|wp-content|wp-config-sample.php) continue ;;
      *) cp -a "$f" "$WP_DIR/$base" ;;
    esac
  done
  cp -a "$tmp_dir/wordpress/wp-config-sample.php" "$WP_DIR/wp-config-sample.php"

  # Ensure wp-content & default themes exist on first install (never overwrite mounted plugin)
  mkdir -p "$WP_DIR/wp-content/themes" "$WP_DIR/wp-content/plugins" "$WP_DIR/wp-content/uploads"
  if [ -d "$tmp_dir/wordpress/wp-content/themes" ]; then
    for t in "$tmp_dir/wordpress/wp-content/themes"/*; do
      tbase="$(basename "$t")"
      if [ ! -e "$WP_DIR/wp-content/themes/$tbase" ]; then
        cp -a "$t" "$WP_DIR/wp-content/themes/$tbase"
      fi
    done
  fi
  if [ ! -f "$WP_DIR/wp-content/index.php" ] && [ -f "$tmp_dir/wordpress/wp-content/index.php" ]; then
    cp -a "$tmp_dir/wordpress/wp-content/index.php" "$WP_DIR/wp-content/index.php"
  fi
}

SKIP_CONTENT_FLAG=()
if [ -d "$WP_DIR/wp-content/themes" ] && [ "$(ls -A "$WP_DIR/wp-content/themes" 2>/dev/null || true)" != "" ]; then
  SKIP_CONTENT_FLAG=(--skip-content)
fi

if [ -n "$DOWNLOAD_URL" ]; then
  install_from_zip "$DOWNLOAD_URL"
else
  if ! wp core download --path="$WP_DIR" --version="$RESOLVED_VERSION" --force "${SKIP_CONTENT_FLAG[@]}"; then
    echo "[wp-switch] wp core download failed for '$RESOLVED_VERSION'; falling back to direct zip..."
    install_from_zip "https://downloads.wordpress.org/release/wordpress-${RESOLVED_VERSION}.zip"
  fi
fi

# Ensure www-data owns WordPress core & wp-content (excluding bind-mounted plugin directory)
find "$WP_DIR" -maxdepth 1 -mindepth 1 -not -name wp-content -exec chown -R www-data:www-data {} +
chown www-data:www-data "$WP_DIR" "$WP_DIR/wp-content"
mkdir -p "$WP_DIR/wp-content/uploads" "$WP_DIR/wp-content/upgrade"
chown -R www-data:www-data "$WP_DIR/wp-content/uploads" "$WP_DIR/wp-content/upgrade" "$WP_DIR/wp-content/themes"

if [ -f "$WP_DIR/wp-config.php" ] && wp core is-installed --path="$WP_DIR" >/dev/null 2>&1; then
  wp core update-db --path="$WP_DIR" || true
  wp cache flush --path="$WP_DIR" || true
fi

INSTALLED_VER="$(wp core version --path="$WP_DIR")"
echo "[wp-switch] Active WordPress core version: $INSTALLED_VER"
