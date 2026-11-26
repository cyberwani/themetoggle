# TTF011 — Audit log

| Field | Value |
| --- | --- |
| Feature ID | TTF011 |
| Status | Idea |
| Group | Medium |
| Effort | Medium |
| Risk | Medium |
| Build order | — |
| Depends on | TTF006 (change hooks) |
| Related | TTF008, TTF018 (preview-link events) |
| Released in | — |

## Summary

A log of who changed whose theme and who changed the settings, with time, before and after values, and where the change came from. It has a retention limit, an admin viewer and a CSV export, and supports the WordPress privacy tools.

## Current state, required state, gap

- **Current:** no history.
- **Required:** an append-only log with retention, built on TTF006's actions.
- **Gap:** a table, listeners, a viewer, cleanup and privacy hooks.

## User stories and acceptance criteria

1. As an admin, I open ThemeToggle → Log and see changes, newest first, filterable by user, event and date.
2. Each row has time (site timezone), actor, target user, event, old value, new value and source.
3. Entries older than the retention setting (default 90 days, 7–730) are deleted automatically.
4. I can export the filtered view as CSV.
5. Personal data export and erasure (Tools → Export / Erase Personal Data) include log entries about a user.

## Design

### Table

`{$wpdb->prefix}tt_audit_log`, created with `dbDelta()` in `Installer` and versioned with a `tt_db_version` option:

| Column | Type |
| --- | --- |
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PK |
| `created_at` | DATETIME (UTC), indexed |
| `actor_id` | BIGINT UNSIGNED (0 = system/CLI) |
| `target_user_id` | BIGINT UNSIGNED, indexed (0 for settings events) |
| `event` | VARCHAR(40): `preference_changed`, `preferences_reset`, `preferences_reset_all`, `settings_saved`, `lock_changed`, `share_link_*` |
| `source` | VARCHAR(20) |
| `old_value` | LONGTEXT (JSON) |
| `new_value` | LONGTEXT (JSON) |

A custom table, not an option: an option would be rewritten in full on every change, grow without limit and be autoloaded.

### Writing

- Listeners on TTF006's actions call `$wpdb->insert()` with format arrays, so values are always escaped by `$wpdb`.
- For `settings_saved`, store only the keys that changed.
- Never log from the resolution path.

### Retention

- Daily cron job `themetoggle_audit_cleanup`: delete rows older than the retention setting, in chunks of 1,000.
- Also a hard limit of 100,000 rows, deleting the oldest.
- Unschedule on deactivation; drop the table on uninstall.

### Viewer

- `WP_List_Table` on a new settings tab, `manage_options`.
- Filters are read with `absint()` or whitelists and passed to `$wpdb->prepare()`.
- Values shown with `esc_html()`.

### CSV export

- `admin_post_` handler, nonce, capability.
- Headers: `Content-Type: text/csv`, `Content-Disposition: attachment`, `nocache_headers()`.
- Cells that start with `=`, `+`, `-`, `@`, tab or CR get a leading `'`, to stop formula injection in Excel.

### Privacy

- `wp_privacy_personal_data_exporters`: rows where the user is actor or target.
- `wp_privacy_personal_data_erasers`: set `actor_id` / `target_user_id` to 0 and blank the values. The event and time are kept for the record.
- `wp_add_privacy_policy_content()`: a suggested paragraph.
- No IP addresses by default. If added later, store a salted hash only.

## Security

| Threat | Prevention |
| --- | --- |
| Log tampering through the UI. | No edit or delete UI; rows are deleted only by retention. |
| SQL injection in filters. | `prepare()`, whitelisted event names, `absint()` IDs. |
| Formula injection in CSV. | Prefix dangerous leading characters. |
| XSS through logged values (theme slugs from other code). | Escaped on output; stored as JSON text. |
| Data exposure. | Viewer and export are `manage_options` only. |
| Log flooding (denial of service). | Writes happen only on real changes, which need a nonce and capability; there is a row limit. |

## Caching and headers

Admin pages and downloads only; the CSV response has `nocache_headers()`.

## Data integrity, privacy and leakage

- The log is personal data: retention, export and erasure are part of the feature, not extras.
- Logs record theme slugs and user IDs, not names; names are looked up when shown.

## Performance

- One insert per change. Changes are rare.
- Reset All logs one row with a count, not one row per user.

## Compatibility

- `dbDelta()` works on MySQL and MariaDB. The e2e suite runs on SQLite, which the SQLite integration supports for simple `CREATE TABLE`; check that the schema creates cleanly there.

## Architecture invariants

The listeners must not change or block the write that triggered them.

## Tests

- Profile change: one row with the right actor, target, old and new value, and source.
- Settings save changing one key: one row with only that key.
- Retention: insert an old row through WP-CLI, run the cron hook with `wp cron event run`, row gone.
- CSV: a slug `=HYPERLINK(...)` comes out as `'=HYPERLINK(...)`.
- Viewer as a non-admin: 403.
- Erasure for user A: their IDs are 0 in all rows.
- Uninstall drops the table.

## Documentation to update

`README.md` (Data section: new table, retention), `readme.txt` (privacy), `docs/ARCHITECTURE.md`, `docs/TESTING.md`, POT, `uninstall.php`.

## Open questions

1. Is a log needed, or would sites use an existing activity-log plugin with TTF006's hooks? Recommendation: build TTF006 first and ask users.

## Decision log

- 2026-11-26: Spec written. Custom table, no IPs by default.
