#!/usr/bin/env bash
# Prepares a disposable WordPress site for the ThemeToggle e2e suite.
# Requires: TT_WP_PATH, optional TT_WP_CLI (default "wp"), TT_BASE.
# WARNING: creates users/roles and changes the active theme. Never run on a real site.
set -euo pipefail
HERE=$(cd "$(dirname "$0")" && pwd)
WP="${TT_WP_CLI:-wp} --path=${TT_WP_PATH:?Set TT_WP_PATH}"
for opt in home siteurl; do
	value=$($WP option get "$opt")
	[ "$value" = "${TT_BASE:?Set TT_BASE}" ] || { echo "Option $opt is '$value' but TT_BASE is '$TT_BASE'; fix with: wp option update $opt $TT_BASE" >&2; exit 1; }
done
for theme in twentytwentyone twentytwentytwo twentytwentythree twentytwentyfour twentytwentyfive; do
	$WP theme is-installed "$theme" || { echo "Missing theme $theme (run ci-install-wordpress.sh or wp theme install $theme)" >&2; exit 1; }
done
cp -r "$HERE/fixtures/tt-child" "$HERE/fixtures/tt-orphan" "$TT_WP_PATH/wp-content/themes/"
$WP theme activate twentytwentyfive
$WP plugin activate themetoggle
$WP role create shop_manager "Shop Manager" || true
for u in editor1:editor author1:author contrib1:contributor sub1:subscriber multi1:editor shop1:shop_manager; do
	$WP user create "${u%%:*}" "${u%%:*}@example.com" --role="${u##*:}" --user_pass=pass123 || true
done
$WP user add-role multi1 author
$WP user update admin --user_pass=adminpass
