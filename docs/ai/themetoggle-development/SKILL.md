---
name: themetoggle-development
description: Use for any change to the ThemeToggle WordPress plugin (per-user, role-based theme switching) — new features, bug fixes, settings, hooks, tests, releases or reviews. Gives the workflow, recipes and checks that keep theme resolution and site data safe.
---

# ThemeToggle development

ThemeToggle overrides the theme per request via the `template` / `stylesheet` filters for logged-in users, based on role permissions (`tt_settings`) and a per-user preference (`tt_user_theme`). Mistakes here can corrupt site-wide theme data or leak one user's theme to others, so follow the workflow even for small changes.

Read first: `AGENTS.md` (rules, conventions) and `docs/ARCHITECTURE.md` (lifecycle, invariants).

## Workflow

1. **Ground truth.** Read the files involved (see the map in `AGENTS.md`). Do not assume APIs, hooks or settings exist; check the code. For WordPress core behaviour, read core source when it matters (for example how `get_theme_mods()` picks its option).
2. **State the gap.** Write down CURRENT STATE, REQUIRED STATE and the GAP in one or two lines each. Treat the requester's proposed solution as a hypothesis; propose a safer or simpler one if it exists.
3. **Check the invariants** in `docs/ARCHITECTURE.md`. If the request breaks one, stop and explain; do not implement silently.
4. **Pick the recipe** below, or the closest one.
5. **Implement the smallest change** in the existing style (WPCS, constructor injection, `$view` arrays for templates).
6. **Test.** Add or update checks in `tests/e2e/http-tests.sh` (helpers in `tests/e2e/lib.sh`). Cover the success path, the rejection path (forged or unpermitted input) and one fallback case.
7. **Verify.** Run `composer check`, `npm run lint`, then the e2e suite on a disposable site. Regenerate the POT if strings changed (`composer make-pot`).
8. **Update docs** (`README.md`, `readme.txt`, `docs/*`) when settings, hooks, commands or behaviour change.
9. **Report**: changes, design decisions, tests added, exact check results, what was not verified, follow-ups and risks.

## Recipes

### Add a setting

1. `includes/class-settings.php`: add the key to `get_defaults()`, to the right `TAB_KEYS` entry, and a case in `sanitize_value()` (whitelist choices, clamp numbers, `sanitize_hex_color` for colours; a missing checkbox means `false`).
2. Render it in `admin/views/settings-<tab>.php` with `name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[key]"`.
3. Read it through `Settings::get()` (or a typed accessor such as `is_admin_enabled()`).
4. e2e: save the tab through `options.php` with a nonce, assert the stored value, and assert other tabs' values are unchanged.

### Add a settings tab

1. Add the slug and label to `Admin::get_tabs()` and the view file to the `$tt_views` map in `admin/views/settings-page.php`.
2. Add its keys to `Settings::TAB_KEYS` and post `tt_settings[_tab]=<slug>` from the form.
3. e2e: `settings tab renders: <slug>`.

### Change who gets which theme

1. Change `Theme_Resolver::compute_state()` (status per user) and/or `Role_Manager::get_permitted_themes()`.
2. Keep the status constants meaningful; `Users_List::describe_state()` must show the saved preference honestly (never show an invalid theme as active).
3. Keep `Theme_Resolver::save_preference()` validating against `get_selectable_themes()` for the **target** user.
4. e2e: frontend theme for each affected role, the users-list text, and a forged profile POST.

### Exclude a screen or request from switching

1. Admin page: add the file name to `Request_Context::is_excluded_admin_page()`. AJAX action: `is_excluded_ajax_action()`. REST/AJAX from that page are excluded automatically through the referer.
2. e2e: `lacks "excluded admin page keeps default theme: <page>" … 'tt-functions-admin'` with admin switching on.

### Add a public hook

1. Name it `themetoggle_<what>`, document it with a docblock at the `apply_filters()` / `do_action()` call.
2. Validate what comes back from a filter (type and allowed values) before using it.
3. Add it to the hooks tables in `README.md` and `readme.txt`.

### Add a state-changing action (button, form, bulk action)

1. Handler on `admin_post_<tt_action>` (no `nopriv` unless explicitly required).
2. `check_admin_referer()` with a specific action, `current_user_can()` with the narrowest capability, `wp_unslash` + sanitise input, `Redirect::get_target()` for the redirect, then `exit`.
3. e2e: request without nonce is rejected and changes nothing; with nonce it works; a foreign redirect target ends on the site.

### Protect more theme data

If some core data is keyed by `get_option( 'stylesheet' )` or is site-wide and written by theme code, add a `pre_option_*` / `pre_update_option_*` filter in `Data_Guard::activate()` and an e2e check that the default theme's copy is unchanged after a switched request.

### Release

Follow the "Release checklist" in `README.md`: bump the version in all four places (`composer version:check`), run every check, regenerate the POT, merge with CI green, then push a `vX.Y.Z` tag; `.github/workflows/release.yml` builds and publishes the zip. Never create the GitHub Release by hand.

## Pitfalls already found (do not rediscover)

- `wp_get_theme()` without an argument → `get_stylesheet()` → this plugin's filter → recursion.
- `get_theme_mods()` / `set_theme_mod()` use the raw `stylesheet` option, not the filter → handled by `Data_Guard`.
- `retrieve_widgets()` (widget screens and REST `/wp/v2/sidebars`, `/wp/v2/widgets`) rewrites `sidebars_widgets` for the loaded theme → blocked by `Data_Guard`.
- Site Editor REST calls would save templates against the user's theme → REST is classified by referer.
- WordPress runs a settings sanitize callback twice when the option does not exist yet → `Settings::sanitize()` handles payloads without `_tab`.
- Core does not nonce-check custom `handle_bulk_actions-users` actions.
- `user-edit.php` dies with HTTP 500 (not 403) for users who cannot edit the target.
- A plain `meta_key` orderby drops users without the meta → `Users_List::apply_sorting()` uses an `EXISTS OR NOT EXISTS` meta query.
- WPCS rejects `tt` as a prefix → hooks use `themetoggle_`, constants are namespaced.
- PHPCompatibilityWP 2.x does not detect PHP 8.0+ syntax → lint with a PHP 7.4 binary too.
- e2e: the test site's `home`/`siteurl` must equal `TT_BASE`, or login cookies get the wrong path. `wp core install --url=…` can still save a sub-folder URL, so `ci-install-wordpress.sh` sets both options explicitly and `setup.sh` refuses to run on a mismatch.
- e2e on GitHub: the checkout path contains `themetoggle`, so the debug-log check matches the plugin's real path, not the word.
