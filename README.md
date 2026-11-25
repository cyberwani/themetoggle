# ThemeToggle

Per-user WordPress themes with role-based permissions. Administrators choose which roles may switch themes and which installed themes each role may use; eligible users pick their own theme in their profile. The globally active theme is never changed: it stays the default for visitors and for everyone without a valid personal choice.

- **Version:** 1.0.0
- **Requires:** WordPress 5.9+, PHP 7.4+
- **License:** GPL-2.0-or-later
- **Text domain:** `themetoggle`
- **Namespace:** `ThemeToggle`

`readme.txt` is the WordPress.org-format readme for site administrators. This file is for developers.

## Features

- Master switch, plus a separate switch for wp-admin requests.
- Role eligibility (who may switch) kept separate from role theme assignment (which themes).
- Any registered role, including roles added by WooCommerce, LMS or membership plugins.
- Users with several roles get the combined, de-duplicated list of permitted themes.
- Parent and child themes are both selectable; a child theme loads its parent template.
- "Personal Theme Settings" on the profile screen, with a "Default Theme" option and a one-page preview before saving.
- Floating "Switch to Default Theme" button (position, label, colours, radius, size, icon), on the frontend and/or in wp-admin.
- "Selected Theme" column in Users → All Users that separates the saved preference from the effective theme, sortable, with a bulk reset.
- "Reset All User Theme Preferences" tool.

## Installation

### On a site

1. Copy the plugin to `wp-content/plugins/themetoggle/` (or upload the release zip in Plugins → Add New → Upload).
2. Activate **ThemeToggle**.
3. Open **Appearance → ThemeToggle**:
   - **General Settings** — keep "Enable Theme Switching" on. Turn on admin switching only if needed (see [Admin switching](#admin-switching)).
   - **Role-Based Themes** — tick the roles allowed to switch, then the themes each role may use.
   - **Floating Button** — adjust the reset button.
4. Eligible users choose a theme in **Users → Profile → Personal Theme Settings**.

The plugin has no runtime dependencies: nothing needs `composer install` or `npm install` to run it.

### For development

Requirements: PHP 7.4+, [Composer](https://getcomposer.org/) 2, Node.js 22.22.2+ (see `.nvmrc`) with npm 10+, Git, Bash (Git Bash on Windows).

```bash
git clone <repository-url> themetoggle
cd themetoggle
composer install
npm install
```

Clone it straight into `wp-content/plugins/` of a local site (for example WAMP's `www/<site>/wp-content/plugins/`) to work on it live.

## Commands

Every check can be run through Composer or npm; npm scripts that touch PHP delegate to Composer.

| Task | Composer | npm |
| --- | --- | --- |
| PHP syntax check | `composer lint` | — |
| PHPCS: WordPress standards + PHP 7.4 compatibility | `composer phpcs` | `npm run lint:php` (runs `composer check`) |
| Auto-fix PHP style | `composer phpcbf` | `npm run fix:php` |
| All PHP checks | `composer check` | `npm run lint:php` |
| JavaScript lint (WordPress ESLint config) | — | `npm run lint:js` / `npm run fix:js` |
| CSS lint (WordPress Stylelint config) | — | `npm run lint:css` / `npm run fix:css` |
| Format JavaScript (Prettier) | — | `npm run format` |
| Markdown lint | — | `npm run lint:md` |
| Everything (JS + CSS + PHP) | — | `npm run lint` / `npm run fix` |
| Regenerate `languages/themetoggle.pot` | `composer make-pot` | `npm run makepot` |
| Check the POT has every source string | `composer pot:check` | — |
| Check all version numbers agree | `composer version:check` (optionally `-- 1.0.1`) | — |
| Release zip | `composer zip` (from the last git commit) | `npm run plugin-zip` (from the working tree) |
| Full build | `composer build` (checks + POT) | `npm run build` (lint + POT + zip) |
| End-to-end tests | `composer test:e2e` | `npm run test:e2e` |
| Update `@wordpress/scripts` | — | `npm run packages-update` |

There is no compile step. `assets/js/admin.js` and the two CSS files are small, hand-written and shipped as they are, so there is no bundler or minifier. Add `wp-scripts build` with a `src/` folder only if the assets grow enough to need it.

### What goes into the release zip

Only the runtime files: `themetoggle.php`, `uninstall.php`, `readme.txt`, `admin/`, `assets/`, `includes/`, `languages/`. Three lists define this and must stay in sync:

- `files` in `package.json` (used by `npm run plugin-zip`; npm always adds `README.md` and `package.json` as well, which is harmless — use `composer zip` for an exact runtime-only zip)
- `export-ignore` entries in `.gitattributes` (used by `composer zip`)
- `.distignore` (used by `wp dist-archive .` if you use WP-CLI's dist-archive command)

## Project structure

```text
themetoggle/
├── themetoggle.php               Plugin header, namespaced constants, autoloader, bootstrap on plugins_loaded:1
├── uninstall.php                 Deletes tt_settings and all tt_user_theme meta (plugin deletion only)
├── readme.txt                    WordPress.org readme (site administrators)
├── includes/
│   ├── class-autoloader.php      ThemeToggle\Foo_Bar → includes/class-foo-bar.php; ThemeToggle\Admin\* → admin/
│   ├── class-plugin.php          Composition root: builds services, registers hooks once
│   ├── class-installer.php       Activation: seeds default settings
│   ├── class-settings.php        tt_settings option: defaults, per-tab sanitising
│   ├── class-theme-manager.php   Cached Theme API wrapper (installed / usable themes)
│   ├── class-role-manager.php    Role eligibility and role → themes union
│   ├── class-theme-resolver.php  Saved preference → status + effective theme, per user
│   ├── class-request-context.php frontend / admin / none classification (incl. AJAX, REST, admin-post)
│   ├── class-preview.php         Nonce-bound one-page preview + "Use this theme"
│   ├── class-theme-switcher.php  template / stylesheet filters, per-request cache, no-cache headers
│   ├── class-data-guard.php      Isolates theme mods, blocks sidebars_widgets writes while switched
│   ├── class-user-profile.php    Profile "Personal Theme Settings" render + save
│   ├── class-floating-button.php Floating reset button and preview bar
│   ├── class-users-list.php      Users column, sorting, bulk reset
│   └── class-redirect.php        Safe redirect target for POST handlers
├── admin/
│   ├── class-admin.php           Appearance → ThemeToggle page, tabs, assets, reset-all
│   └── views/                    Templates (required from class methods)
├── assets/                       css/admin.css, css/frontend.css, js/admin.js
├── languages/themetoggle.pot
├── tests/e2e/                    HTTP end-to-end suite, fixture themes, CI WordPress installer
├── bin/                          check-version.sh, check-pot.sh (CI helpers)
├── .github/                      CI and release workflows, Dependabot
└── docs/                         ARCHITECTURE.md, TESTING.md
```

Details: [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md). Manual and automated testing: [docs/TESTING.md](docs/TESTING.md).

## How theme switching works

1. On `plugins_loaded` (priority 1) the plugin adds callbacks to the `template` and `stylesheet` filters at priority 99. `pluggable.php` is loaded by then, so the current user can be found, and the theme has not been located yet.
2. The first `get_template()` / `get_stylesheet()` call (after `setup_theme`) resolves the theme once for the request: master switch → request context → logged in → an eligible role → a valid preview → the saved theme, which must be installed, error-free (a child theme needs its parent), permitted by an eligible role and not the site default.
3. If that succeeds, `stylesheet` returns the chosen theme and `template` returns its template (the parent for a child theme). Otherwise both filters return the original values.
4. The global `template` / `stylesheet` options are never written and `switch_theme()` is never called.

A saved preference is only a preference: it is re-checked on every request. Invalid preferences are ignored (the default theme is used) but kept, so they apply again if they become valid.

### Admin switching

When enabled, the selected theme's `functions.php` (and whatever it registers) is loaded in wp-admin, and the block editor uses its `theme.json`, editor styles and templates. The WordPress admin interface is **not** restyled by a frontend theme. These screens always use the default theme: Themes, Add Themes, Theme File Editor, Site Editor, Customizer, Widgets, Menus, Updates and the plugin's own settings page.

AJAX, admin-post and REST requests follow the page that sent them (the `Referer`): from a frontend page they use frontend rules, from wp-admin they use admin rules. With no or a foreign referer they are never switched.

### Data protection while a personal theme is applied

- **Theme mods** are read from and written to the selected theme's own `theme_mods_{stylesheet}` row, never the default theme's.
- **Widgets:** writes to the site-wide `sidebars_widgets` option are discarded.
- **Caching:** responses get `Cache-Control: no-cache, … private`, `Vary: Cookie` and `DONOTCACHEPAGE`.

## Data

| Identifier | Kind | Contents |
| --- | --- | --- |
| `tt_settings` | Option (autoloaded) | All settings, see `Settings::get_defaults()` |
| `tt_user_theme` | User meta | Preferred theme stylesheet (directory name) |

Deactivation removes nothing. Deleting the plugin runs `uninstall.php`, which deletes both. WordPress deletes user meta when a user is deleted.

## Naming

| What | Prefix / name | Why |
| --- | --- | --- |
| Option, user meta, form fields, query vars, admin-post actions | `tt_` | Required by the original spec |
| PHP classes and constants | `ThemeToggle\` namespace (`ThemeToggle\VERSION`, `PLUGIN_DIR`, `PLUGIN_URL`, `PLUGIN_FILE`) | No global constants |
| Public hooks | `themetoggle_` | WPCS rejects prefixes shorter than 3 characters |
| Script / style handles, text domain, page slug | `themetoggle` / `themetoggle-*` | Plugin slug |
| CSS classes | `tt-` | Short and scoped |

## Hooks

| Hook | Type | Purpose |
| --- | --- | --- |
| `themetoggle_applied_theme` | filter | `WP_Theme\|null` to apply for this request; return `null` to keep the default. Second arg: `$is_preview`. |
| `themetoggle_request_context` | filter | `'frontend'`, `'admin'` or `'none'`. |
| `themetoggle_excluded_admin_pages` | filter | Admin file names that are never switched. |
| `themetoggle_excluded_ajax_actions` | filter | AJAX actions that are never switched. |
| `themetoggle_inherit_default_theme_mods` | filter | `bool`, default `true`: a theme without its own mods reads the default theme's mods (read-only). |
| `themetoggle_loaded` | action | Receives the `ThemeToggle\Plugin` instance; `$plugin->get( 'resolver' )` etc. |

Example — never switch on a custom admin page:

```php
add_filter(
	'themetoggle_excluded_admin_pages',
	static function ( array $pages ) {
		$pages[] = 'my-builder.php';
		return $pages;
	}
);
```

## Testing

- **Static:** `composer check` and `npm run lint` must report 0 errors and 0 warnings.
- **End-to-end:** `tests/e2e/` drives a real WordPress install over HTTP (123 checks: role rules, child themes, forged requests, open redirects, cache headers, admin/REST contexts, theme-mod and widget protection, users list, settings sanitising). It creates users and switches the active theme, so **only run it on a disposable site**. See [tests/e2e/README.md](tests/e2e/README.md).
- **Manual:** the checklist in [docs/TESTING.md](docs/TESTING.md).

PHP compatibility note: PHPCompatibilityWP 2.x flags features missing in PHP 7.4 but does not know PHP 8.0+ syntax. Run `composer lint` with a PHP 7.4 binary (for example in CI) to catch PHP 8-only syntax.

## CI/CD (GitHub Actions)

| Workflow | Runs on | What it does |
| --- | --- | --- |
| `.github/workflows/ci.yml` | Every push to `main`, every pull request, manual run | PHP syntax on 7.4–8.4 · PHPCS (WordPress + PHP compatibility, annotated on the PR) · JS/CSS/Markdown lint · POT up to date · e2e suite on WordPress 6.7 + PHP 7.4 and latest WordPress + PHP 8.3 (MySQL 8) |
| `.github/workflows/release.yml` | Pushing a tag `vX.Y.Z` | Runs all of CI, checks the tag matches every version number, builds `themetoggle-X.Y.Z.zip` with `git archive`, publishes a GitHub Release with the zip and generated notes |
| `.github/dependabot.yml` | Weekly | Grouped update PRs for Composer and npm dev tools, and for the Actions used |

The e2e job uses `tests/e2e/ci-install-wordpress.sh` to build the throwaway site; the same script works locally with a MySQL database. On failure, `debug.log` and the PHP server log are attached to the run as an artifact.

## Release checklist

1. Bump the version in `themetoggle.php` (header and `const VERSION`), `readme.txt` (`Stable tag` and a `= X.Y.Z =` changelog entry) and `package.json`. Check with `composer version:check`.
2. `composer check && npm run lint` — 0 errors, 0 warnings.
3. `composer make-pot` and commit the updated POT.
4. Merge to `main` with CI green.
5. Tag and push: `git tag v1.0.1 && git push origin v1.0.1`. The Release workflow builds and publishes the zip.
6. Install the zip from the GitHub Release on a clean site and smoke-test the manual checklist.

Without GitHub: `composer zip` (from the last commit) or `npm run plugin-zip` (from the working tree).

## Known limitations

- Multisite is not supported or tested. Settings are per site, but user meta is network-wide; the meta key is centralised in `Theme_Resolver::META_KEY`.
- A classic theme that was never active has no settings of its own (menu locations, logo) and reads the default theme's until it saves its own.
- Widget changes are not saved while a personal theme is applied; manage widgets from Appearance → Widgets.
- `remove_theme_mods()` deletes the option directly and cannot be intercepted (core only calls it from the Customizer, which is never switched).
- The preview lasts one page view.
- AJAX/REST requests without a `Referer` header are not switched.

## Working with AI agents

Agent instructions live in [AGENTS.md](AGENTS.md) (read by most coding agents; `CLAUDE.md` imports it). A step-by-step development skill and Copilot instructions are in [docs/ai/](docs/ai/README.md); that page explains how to install them where Claude Code and GitHub Copilot load them automatically.

## License

GPL-2.0-or-later. See <https://www.gnu.org/licenses/gpl-2.0.html>.
