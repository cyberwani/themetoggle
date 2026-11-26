# AGENTS.md — ThemeToggle

Instructions for AI coding agents (Claude Code, Codex, Cursor, Copilot, Gemini and others) working on this repository. Humans: see [README.md](README.md).

## What this is

A WordPress plugin that lets each logged-in user see their own theme, chosen from themes an administrator permits per role. It overrides the theme **per request** through the `template` and `stylesheet` filters. It never changes the site's active theme.

- PHP 7.4+, WordPress 5.9+, no runtime dependencies.
- Namespace `ThemeToggle`, text domain `themetoggle`.
- Stored data: option `tt_settings`, user meta `tt_user_theme`.

Before changing behaviour, read [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md): it has the service graph, the request lifecycle and the invariants. The step-by-step workflow for a change is the skill in [docs/ai/themetoggle-development/SKILL.md](docs/ai/themetoggle-development/SKILL.md); read and follow it before any change. (It is kept under `docs/ai/` so it lives in the repository; see [docs/ai/README.md](docs/ai/README.md) to install it where your tool auto-loads skills.)

## Commands

```bash
composer install && npm install    # dev tools only
composer check                     # PHP lint + PHPCS (WordPress + PHP 7.4 compat) — must be clean
npm run lint                       # JS + CSS + PHP — must be clean
composer phpcbf / npm run fix      # auto-fix style
composer make-pot                  # regenerate languages/themetoggle.pot after any string change
composer pot:check                 # fails if the POT is missing source strings (CI runs this)
composer version:check             # all version numbers agree (the release workflow runs this)
composer test:e2e                  # end-to-end suite — disposable site only (tests/e2e/README.md)
npm run plugin-zip                 # build/themetoggle.zip from the working tree (= composer zip:local)
composer zip                       # build/themetoggle.zip from the last commit (what the release workflow ships)
```

If a tool cannot be installed in your environment, say so in your report. Never claim a check passed unless you ran it and saw the output.

## Non-negotiable rules

1. **Never** write the `template`, `stylesheet` or `current_theme` options, never call `switch_theme()`.
2. **Never** call `wp_get_theme()` with no argument, or `get_stylesheet()` / `get_template()`, from code that can run during resolution: this plugin filters them, so they recurse. Use `get_option( 'stylesheet' )` for the global theme and `Theme_Manager::get_theme( $slug )` for a theme.
3. **A saved preference is not permission.** Read through `Theme_Resolver::get_user_state()`, write through `Theme_Resolver::save_preference()`. Never apply `tt_user_theme` directly.
4. **Do not delete invalid preferences automatically.** Only explicit actions (Default Theme, button, bulk reset, Reset All, uninstall) delete `tt_user_theme`.
5. **Every state change** = nonce check + capability check + `Redirect::get_target()` for redirects. Core does **not** check the nonce for custom user bulk actions — the plugin must.
6. **Keep `Data_Guard` in force** whenever a theme is applied (theme mods isolated, `sidebars_widgets` not written). If you find more core data keyed by the raw `stylesheet` option, protect it there.
7. **Switched responses are private:** keep `send_private_cache_headers()`.
8. **Resolution stays cheap:** no `wp_get_themes()`, no extra queries for logged-out visitors.
9. **No translation calls before `init`.**
10. **Do not switch** on theme-management, widget, menu, Site Editor, Customizer or update screens, or on their AJAX/REST calls. Add new risky screens to `Request_Context`, never remove existing ones without a reason recorded in the commit.

If a request conflicts with one of these, stop and explain the conflict instead of complying silently.

## Conventions

- **Style:** WordPress Coding Standards (tabs, Yoda conditions, `array()`, spaces inside parentheses), enforced by `phpcs.xml.dist`. JS/CSS follow `@wordpress/scripts` defaults.
- **Files:** one class per file. `ThemeToggle\Foo_Bar` → `includes/class-foo-bar.php`; `ThemeToggle\Admin\Foo` → `admin/class-foo.php` (see `includes/class-autoloader.php`). Views live in `admin/views/` and are `require`d from a class method with a `$view` array; they start with the `phpcs:disable …NonPrefixedVariableFound` line already used there.
- **Dependencies:** constructor injection, wired in `Plugin::init()`. No new singletons, no globals, no static methods except stateless helpers (`Redirect`) and activation (`Installer`, `Settings::get_defaults()`).
- **Prefixes:** stored keys / form fields / query vars / admin-post actions `tt_`; hooks `themetoggle_`; handles `themetoggle-*`; CSS classes `tt-`. WPCS rejects `tt` as a PHP prefix (too short), which is why hooks use `themetoggle_`.
- **Escaping:** escape late (`esc_html`, `esc_attr`, `esc_url`, `wp_kses` for the SVG icons). Sanitise early (`wp_unslash` then `sanitize_text_field` / `absint` / `sanitize_key`). Roles are validated against `wp_roles()` exactly; themes with `Theme_Manager::is_installed()`.
- **Strings:** translatable with the `themetoggle` domain, translator comments for placeholders. Regenerate the POT.
- **Docs:** update `README.md`, `readme.txt`, `docs/ARCHITECTURE.md` or `docs/TESTING.md` when behaviour, hooks, settings or commands change.
- **Changelog:** add every user-visible or developer-relevant change to `## [Unreleased]` in `CHANGELOG.md` (Keep a Changelog sections: Added, Changed, Fixed, Security, Removed), with the commit hash once committed.
- **Version bumps:** `themetoggle.php` header and `const VERSION`, `readme.txt` Stable tag + changelog, `package.json` (+ `package-lock.json`), and a `CHANGELOG.md` section; verify with `composer version:check`. Releases are published by pushing a `vX.Y.Z` tag (`.github/workflows/release.yml`).
- **CI:** `.github/workflows/ci.yml` must stay green. If you add a check locally, add it there too; if you change a test helper or the e2e setup, keep `tests/e2e/ci-install-wordpress.sh` working.

## Definition of done

- [ ] Change is the smallest that meets the requirement; no unrelated refactors.
- [ ] `composer check` and `npm run lint`: 0 errors, 0 warnings (output seen).
- [ ] New or changed behaviour has an e2e check in `tests/e2e/http-tests.sh`; the suite passes on a disposable site — or you state clearly that it was not run and why.
- [ ] POT regenerated if strings changed.
- [ ] Docs updated.
- [ ] Report lists: what changed, design decisions, tests added, check results, follow-ups and risks.

## Where things are

| Need to change… | Look in |
| --- | --- |
| Which theme a user gets | `includes/class-theme-resolver.php`, `includes/class-theme-switcher.php` |
| When switching applies (frontend/admin/AJAX/REST) | `includes/class-request-context.php` |
| Role eligibility / theme union | `includes/class-role-manager.php` |
| Settings keys, defaults, validation | `includes/class-settings.php` |
| Settings screens | `admin/class-admin.php`, `admin/views/settings-*.php` |
| Profile selector | `includes/class-user-profile.php`, `admin/views/profile-section.php` |
| Floating button / preview bar | `includes/class-floating-button.php`, `includes/class-preview.php`, `admin/views/floating-button.php`, `assets/css/frontend.css` |
| Users list column / bulk action | `includes/class-users-list.php` |
| Theme-mod / widget protection | `includes/class-data-guard.php` |
| Activation / uninstall | `includes/class-installer.php`, `uninstall.php` |

## Background

The original specification asked for a plugin inspired by Theme Test Drive 2.9.1 (`themedrive_determine_theme()`, `themedrive_get_template()`, `themedrive_get_stylesheet()`), adapted from a global option to per-user meta with role permissions. Do not reintroduce its legacy parts: `level_N` capabilities, a global selected-theme option, `?theme=` URL switching, or custom ZIP theme installation.
