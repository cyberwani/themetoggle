=== ThemeToggle ===
Tags: theme switcher, user theme, role, child theme, preview
Requires at least: 5.9
Tested up to: 7.3
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enable user-specific WordPress themes with role-based permissions and personalized theme switching.

== Description ==

ThemeToggle lets administrators decide which roles may switch themes and which installed themes each role may use. Eligible users then pick their own theme in their profile. The choice only affects that user's own logged-in requests; the globally active theme is never changed and stays the default for everyone else.

Features:

* Master switch, plus a separate switch for wp-admin requests.
* Role eligibility (who may switch) kept separate from role theme assignment (which themes).
* Any registered role, including roles added by WooCommerce, LMS or membership plugins.
* Users with several roles get the combined, de-duplicated list of permitted themes.
* Parent and child themes are both selectable; child themes load their parent template correctly.
* "Personal Theme Settings" on the profile screen, with a "Default Theme" option and a single-page preview before saving.
* Floating "Switch to Default Theme" button (position, label, colours, radius, size, icon), frontend and/or admin.
* "Selected Theme" column on Users → All Users that distinguishes the saved preference from the effective theme, sortable, with a bulk reset action.
* "Reset All User Theme Preferences" tool.

== Installation ==

1. Copy the `themetoggle` folder to `wp-content/plugins/`.
2. Activate ThemeToggle under Plugins.
3. Go to Appearance → ThemeToggle:
   * General Settings: leave "Enable Theme Switching" on; enable admin switching only if you need it (see below).
   * Role-Based Themes: tick the roles allowed to switch, then tick the themes each role may use.
   * Floating Button: adjust the reset button if wanted.
4. Eligible users choose a theme under Users → Profile → Personal Theme Settings.

== How it works (theme-loading lifecycle) ==

1. `plugins_loaded` (priority 1): the plugin builds its services and adds callbacks to the `template` and `stylesheet` filters (priority 99). pluggable.php is loaded at this point, so the current user can be determined; the theme has not been located yet.
2. After `setup_theme`, WordPress calls get_template()/get_stylesheet() (wp_templating_constants(), then it loads the theme's functions.php). The first call resolves the effective theme once for the request:
   master switch → request context → logged in → at least one eligible role → (valid preview) → saved preference exists, is installed and error-free (child themes need their parent) → permitted by an eligible role → not the site default.
3. If resolution succeeds, `stylesheet` returns the selected theme and `template` returns its template (the parent directory for a child theme). Otherwise both filters return the original values untouched. The `template`/`stylesheet` options are never written and switch_theme() is never called.
4. The result is cached for the rest of the request; the resolution path never lists all themes: it calls wp_get_theme() for the one saved stylesheet only (theme roots come from WordPress's cached `theme_roots` transient). For logged-out visitors the cost is one autoloaded option read and the normal auth-cookie check.

Priority 99 makes the user's explicit choice win over generic filters at the default priority. Contexts in which WordPress itself filters the theme (Customizer previews) are not out-prioritised but skipped entirely.

Request contexts:

* Frontend page views: switched for eligible users.
* wp-admin pages: switched only when "Enable Theme Switching in Admin Panel" is on, and never on Themes, Add Themes, Theme File Editor, Site Editor, Customizer, Widgets, Menus, Updates or this plugin's settings page (filter: `themetoggle_excluded_admin_pages`).
* AJAX, admin-post and REST requests: classified by the page that sent them (the Referer). From a frontend page → frontend rules; from wp-admin → admin rules (including the excluded pages); missing or foreign referer → never switched. Theme/widget/menu/Customizer AJAX actions are never switched (filter: `themetoggle_excluded_ajax_actions`).
* WP-CLI, cron, XML-RPC, installation, Customizer: never switched.

What admin switching really does: the selected theme's functions.php (and anything it registers: admin pages, meta boxes, editor features) is loaded instead of the default theme's, and the block editor uses the selected theme's theme.json, editor styles and templates. The WordPress admin interface itself is not restyled by a frontend theme.

== Data safety ==

Some core data is keyed by the raw `stylesheet` option or is site-wide. While a user-specific theme is applied to a request:

* Theme mods: reads come from the selected theme's own `theme_mods_{stylesheet}` row (if it has none yet, the default theme's mods are inherited read-only; filter `themetoggle_inherit_default_theme_mods`). Writes (set_theme_mod()) go to the selected theme's row, so the default theme's settings are never overwritten.
* Widgets: writes to the site-wide `sidebars_widgets` option are discarded, because retrieve_widgets() would otherwise remap the live widget layout to the selected theme's sidebars.
* Caching: responses get `Cache-Control: no-cache, ... private`, `Vary: Cookie` and `DONOTCACHEPAGE`, so page caches do not store a user-specific rendering.

== Stored data ==

* Option `tt_settings` (autoloaded): all settings.
* User meta `tt_user_theme`: the user's preferred theme stylesheet (directory name).

A saved preference is never treated as authorization. It is re-validated on every request. Invalid preferences (role removed, theme removed from the role, theme deleted or renamed, plugin disabled) are ignored and the default theme is used, but they are kept so they apply again if they become valid. They are only deleted by the user choosing "Default Theme", the floating button, the bulk action, "Reset All", or uninstalling the plugin.

Uninstalling (deleting the plugin) removes `tt_settings` and every `tt_user_theme` value. Deactivating removes nothing. User meta is removed by WordPress when a user is deleted.

== Hooks for developers ==

* `themetoggle_applied_theme` (filter) — WP_Theme|null to apply; return null to keep the default.
* `themetoggle_request_context` (filter) — 'frontend', 'admin' or 'none'.
* `themetoggle_excluded_admin_pages` (filter) — admin file names never switched.
* `themetoggle_excluded_ajax_actions` (filter) — AJAX actions never switched.
* `themetoggle_inherit_default_theme_mods` (filter) — bool.
* `themetoggle_loaded` (action) — receives the Plugin instance; `$plugin->get( 'resolver' )` etc.

== Known limitations ==

* Multisite: not supported or tested in 1.0.0. Settings are per site, but user meta is network-wide; the meta key is centralised in `Theme_Resolver::META_KEY` so a per-site key can be introduced later without restructuring.
* Classic themes keep their own settings (menus locations, logo, etc.) per theme. A theme that was never active has no settings of its own and inherits the default theme's settings read-only until it saves its own.
* While a user-specific theme is applied, widget layout changes are not saved (manage widgets from Appearance → Widgets, which always uses the default theme).
* remove_theme_mods() deletes the option directly and cannot be intercepted; core only calls it from the Customizer, which is never switched.
* The preview covers one page view; following a link returns to the saved theme.
* Requests with a missing Referer (some privacy extensions strip it) are not switched for AJAX/REST.

== Changelog ==

= 1.0.0 =
* Initial release.
