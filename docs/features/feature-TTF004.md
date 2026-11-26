# TTF004 — WP-CLI commands

| Field | Value |
| --- | --- |
| Feature ID | TTF004 |
| Status | Idea |
| Group | Quick win |
| Effort | Small |
| Risk | Low |
| Build order | — |
| Depends on | TTF005 for `settings export/import` (same JSON format) |
| Related | TTF003 (`stats`), TTF006 (hooks fire from CLI too) |
| Released in | — |

## Summary

`wp themetoggle …` commands for support and automation: read, set and reset a user's theme, show stats, and export or import settings.

## Current state, required state, gap

- **Current:** everything is done in wp-admin. `Request_Context::is_unsupported_environment()` already returns `none` for WP-CLI, so CLI requests never switch theme.
- **Required:** the same operations from the command line, with the same validation.
- **Gap:** a command class registered only under WP-CLI.

## User stories and acceptance criteria

| Command | Result |
| --- | --- |
| `wp themetoggle user get <user>` | Saved preference, status, effective theme, permitted themes. `--format=table\|json\|yaml`. |
| `wp themetoggle user set <user> <stylesheet>` | Saves through `save_preference()`; exits with an error if the theme is not allowed for that user. |
| `wp themetoggle user reset <user>…` | Clears the preference for one or more users. |
| `wp themetoggle user reset --all [--yes]` | Clears every preference, after confirmation. |
| `wp themetoggle stats [--format=…]` | The TTF003 counts. |
| `wp themetoggle settings get [<key>]` | Reads `tt_settings`. |
| `wp themetoggle settings export [--file=<path>]` | TTF005 JSON to stdout or a file. |
| `wp themetoggle settings import <file> [--dry-run] [--yes]` | TTF005 import with the same sanitising. |

`<user>` accepts an ID, login or email, using `WP_CLI\Fetchers\User`.

All write commands support `--dry-run`, and print what changed.

## Design

- `includes/class-cli.php`, `ThemeToggle\CLI extends WP_CLI_Command`. Registered in `Plugin::init()` only when `defined( 'WP_CLI' ) && WP_CLI`, with `WP_CLI::add_command( 'themetoggle', … )`.
- The command takes the services from `ThemeToggle\plugin()->get( 'resolver' )` and friends; it doesn't build its own.
- No `--force` option that skips validation. If an admin needs a theme outside the role rules, the rules must be changed first. That keeps invariant 3 true for every path.
- Output with `WP_CLI\Utils\format_items()`; errors with `WP_CLI::error()` (exit code 1).
- `reset --all` asks through `WP_CLI::confirm()` unless `--yes` is given.

## Security

| Threat | Prevention |
| --- | --- |
| Data corruption through bad input. | Every write goes through `save_preference()` or `Settings::sanitize()`. |
| Accidental mass reset. | Confirmation; `--dry-run`; a count before and after. |
| Commands reachable over HTTP. | The class file is only loaded under WP-CLI; the file itself starts with `defined( 'ABSPATH' ) \|\| exit;` like every include. |
| `settings import` reading arbitrary files. | CLI users already have shell access; still, read only regular files below a size limit (64 KB), and parse JSON only, never `unserialize()`. |

WP-CLI has no capability system; anyone who can run it can already change the database. Validation here protects data integrity, not access.

## Caching and headers

None. If a persistent object cache is used, `update_user_meta()` and `update_option()` already clear the right cache keys. Do not write to the database directly.

## Data integrity, privacy and leakage

- `user get --format=json` prints user IDs and theme slugs only; no emails unless asked by a core command.
- TTF006 actions fire with `source = 'cli'`.

## Performance

`reset --all` uses the same `delete_metadata()` call as the admin tool. `stats` reuses TTF003's batching.

## Compatibility

WP-CLI 2.x. The e2e suite already uses the WP-CLI phar, so tests can call it.

## Architecture invariants

- 3: no write bypasses `save_preference()`.
- 4: only explicit commands delete preferences.

## Tests

Run with the e2e suite's WP-CLI:

- `user set` with a permitted theme: exit 0, meta set.
- `user set` with a non-permitted theme: exit 1, meta unchanged.
- `user reset --all --dry-run`: nothing deleted.
- `settings import` of a file with an unknown role and an uninstalled theme: both are dropped and reported.
- Requesting `includes/class-cli.php` over HTTP: no output, no error trace.

## Documentation to update

`README.md` (new "WP-CLI" section and Commands table), `readme.txt` (FAQ), `docs/ARCHITECTURE.md` (class table).

## Open questions

1. Should `user set` accept the global default theme as a value meaning "reset"? Recommendation: no; keep `reset` as the only way, to avoid two meanings.

## Decision log

- 2026-11-26: Spec written. No `--force` flag.
