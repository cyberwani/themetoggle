#!/usr/bin/env bash
# End-to-end tests for ThemeToggle against a real WordPress over HTTP.
source "$(cd "$(dirname "$0")" && pwd)/lib.sh"

SETTINGS_BASE='{"enabled":true,"enable_admin":false,"allowed_roles":["editor","author","shop_manager","administrator"],"role_themes":{"administrator":["tt-child","twentytwentyfour"],"editor":["tt-child","twentytwentyfour"],"author":["twentytwentythree","twentytwentyfour"],"contributor":["twentytwentytwo"],"shop_manager":["twentytwentythree","tt-orphan"]},"show_switch_button":true,"show_switch_button_frontend":true,"show_switch_button_admin":true,"button_position":"bottom-right","button_label":"","button_bg_color":"#1d2327","button_text_color":"#ffffff","button_radius":4,"button_size":"medium","button_icon":"undo"}'
set_settings() { $WP option update tt_settings "$1" --format=json >/dev/null; }
patch_settings() { # jq-free patch via php
	$WP eval-file "$HERE/patch-settings.php" "$1" >/dev/null
}
setmeta() { if [ -z "$2" ]; then $WP user meta delete "$1" tt_user_theme >/dev/null 2>&1; else $WP user meta update "$1" tt_user_theme "$2" >/dev/null; fi; }

set_settings "$SETTINGS_BASE"
for u in admin:adminpass editor1:pass123 author1:pass123 sub1:pass123 multi1:pass123 shop1:pass123 contrib1:pass123; do login "${u%%:*}" "${u##*:}"; done
for u in admin editor1 author1 sub1 multi1 shop1 contrib1; do setmeta $u ""; done

echo "== Theme resolution (frontend)"
check "anonymous visitor gets default theme" "$(theme_of anon $BASE/)" "twentytwentyfive"
check "editor without preference gets default" "$(theme_of editor1 $BASE/)" "twentytwentyfive"
setmeta editor1 tt-child
HTML=$(get editor1 $BASE/)
check "editor with child theme: parent template assets load" "$(themes_in <<<"$HTML")" "twentytwentyone"
contains "editor with child theme: child functions.php loaded (stylesheet = child)" "$HTML" 'name="tt-functions" content="tt-child"'
check "anonymous still default while editor switched (per-user isolation)" "$(theme_of anon $BASE/)" "twentytwentyfive"
check "another logged-in user unaffected" "$(theme_of author1 $BASE/)" "twentytwentyfive"
lacks "URL params cannot force a theme" "$(get editor1 "$BASE/?theme=twentytwentythree&template=twentytwentythree&stylesheet=twentytwentythree" | themes_in)" "twentytwentythree"
check "anonymous cannot force theme via URL" "$(theme_of anon "$BASE/?theme=twentytwentythree&tt_preview=twentytwentythree")" "twentytwentyfive"
setmeta editor1 twentytwentyfour
check "editor: second permitted (parent/standalone) theme" "$(theme_of editor1 $BASE/)" "twentytwentyfour"
setmeta author1 twentytwentythree
check "author: own permitted theme" "$(theme_of author1 $BASE/)" "twentytwentythree"
setmeta author1 tt-child
check "author: theme only permitted for editors -> default" "$(theme_of author1 $BASE/)" "twentytwentyfive"
check "author: invalid preference preserved" "$(meta author1)" "tt-child"
setmeta sub1 twentytwentyfour
check "subscriber (not eligible) -> default" "$(theme_of sub1 $BASE/)" "twentytwentyfive"
setmeta contrib1 twentytwentytwo
check "contributor has themes assigned but role not allowed -> default" "$(theme_of contrib1 $BASE/)" "twentytwentyfive"
setmeta multi1 twentytwentythree
check "multi-role editor+author gets author-only theme (union)" "$(theme_of multi1 $BASE/)" "twentytwentythree"
setmeta shop1 twentytwentythree
check "custom role (shop_manager) supported" "$(theme_of shop1 $BASE/)" "twentytwentythree"
setmeta shop1 tt-orphan
check "child theme with missing parent -> default" "$(theme_of shop1 $BASE/)" "twentytwentyfive"
setmeta shop1 deleted-theme
check "deleted/renamed theme directory -> default" "$(theme_of shop1 $BASE/)" "twentytwentyfive"
setmeta shop1 "../../../etc"
check "path traversal preference -> default" "$(theme_of shop1 $BASE/)" "twentytwentyfive"
setmeta author1 twentytwentyfive
check "preference equal to site default -> default, no button" "$(get author1 $BASE/ | grep -c 'tt-floating__button')" "0"

echo "== Lifecycle: disable / re-enable / role changes"
setmeta editor1 tt-child
patch_settings '{"enabled":false}'
check "plugin switching disabled -> default" "$(theme_of editor1 $BASE/)" "twentytwentyfive"
check "disabled: preference preserved" "$(meta editor1)" "tt-child"
patch_settings '{"enabled":true}'
contains "re-enabled -> preference applies again" "$(get editor1 $BASE/)" 'tt-functions'
patch_settings '{"allowed_roles":[]}'
check "no roles allowed -> default" "$(theme_of editor1 $BASE/)" "twentytwentyfive"
patch_settings '{"allowed_roles":["editor","author","shop_manager","administrator"]}'
$WP user set-role editor1 subscriber >/dev/null
check "user loses eligible role -> default" "$(theme_of editor1 $BASE/)" "twentytwentyfive"
check "user loses role: preference preserved" "$(meta editor1)" "tt-child"
$WP user set-role editor1 editor >/dev/null
contains "user regains role -> preference valid again" "$(get editor1 $BASE/)" 'tt-functions'
patch_settings '{"role_themes":{"administrator":["tt-child","twentytwentyfour"],"editor":["twentytwentyfour"],"author":["twentytwentythree","twentytwentyfour"],"shop_manager":["twentytwentythree"]}}'
check "admin removes theme from role -> default" "$(theme_of editor1 $BASE/)" "twentytwentyfive"
set_settings "$SETTINGS_BASE"

echo "== Cache safety"
H=$(get editor1 $BASE/ -D - -o /dev/null)
contains "switched response is not publicly cacheable" "$H" "Cache-Control: .*no-cache"
contains "switched response varies on cookie" "$H" "Vary: Cookie"
H=$(get anon $BASE/ -D - -o /dev/null)
lacks "anonymous response untouched by plugin" "$H" "Vary: Cookie"

echo "== Floating button"
HTML=$(get editor1 $BASE/)
contains "button shown when custom theme applied" "$HTML" 'tt-floating__button'
contains "button uses default label" "$HTML" 'Switch to Default Theme'
contains "frontend.css enqueued only then" "$HTML" 'themetoggle/assets/css/frontend.css'
lacks "no button for anonymous" "$(get anon $BASE/)" 'tt-floating'
lacks "no frontend.css for anonymous" "$(get anon $BASE/)" 'themetoggle/assets/css/frontend.css'
patch_settings '{"show_switch_button_frontend":false}'
lacks "frontend visibility off hides button" "$(get editor1 $BASE/)" 'tt-floating'
patch_settings '{"show_switch_button_frontend":true,"button_label":"Back to normal","button_position":"top-left"}'
HTML=$(get editor1 $BASE/)
contains "custom label" "$HTML" 'Back to normal'
contains "position class" "$HTML" 'tt-floating--top-left'
set_settings "$SETTINGS_BASE"

NONCE=$(get editor1 $BASE/ | grep -A3 'value="tt_reset_theme"' | grep -o 'name="tt_nonce" value="[^"]*"' | sed 's/.*value="//; s/"//')
CODE=$(get editor1 "$BASE/wp-admin/admin-post.php" -o /dev/null -w "%{http_code}" -d action=tt_reset_theme -d tt_redirect=/?p=1)
check "reset without nonce rejected" "$CODE" "403"
check "reset without nonce: preference kept" "$(meta editor1)" "tt-child"
LOC=$(get editor1 "$BASE/wp-admin/admin-post.php" -o /dev/null -w "%{redirect_url}" -d action=tt_reset_theme -d "tt_nonce=$NONCE" --data-urlencode "tt_redirect=https://evil.example/phish")
lacks "open redirect blocked" "$LOC" "evil.example"
check "reset with nonce deletes preference" "$(meta editor1)" ""
check "after reset -> default theme" "$(theme_of editor1 $BASE/)" "twentytwentyfive"
setmeta editor1 tt-child
NONCE=$(get editor1 $BASE/ | grep -A3 'value="tt_reset_theme"' | grep -o 'name="tt_nonce" value="[^"]*"' | sed 's/.*value="//; s/"//')
LOC=$(get editor1 "$BASE/wp-admin/admin-post.php" -o /dev/null -w "%{redirect_url}" -d action=tt_reset_theme -d "tt_nonce=$NONCE" --data-urlencode "tt_redirect=/?p=1&x=2")
check "reset redirects back to same-site URL" "$LOC" "$BASE/?p=1&x=2"
check "anonymous reset does nothing (no nopriv handler)" "$(curl -s -o /dev/null -w '%{http_code}' -d action=tt_reset_theme $BASE/wp-admin/admin-post.php)" "400"

echo "== Profile selector"
setmeta editor1 tt-child
P=$(get editor1 "$BASE/wp-admin/profile.php")
contains "eligible user sees Personal Theme Settings" "$P" 'Personal Theme Settings'
contains "selector lists permitted theme" "$P" 'value="twentytwentyfour"'
lacks "selector hides theme not permitted for role" "$P" 'value="twentytwentythree"'
contains "current selection shown" "$P" 'TT Child of 2021'
lacks "subscriber sees no selector" "$(get sub1 "$BASE/wp-admin/profile.php")" 'Personal Theme Settings'
lacks "contributor (role not allowed) sees no selector" "$(get contrib1 "$BASE/wp-admin/profile.php")" 'Personal Theme Settings'

profile_post() { # user page(profile.php|user-edit.php?user_id=N) theme [user_id]
	local u=$1 page=$2 theme=$3
	local html; html=$(get "$u" "$BASE/wp-admin/$page")
	local wpn ttn uid email nick
	wpn=$(grep -o 'name="_wpnonce" value="[^"]*"' <<<"$html" | head -1 | sed 's/.*value="//; s/"//')
	ttn=$(grep -o 'name="tt_user_theme_nonce" value="[^"]*"' <<<"$html" | head -1 | sed 's/.*value="//; s/"//')
	uid=$(grep -o 'name="user_id"[^>]*value="[^"]*"' <<<"$html" | head -1 | sed 's/.*value="//; s/"//')
	email=$(grep -o 'name="email" id="email"[^>]*value="[^"]*"' <<<"$html" | sed 's/.*value="//; s/"//')
	nick=$(grep -o 'name="nickname" id="nickname" value="[^"]*"' <<<"$html" | sed 's/.*value="//; s/"//')
	get "$u" "$BASE/wp-admin/$page" -o /tmp/profile-resp.html -w "%{http_code} %{redirect_url}" \
		-d "_wpnonce=$wpn" -d "tt_user_theme_nonce=$ttn" -d "action=update" -d "user_id=$uid" \
		--data-urlencode "email=$email" --data-urlencode "nickname=$nick" -d "display_name=$nick" \
		--data-urlencode "tt_user_theme=$theme" ${4:+-d "$4"}
}
profile_post editor1 profile.php twentytwentythree >/dev/null
check "forged profile POST with unpermitted theme rejected" "$(meta editor1)" "tt-child"
contains "rejection reported to user" "$(cat /tmp/profile-resp.html)" 'not available for this account'
profile_post editor1 profile.php twentytwentyfour >/dev/null
check "profile save with permitted theme" "$(meta editor1)" "twentytwentyfour"
profile_post editor1 profile.php "" >/dev/null
check "Default Theme option deletes meta" "$(meta editor1)" ""
setmeta author1 tt-child
P=$(get author1 "$BASE/wp-admin/profile.php")
contains "stale preference warning shown" "$P" 'currently not available'
profile_post author1 profile.php "__tt_keep__" >/dev/null
check "saving profile with 'keep' preserves stale preference" "$(meta author1)" "tt-child"
AID=$($WP user get author1 --field=ID)
profile_post admin "user-edit.php?user_id=$AID" twentytwentythree >/dev/null
check "admin edits another user's theme" "$(meta author1)" "twentytwentythree"
profile_post admin "user-edit.php?user_id=$AID" tt-child >/dev/null
check "admin cannot assign theme not permitted for that user's roles" "$(meta author1)" "twentytwentythree"
contains "editor cannot open another user's profile" "$(get editor1 "$BASE/wp-admin/user-edit.php?user_id=$AID")" 'not allowed to edit this user'

echo "== Preview"
setmeta editor1 tt-child
PURL=$(get editor1 "$BASE/wp-admin/profile.php" | grep -o 'href="[^"]*tt_preview=twentytwentyfour[^"]*"' | head -1 | sed 's/href="//; s/"$//; s/&#038;/\&/g; s/&amp;/\&/g')
HTML=$(get editor1 "$PURL")
check "preview shows previewed theme" "$(themes_in <<<"$HTML")" "twentytwentyfour"
contains "preview bar rendered" "$HTML" 'Previewing'
check "preview does not change saved preference" "$(meta editor1)" "tt-child"
contains "next page view returns to saved theme" "$(get editor1 $BASE/)" 'tt-functions'
check "preview URL is useless for another user" "$(theme_of author1 "$PURL")" "twentytwentythree"
check "preview URL is useless for anonymous" "$(theme_of anon "$PURL")" "twentytwentyfive"
BAD=$(sed 's/tt_preview=twentytwentyfour/tt_preview=twentytwentythree/' <<<"$PURL")
lacks "preview nonce bound to theme" "$(get editor1 "$BAD" | themes_in)" "twentytwentythree"
SN=$(grep -A4 'value="tt_select_theme"' <<<"$HTML" | grep -o 'name="tt_nonce" value="[^"]*"' | sed 's/.*value="//; s/"//')
get editor1 "$BASE/wp-admin/admin-post.php" -o /dev/null -d action=tt_select_theme -d tt_theme=twentytwentythree -d "tt_nonce=$SN" -d tt_redirect=/
check "'Use this theme' cannot save an unpermitted theme" "$(meta editor1)" "tt-child"
get editor1 "$BASE/wp-admin/admin-post.php" -o /dev/null -d action=tt_select_theme -d tt_theme=twentytwentyfour -d "tt_nonce=$SN" -d tt_redirect=/
check "'Use this theme' saves previewed theme" "$(meta editor1)" "twentytwentyfour"

echo "== Admin context"
setmeta admin tt-child
setmeta editor1 tt-child
lacks "admin switching off: default theme in wp-admin" "$(get editor1 "$BASE/wp-admin/profile.php")" 'tt-functions-admin'
lacks "admin switching off: no admin button" "$(get editor1 "$BASE/wp-admin/profile.php")" 'tt-floating__button'
patch_settings '{"enable_admin":true}'
P=$(get editor1 "$BASE/wp-admin/profile.php")
contains "admin switching on: selected theme functions.php loaded in wp-admin" "$P" 'tt-functions-admin'
contains "admin switching on: admin floating button" "$P" 'tt-floating__button'
contains "dashboard switched" "$(get admin "$BASE/wp-admin/index.php")" 'tt-functions-admin'
for page in themes.php widgets.php nav-menus.php theme-install.php "themes.php?page=themetoggle" update-core.php site-editor.php; do
	lacks "excluded admin page keeps default theme: $page" "$(get admin "$BASE/wp-admin/$page")" 'tt-functions-admin'
done
patch_settings '{"show_switch_button_admin":false}'
lacks "admin visibility off hides admin button" "$(get editor1 "$BASE/wp-admin/profile.php")" 'tt-floating__button'
set_settings "$SETTINGS_BASE"

echo "== REST / AJAX context"
RN=$(get admin "$BASE/wp-admin/index.php" | grep -o 'createNonceMiddleware( "[^"]*"' | head -1 | sed 's/.*( "//; s/"//')
active_via_rest() { # referer
	get admin "$BASE/?rest_route=/wp/v2/themes&status=active&_wpnonce=$RN" ${1:+-e "$1"} | grep -o '"stylesheet":"[^"]*"' | head -1 | sed 's/.*:"//; s/"//'
}
check "REST from a frontend page follows frontend switching" "$(active_via_rest "$BASE/")" "tt-child"
check "REST from wp-admin with admin switching off -> default" "$(active_via_rest "$BASE/wp-admin/post.php?post=1&action=edit")" "twentytwentyfive"
check "REST without referer -> default (fail-safe)" "$(active_via_rest "")" "twentytwentyfive"
check "REST from foreign site -> default" "$(active_via_rest "https://evil.example/")" "twentytwentyfive"
patch_settings '{"enable_admin":true}'
check "REST from wp-admin with admin switching on -> switched" "$(active_via_rest "$BASE/wp-admin/post.php?post=1&action=edit")" "tt-child"
check "REST from Site Editor always default" "$(active_via_rest "$BASE/wp-admin/site-editor.php")" "twentytwentyfive"
set_settings "$SETTINGS_BASE"
check "Customizer preview request not overridden" "$(theme_of admin "$BASE/?customize_changeset_uuid=00000000-0000-4000-8000-000000000000")" "twentytwentyfive"

echo "== Site-wide data protection"
$WP eval 'set_theme_mod("tt_probe","global-value");' >/dev/null
$WP option delete theme_mods_tt-child >/dev/null 2>&1
get admin "$BASE/?tt_setmod=1" -o /dev/null
check "set_theme_mod in switched request does not touch default theme mods" "$($WP eval 'echo get_option("theme_mods_twentytwentyfive")["tt_probe"];')" "global-value"
check "set_theme_mod in switched request writes the selected theme's mods" "$($WP eval 'echo get_option("theme_mods_tt-child")["tt_probe"];')" "from-child"
BEFORE=$($WP option get sidebars_widgets --format=json)
get admin "$BASE/?rest_route=/wp/v2/sidebars&_wpnonce=$RN" -e "$BASE/" -o /dev/null
AFTER=$($WP option get sidebars_widgets --format=json)
check "REST sidebars call in switched request does not rewrite global widgets" "$AFTER" "$BEFORE"
check "global stylesheet option never changed" "$($WP option get stylesheet)" "twentytwentyfive"
check "global template option never changed" "$($WP option get template)" "twentytwentyfive"

echo "== Users list"
setmeta editor1 tt-child; setmeta author1 tt-child; setmeta sub1 ""
U=$(get admin "$BASE/wp-admin/users.php")
contains "Selected Theme column" "$U" 'Selected Theme'
contains "valid selection shows theme name" "$U" '<strong>TT Child of 2021</strong>'
contains "invalid selection flagged, not shown as active" "$U" 'Invalid Selection: TT Child of 2021'
contains "no preference shows Default Theme" "$U" 'Default Theme'
contains "bulk action offered" "$U" 'value="tt_reset_theme"'
TOTAL=$(grep -c 'name="users\[\]"' <<<"$U")
if [ "$TOTAL" -gt 0 ]; then ok "users list shows user rows ($TOTAL)"; else bad "users list shows user rows" "row pattern matched nothing"; fi
check "sorting by theme keeps users without preference" "$(get admin "$BASE/wp-admin/users.php?orderby=tt_theme&order=asc" | grep -c 'name="users\[\]"')" "$TOTAL"
EID=$($WP user get editor1 --field=ID)
check "bulk reset without nonce rejected" "$(get admin "$BASE/wp-admin/users.php?action=tt_reset_theme&users[]=$EID" -o /dev/null -w '%{http_code}' -e "$BASE/wp-admin/users.php")" "403"
check "bulk reset without nonce: preference kept" "$(meta editor1)" "tt-child"
BN=$(grep -o 'name="_wpnonce" value="[^"]*"' <<<"$U" | head -1 | sed 's/.*value="//; s/"//')
get admin "$BASE/wp-admin/users.php?action=tt_reset_theme&users[]=$EID&users[]=$AID&_wpnonce=$BN" -o /dev/null -e "$BASE/wp-admin/users.php"
check "bulk reset clears selected users" "$(meta editor1)$(meta author1)" ""

echo "== Settings page"
check "editor cannot open settings" "$(get editor1 "$BASE/wp-admin/themes.php?page=themetoggle" -o /dev/null -w '%{http_code}')" "403"
S=$(get admin "$BASE/wp-admin/themes.php?page=themetoggle&tab=roles")
contains "roles tab lists custom role" "$S" 'Shop Manager'
contains "roles tab lists child theme with parent info" "$S" 'Child of Twenty Twenty-One'
ON=$(grep -o 'name="_wpnonce" value="[^"]*"' <<<"$S" | head -1 | sed 's/.*value="//; s/"//')
patch_settings '{"button_label":"Keep me"}'
get admin "$BASE/wp-admin/options.php" -o /dev/null -e "$BASE/wp-admin/themes.php?page=themetoggle&tab=roles" \
	-d option_page=tt_settings_group -d action=update -d "_wpnonce=$ON" -d "tt_settings[_tab]=roles" \
	-d "tt_settings[allowed_roles][]=editor" -d "tt_settings[allowed_roles][]=fake_role" \
	-d "tt_settings[role_themes][editor][]=twentytwentyfour" -d "tt_settings[role_themes][editor][]=../evil" \
	-d "tt_settings[role_themes][fake_role][]=twentytwentyfour" -d "tt_settings[role_themes][editor][]=tt-orphan"
SAVED=$($WP option get tt_settings --format=json)
contains "settings: valid role saved" "$SAVED" '"allowed_roles":\["editor"\]'
lacks "settings: unknown role dropped" "$SAVED" 'fake_role'
lacks "settings: traversal theme dropped" "$SAVED" 'evil'
lacks "settings: broken theme dropped" "$SAVED" 'tt-orphan'
contains "settings: other tabs preserved" "$SAVED" '"button_label":"Keep me"'
for t in general roles users button; do
	check "settings tab renders: $t" "$(get admin "$BASE/wp-admin/themes.php?page=themetoggle&tab=$t" | grep -c 'nav-tab-active')" "1"
done
set_settings "$SETTINGS_BASE"

echo "== Reset all"
setmeta editor1 tt-child; setmeta author1 twentytwentythree
RS=$(get admin "$BASE/wp-admin/themes.php?page=themetoggle&tab=users")
contains "users tab shows preference count" "$RS" '[0-9]* users currently have a saved theme preference'
RAN=$(grep -A2 'value="tt_reset_all"' <<<"$RS" | grep -o 'name="tt_nonce" value="[^"]*"' | sed 's/.*value="//; s/"//')
check "reset all by non-admin rejected" "$(get editor1 "$BASE/wp-admin/admin-post.php" -o /dev/null -w '%{http_code}' -d action=tt_reset_all -d tt_confirm_reset=1 -d "tt_nonce=$RAN")" "403"
get admin "$BASE/wp-admin/admin-post.php" -o /dev/null -d action=tt_reset_all -d "tt_nonce=$RAN"
check "reset all without confirmation does nothing" "$(meta editor1)" "tt-child"
get admin "$BASE/wp-admin/admin-post.php" -o /dev/null -d action=tt_reset_all -d tt_confirm_reset=1 -d "tt_nonce=$RAN"
check "reset all with confirmation clears everyone" "$(meta editor1)$(meta author1)" ""

echo "== Security hardening (audit 2026-11-26)"
login admin adminpass; login editor1 pass123
# 1. Dev helpers in bin/ refuse to run over HTTP.
for script in build-zip.php check-pot.php check-version.php; do
	CODE=$(curl -s -o /tmp/tt-bin-body -w '%{http_code}' "$BASE/wp-content/plugins/themetoggle/bin/$script")
	check "bin/$script over HTTP is refused (403)" "$CODE" "403"
	check "bin/$script over HTTP prints nothing" "$(wc -c < /tmp/tt-bin-body | tr -d ' ')" "0"
done
# 2. Sorting by Selected Theme keeps another plugin's meta_query restriction.
$WP user meta update editor1 tt_e2e_visible 1 >/dev/null; $WP user meta update author1 tt_e2e_visible 1 >/dev/null
VISIBLE=$(get admin "$BASE/wp-admin/users.php?tt_e2e_hide_users=1" | grep -c 'name="users\[\]"')
check "fixture restriction lists only the 2 marked users" "$VISIBLE" "2"
check "sorting by Selected Theme keeps the restriction" "$(get admin "$BASE/wp-admin/users.php?tt_e2e_hide_users=1&orderby=tt_theme&order=asc" | grep -c 'name="users\[\]"')" "2"
check "sorting by Selected Theme without restriction still lists everyone" "$(get admin "$BASE/wp-admin/users.php?orderby=tt_theme&order=desc" | grep -c 'name="users\[\]"')" "$(get admin "$BASE/wp-admin/users.php" | grep -c 'name="users\[\]"')"
$WP user meta delete editor1 tt_e2e_visible >/dev/null; $WP user meta delete author1 tt_e2e_visible >/dev/null
# 3. A broken theme returned by the themetoggle_applied_theme filter is ignored.
setmeta editor1 tt-child
check "broken theme from filter falls back to default" "$(theme_of editor1 "$BASE/?tt_e2e_broken_theme=1")" "twentytwentyfive"
lacks "broken theme from filter: no floating button" "$(get editor1 "$BASE/?tt_e2e_broken_theme=1")" 'tt-floating__button'
contains "valid filter result still applies" "$(get editor1 "$BASE/")" 'tt-functions'
setmeta admin ""
lacks "filter cannot force a theme on an excluded screen (themes.php)" "$(get admin "$BASE/wp-admin/themes.php?tt_e2e_force_theme=tt-child")" 'tt-functions-admin'
lacks "filter cannot force a theme on an excluded screen (widgets.php)" "$(get admin "$BASE/wp-admin/widgets.php?tt_e2e_force_theme=tt-child")" 'tt-functions-admin'
check "global theme unchanged after forced/broken filter results" "$($WP option get stylesheet)" "twentytwentyfive"
get admin "$BASE/wp-admin/themes.php?tt_e2e_broken_theme=1" -o /dev/null
check "broken theme from filter on themes.php does not switch the site theme" "$($WP option get stylesheet)" "twentytwentyfive"
# 4. Button styles are re-validated on output, even if the stored option was tampered with.
patch_settings '{"button_bg_color":"red;background:url(https://evil.example/x)","button_text_color":"#fff\" onmouseover=\"x","button_position":"x onmouseover=alert(1)","button_size":"huge","button_radius":"999","button_icon":"<script>"}'
HTML=$(get editor1 $BASE/)
lacks "tampered colour: no injected CSS" "$HTML" 'evil.example'
lacks "tampered values: no injected attributes" "$HTML" 'onmouseover'
contains "tampered colour falls back to default" "$HTML" '--tt-bg:#1d2327;--tt-color:#ffffff;'
contains "tampered radius is clamped" "$HTML" '--tt-radius:50px;'
contains "tampered position/size fall back to defaults" "$HTML" 'tt-floating tt-floating--bottom-right tt-floating--medium'
set_settings "$SETTINGS_BASE"
setmeta editor1 ""

echo "== Logout / deletion"
setmeta editor1 tt-child
curl -s -o /dev/null -c $JAR/out -b $JAR/editor1 "$BASE/wp-login.php?action=logout&_wpnonce=$(get editor1 "$BASE/wp-admin/profile.php" | grep -o 'action=logout&#038;_wpnonce=[a-z0-9]*' | head -1 | sed 's/.*=//')"
check "after logout -> default" "$(curl -s -b $JAR/out $BASE/ | themes_in)" "twentytwentyfive"
TMP=$($WP user create tmpuser tmp@example.com --role=editor --porcelain); $WP user meta update $TMP tt_user_theme tt-child >/dev/null
$WP user delete $TMP --yes >/dev/null
check "user deletion removes preference" "$($WP eval "echo count( get_user_meta( $TMP ) );")" "0"

echo "== PHP notices"
PLUGIN_REAL=$(cd "$HERE/../.." && pwd -P)
LOG="$WP_PATH/wp-content/debug.log"
check "no PHP warnings/notices from the plugin in debug.log" "$( { grep -F -e "$PLUGIN_REAL/" -e "plugins/themetoggle/" "$LOG" 2>/dev/null || true; } | wc -l | tr -d ' ')" "0"

summary
