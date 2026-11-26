# Changelog

All notable changes to ThemeToggle are documented in this file. The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries name the commits they come from. User-facing changes are also summarised in the `== Changelog ==` section of `readme.txt`.

## [Unreleased]

## [1.0.1] - 2026-11-26

### Added

- General Settings option **Users List Column** to show or hide the "Selected Theme" column in Users → All Users. It is on by default. Hiding the column also turns off sorting by it; the "Reset selected users' themes" bulk action stays available. ([804420d](https://github.com/cyberwani/themetoggle/commit/804420d))
- `docs/github-ci-setup.md`, a step-by-step guide to the GitHub repository, CI and releases, and `docs/TODO.md` for open work. ([bff5b8c](https://github.com/cyberwani/themetoggle/commit/bff5b8c))
- `bin/build-zip.php` and `composer zip:local`: a release zip from the working tree with the same file list as `composer zip`. ([a80aa06](https://github.com/cyberwani/themetoggle/commit/a80aa06))
- This changelog.

### Changed

- Role-Based Theme Assignment only shows a role's themes while the role is ticked under "User Roles Allowed to Switch Themes". Rows show or hide as soon as a role is ticked or unticked. An unticked role keeps its saved themes, and a hint replaces the table when no role is allowed. The "Allowed / Not allowed" badges are removed. ([32b2ea3](https://github.com/cyberwani/themetoggle/commit/32b2ea3))
- Composer and npm scripts now run in Windows `cmd` and PowerShell. WP-CLI is called through PHP, so no Unix shell is needed. The version and translation checks are rewritten in PHP (`bin/check-version.php`, `bin/check-pot.php`) and replace the bash scripts. `npm run plugin-zip` no longer adds `README.md` and `package.json` to the zip. ([a80aa06](https://github.com/cyberwani/themetoggle/commit/a80aa06), [c3a1673](https://github.com/cyberwani/themetoggle/commit/c3a1673))
- The release workflow checks versions with `php bin/check-version.php`. ([a80aa06](https://github.com/cyberwani/themetoggle/commit/a80aa06))
- Translation template regenerated. ([a38e178](https://github.com/cyberwani/themetoggle/commit/a38e178), [3268e04](https://github.com/cyberwani/themetoggle/commit/3268e04))
- README and setup documentation updated for the changes above. ([bfbb954](https://github.com/cyberwani/themetoggle/commit/bfbb954))

### Fixed

- End-to-end suite: the REST checks failed on WordPress 6.7 because the test scraped its REST nonce from Dashboard HTML that 6.7 does not print. It now uses core's `admin-ajax.php?action=rest-nonce`, and reports clearly if no nonce is obtained. This also makes the REST widget-protection check meaningful on 6.7: it had been passing only because its request was rejected.
- The floating "Switch to Default Theme" button kept its style on sites using Elementor. Elementor's frontend CSS was overriding the button's layout, colours and spacing. ([6059ad8](https://github.com/cyberwani/themetoggle/commit/6059ad8))
- `composer zip` failed on a fresh checkout because the `build/` folder did not exist; it is now created first. ([949a842](https://github.com/cyberwani/themetoggle/commit/949a842))
- `phpcs.xml.dist` exclude patterns are anchored to the plugin folder. Before, a checkout under any folder named `build`, `vendor`, `node_modules` or `bin` skipped every file and reported no problems. Also fixes the PHPCS alignment warning in `includes/class-floating-button.php`. ([ead7acb](https://github.com/cyberwani/themetoggle/commit/ead7acb))

### Security

- The development scripts in `bin/` refuse to run when requested over HTTP (403), for sites that run a git checkout instead of the release zip. ([c3db809](https://github.com/cyberwani/themetoggle/commit/c3db809))
- A theme returned by the `themetoggle_applied_theme` filter must be installed and error-free, and is never applied on screens ThemeToggle does not switch (Themes, Widgets, Menus, Site Editor, Customizer, Updates). Before, a broken theme reaching the Themes screen could make WordPress switch the site's active theme. ([c3db809](https://github.com/cyberwani/themetoggle/commit/c3db809))
- Sorting Users → All Users by "Selected Theme" keeps user-list restrictions added by other plugins instead of replacing them. ([c3db809](https://github.com/cyberwani/themetoggle/commit/c3db809))
- Floating-button colours, radius, position, size, icon and label are re-validated when they are output, not only when they are saved. ([c3db809](https://github.com/cyberwani/themetoggle/commit/c3db809))

## [1.0.0] - 2026-11-25

### Added

- Initial release. ([f6bac97](https://github.com/cyberwani/themetoggle/commit/f6bac97))
- Per-user themes chosen from themes an administrator permits per role, applied per request through the `template` and `stylesheet` filters. The site's active theme is never changed.
- Role eligibility kept separate from role → theme assignment; any registered role is supported, custom roles included; users with several roles get the combined list.
- Profile "Personal Theme Settings" with a Default Theme option and a one-page preview.
- Floating "Switch to Default Theme" button and preview bar.
- "Selected Theme" column in Users → All Users with sorting, a bulk reset action and Reset All.
- Frontend and optional admin switching, with theme-management, widget, menu, Site Editor, Customizer and update screens excluded.
- Protection of site-wide data while a personal theme is applied (theme mods, widget layout, private no-cache headers).
- WordPress Coding Standards tooling, end-to-end test suite, GitHub CI and release workflows, and developer and AI-agent documentation.

[Unreleased]: https://github.com/cyberwani/themetoggle/compare/v1.0.1...HEAD
[1.0.1]: https://github.com/cyberwani/themetoggle/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/cyberwani/themetoggle/releases/tag/v1.0.0
