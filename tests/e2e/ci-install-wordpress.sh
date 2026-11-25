#!/usr/bin/env bash
# Installs a throwaway WordPress site for the e2e suite (used by CI; works locally too).
#
# Required: TT_WP_PATH, TT_BASE, DB_NAME, DB_USER, DB_PASSWORD, DB_HOST
# Optional: WP_VERSION (default latest), TT_WP_CLI (default "wp")
#
# WARNING: creates and overwrites a WordPress install at TT_WP_PATH. Never point it at a real site.
set -euo pipefail

HERE=$(cd "$(dirname "$0")" && pwd)
PLUGIN_DIR=$(cd "$HERE/../.." && pwd)
WP="${TT_WP_CLI:-wp} --path=${TT_WP_PATH:?Set TT_WP_PATH}"
: "${TT_BASE:?Set TT_BASE}" "${DB_NAME:?}" "${DB_USER:?}" "${DB_PASSWORD?}" "${DB_HOST:?}"

mkdir -p "$TT_WP_PATH"
$WP core download --version="${WP_VERSION:-latest}" --force
$WP config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASSWORD" --dbhost="$DB_HOST" --skip-check --force
$WP config set WP_DEBUG true --raw
$WP config set WP_DEBUG_LOG true --raw
$WP config set WP_DEBUG_DISPLAY false --raw
$WP core install --url="$TT_BASE" --title="ThemeToggle E2E" \
	--admin_user=admin --admin_password=adminpass --admin_email=admin@example.com --skip-email

# `core install` can derive a sub-folder URL from the install path; the suite's login
# cookies only work when home and siteurl are exactly TT_BASE.
$WP option update home "$TT_BASE"
$WP option update siteurl "$TT_BASE"

# Themes the suite and its fixtures rely on (tt-child is a child of Twenty Twenty-One).
for theme in twentytwentyone twentytwentytwo twentytwentythree twentytwentyfour twentytwentyfive; do
	$WP theme is-installed "$theme" || $WP theme install "$theme"
done

# No outbound HTTP from here on: faster, deterministic runs.
$WP config set WP_HTTP_BLOCK_EXTERNAL true --raw
$WP config set AUTOMATIC_UPDATER_DISABLED true --raw

ln -sfn "$PLUGIN_DIR" "$TT_WP_PATH/wp-content/plugins/themetoggle"
echo "WordPress $($WP core version) installed at $TT_WP_PATH"
