# ThemeToggle TODO

What is done, what is still open, and anything found later. Tick items as they are finished and add new findings under [Found later](#found-later).

Last updated: 2026-11-26

## Features

Planned features, each with a Feature ID. The full spec of each is in `docs/features/feature-<ID>.md`; ID rules, status values and the spec template are in [`docs/features/README.md`](features/README.md). Keep this table, the spec's header and `docs/ThemeToggle feature ideas.xlsx` in step when a status changes.

Recommended order: TTF001 → TTF007 → TTF002 and TTF003 → TTF010 → TTF018.

| ID | Feature | Group | Effort | Risk | Status | Depends on |
| --- | --- | --- | --- | --- | --- | --- |
| [TTF001](features/feature-TTF001.md) | Toolbar menu (theme switcher and feature links) | Quick win | Small | Low | Idea | — |
| [TTF002](features/feature-TTF002.md) | "Why this theme?" admin info | Quick win | Small | Low | Idea | TTF001 optional |
| [TTF003](features/feature-TTF003.md) | Usage stats | Quick win | Small | Low | Idea | — |
| [TTF004](features/feature-TTF004.md) | WP-CLI commands | Quick win | Small | Low | Idea | TTF005 |
| [TTF005](features/feature-TTF005.md) | Settings import/export (JSON) | Quick win | Small | Low | Idea | — |
| [TTF006](features/feature-TTF006.md) | Change hooks | Quick win | Small | Low | Idea | — |
| [TTF007](features/feature-TTF007.md) | Default theme per role | Medium | Medium | Medium | Idea | — |
| [TTF008](features/feature-TTF008.md) | Admin-assigned, locked themes | Medium | Medium | Low | Idea | — |
| [TTF009](features/feature-TTF009.md) | Time-limited assignments | Medium | Medium | Low | Idea | TTF008 |
| [TTF010](features/feature-TTF010.md) | Cache-plugin compatibility | Medium | Medium | Medium | Idea | — |
| [TTF011](features/feature-TTF011.md) | Audit log | Medium | Medium | Medium | Idea | TTF006 |
| [TTF012](features/feature-TTF012.md) | Rules beyond roles | Medium | Medium | Medium | Idea | — |
| [TTF013](features/feature-TTF013.md) | Preview across pages | Medium | Medium | Medium | Idea | — |
| [TTF014](features/feature-TTF014.md) | Multisite support | Larger | Large | High | Idea | — |
| [TTF015](features/feature-TTF015.md) | Settings for non-active classic themes | Larger | Large | High | Idea | — |
| [TTF016](features/feature-TTF016.md) | Visitor (logged-out) switching | Larger | Large | High | Idea | TTF010 |
| [TTF017](features/feature-TTF017.md) | REST API for preferences | Larger | Medium | Medium | Idea | — |
| [TTF018](features/feature-TTF018.md) | Shareable preview links (no login) | Larger | Large | Medium | Idea | TTF010; TTF001 optional |

Next free ID: TTF019.

## Pending

### Verify

- [ ] Activate the plugin on the Kures dev site (WAMP) and work through the manual checklist in `docs/TESTING.md`.
- [ ] Look at every admin screen in a browser: the four settings tabs, the profile selector, the Users column, the floating button and the preview bar. Until now they were only checked as HTML, never visually.
- [ ] Push to `github.com/cyberwani/themetoggle` and get the first CI run green. Not yet proven on GitHub:
    - [ ] the workflow files themselves (`actionlint` was never run)
    - [ ] the MySQL service and theme downloads in `tests/e2e/ci-install-wordpress.sh`
    - [ ] the WordPress 6.7 + PHP 7.4 e2e job (only WordPress 7.3-alpha + PHP 8.3 was tested)
- [ ] After the first CI run, set up the `main` ruleset with the required checks listed in `docs/github-ci-setup.md`.
- [ ] Check github.com/cyberwani/themetoggle/releases: the `v1.0.0` tag (initial commit) already contains the release workflow, so pushing it may have run a release. Confirm whether it succeeded.
- [ ] Publish `v1.0.1`: commit, push `main` with CI green, push the `v1.0.1` tag, check the release workflow, and install the release zip on a clean site.

### Decide

- [ ] Hook names: keep `themetoggle_*` (passes WPCS) or rename to `tt_*` (matches the original spec, fails the WPCS prefix check).
- [ ] `npm run build` rewrites `languages/themetoggle.pot` on every run (timestamp only). Option: run `composer pot:check` in the build instead, and regenerate with `composer make-pot` only when strings change.
- [ ] Add `/Claude outputs/` to `.gitignore`. The Claude desktop app creates that folder when an attachment is saved while this folder is selected.
- [ ] Continuous deployment beyond GitHub Releases: publish to WordPress.org (SVN) and/or deploy to a server.

### Fill in

- [ ] Plugin header has no `Author`, `Author URI` or `Plugin URI`, so the POT copyright line is blank (`# Copyright (C) 2026`). Add them to `themetoggle.php`, add `Contributors:` to `readme.txt`, then run `composer make-pot`.
- [ ] Root `github-ci-config.md` is an untracked copy of the old CI instructions, which still name the deleted `.sh` scripts. It is superseded by `docs/github-ci-setup.md`: delete it or keep it out of git.

### Improve (optional)

- [ ] Multisite support → [TTF014](features/feature-TTF014.md). Not implemented; do not claim support until it is.
- [ ] Preview across page views (it currently lasts one page) → [TTF013](features/feature-TTF013.md).
- [ ] Let users on a personal theme manage widgets. Writes to `sidebars_widgets` are blocked while a personal theme is applied.
- [ ] A way to configure a classic theme's own settings (menu locations, logo) without making it the active theme once → [TTF015](features/feature-TTF015.md).
- [ ] Theme installation from the settings page, using WordPress's own installer APIs. Out of scope for 1.0.0.
- [ ] Move to PHPCompatibility 10 when it is stable, so PHPCS also flags PHP 8-only syntax. CI's PHP 7.4 lint covers this today.
- [ ] Compare against the Theme Test Drive 2.9.1 source; it was never provided, so the build relied on its known approach.
- [ ] Optional auto-loading copies of the AI files (`.claude/skills/…`, `.github/copilot-instructions.md`); see `docs/ai/README.md`.

## Found later

Add new findings here, newest first.

- [x] 2026-11-26 — TTF001 widened from "Admin-bar theme switcher" to "Toolbar menu": one ThemeToggle toolbar menu with Switch theme (TTF001), Share this page… and My preview links (TTF018) and Why this theme? (TTF002, admins). Specs TTF001, TTF002 and TTF018 updated.
- [x] 2026-11-26 — Feature IDs introduced. The 17 ideas from the feature sheet and the new shareable preview links feature are now TTF001–TTF018, each with a spec in `docs/features/`, listed under [Features](#features) and in `docs/ThemeToggle feature ideas.xlsx`. The Improve items for multisite, preview across pages and classic theme settings now point to TTF014, TTF013 and TTF015.
- [x] 2026-11-26 — CI job `E2E (WP 6.7, PHP 7.4)` failed: the six REST checks got no REST nonce, because WordPress 6.7 does not print `createNonceMiddleware()` on the Dashboard. Test-only bug; the plugin was fine. The suite now takes the nonce from `admin-ajax.php?action=rest-nonce`. Reproduced and verified locally on WordPress 6.7 and 7.3-alpha (165/0 each).
- [x] 2026-11-26 — Version bumped to 1.0.1 (plugin header, `VERSION`, `readme.txt` Stable tag and changelog, `package.json`, `package-lock.json`); `CHANGELOG.md` added from the git history since `v1.0.0`.
- [x] 2026-11-26 — New General Settings option "Users List Column" (`show_users_column`, default on) to show or hide the "Selected Theme" column in Users → All Users. Hiding it also disables sorting by it; the bulk reset action stays. Covered by 11 e2e checks.
- [x] 2026-11-26 — `composer phpcs` warned about an unaligned `=` in `includes/class-floating-button.php`, introduced by the security fixes. Root cause of it slipping through: `phpcs.xml.dist` excluded `/build/*` unanchored, which matched the sandbox path `/home/claude/build/…`, so PHPCS there checked no files. Patterns are now anchored to the plugin folder; warning fixed.
- [x] 2026-11-26 — Role-Based Theme Assignment listed theme checkboxes for every role, even roles not ticked as allowed (e.g. Contributor, Subscriber). Now only allowed roles have a row; rows show/hide live when a role is ticked; an unticked role keeps its saved themes; with no role ticked a hint is shown. Covered by 8 e2e checks (server-rendered HTML) and a 13-check jsdom run of the live toggle.
- [ ] …

## Done

### Plugin (1.0.0)

- [x] Role eligibility kept separate from role → theme assignment, with any registered role supported, custom roles included.
- [x] Per-user preference (`tt_user_theme`) re-validated on every request; invalid choices fall back to the default theme and are kept.
- [x] Theme applied per request through the `template` / `stylesheet` filters, with correct child/parent handling. The global theme is never changed.
- [x] Frontend and admin switching with exclusions for theme, widget, menu, Site Editor, Customizer and update screens; AJAX, admin-post and REST classified by referer.
- [x] Data protection while a personal theme is applied: theme mods isolated, site-wide widget layout not rewritten, private no-cache headers.
- [x] Settings page with four tabs (General, Role-Based Themes, User Profiles, Floating Button).
- [x] Profile "Personal Theme Settings" with Default Theme, a keep-stale option and a one-page preview.
- [x] Floating "Switch to Default Theme" button and preview bar.
- [x] Users column (sortable), bulk reset with nonce check, Reset All with confirmation.
- [x] Nonce and capability checks on every state change; same-site redirects only.
- [x] Uninstall cleanup; translation template (`languages/themetoggle.pot`).

### Quality

- [x] PHPCS (WordPress standard + PHP 7.4 compatibility): 0 errors, 0 warnings.
- [x] JS, CSS and Markdown lint clean.
- [x] End-to-end suite: 165 checks passing on fresh sites with WordPress 6.7 and 7.3-alpha (PHP 8.3, SQLite).
- [x] Security audit (2026-11-26): `bin/` scripts made CLI-only; Users-list sort keeps other plugins' `meta_query`; filter-supplied themes validated and never applied on excluded screens (a broken one could switch the site theme); button styles re-validated on output. Each fix has e2e checks that failed on the old code. Also fixed a vacuous Users-list row count in the e2e suite.

### Tooling and docs

- [x] `composer.json` and `package.json` with lint, fix, POT, version-check, zip, build and e2e commands; all run in Windows `cmd` except the e2e suite (bash).
- [x] `composer zip` (last commit) and `npm run plugin-zip` (working tree) produce the same 30-file zip.
- [x] `.editorconfig`, `.gitattributes` (LF line endings, dev files kept out of zips), `.gitignore`, `.distignore`, `.nvmrc`.
- [x] CI, release and Dependabot workflows in `.github/`.
- [x] Docs: `README.md`, `readme.txt`, `docs/ARCHITECTURE.md`, `docs/TESTING.md`, `docs/github-ci-setup.md`.
- [x] AI agent guidance: `AGENTS.md`, `CLAUDE.md`, `docs/ai/` (development skill, Copilot instructions).
- [x] Git repository created with `composer.lock`, the regenerated POT and the `.github` files committed; remote set to `github.com/cyberwani/themetoggle`.
