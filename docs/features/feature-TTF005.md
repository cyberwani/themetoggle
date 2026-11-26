# TTF005 — Settings import/export (JSON)

| Field | Value |
| --- | --- |
| Feature ID | TTF005 |
| Status | Idea |
| Group | Quick win |
| Effort | Small |
| Risk | Low |
| Build order | — |
| Depends on | — |
| Related | TTF004 (CLI uses the same format), TTF011 (log imports) |
| Released in | — |

## Summary

Download the plugin settings as a JSON file and load them on another site, for example from staging to production. Imports go through the same validation as a form save and show a preview before anything changes.

## Current state, required state, gap

- **Current:** settings are one option, `tt_settings`, saved per tab through the Settings API. `Settings::sanitize()` validates the whole array when the payload has no `_tab`.
- **Required:** export to a file, import from a file, with validation, preview and a backup.
- **Gap:** a "Tools" tab with two handlers, plus a versioned file format.

## User stories and acceptance criteria

1. As an admin, I click "Export settings" and download `themetoggle-settings-{site}-{date}.json`.
2. I upload that file on another site and see a preview: the changes, and what will be dropped (roles that don't exist there, themes not installed there).
3. I confirm, and the settings are saved. My previous settings are offered as a download first.
4. User preferences are **not** exported. They are personal data and belong to users of one site.
5. Invalid files (wrong plugin, newer schema, broken JSON, too large) are refused with a clear message.

## Design

### File format

```json
{
	"plugin": "themetoggle",
	"schema": 1,
	"plugin_version": "1.0.1",
	"exported_at": "2026-11-26T16:30:00Z",
	"settings": { "enabled": true, "allowed_roles": [ "editor" ], "role_themes": { "editor": [ "tt-child" ] } }
}
```

- `schema` changes only when the meaning of keys changes. Import refuses a schema newer than the plugin knows, and migrates older ones.
- Export removes nothing sensitive, because the settings hold none: roles, theme slugs, button style.

### Export

- `admin-post.php?action=tt_export_settings`, nonce `tt_export_settings`, `manage_options`.
- Headers: `Content-Type: application/json; charset=utf-8`, `Content-Disposition: attachment; filename="…"`, `X-Content-Type-Options: nosniff`, `nocache_headers()`.
- Body: `wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES )`.

### Import, step 1: upload and preview

- Form with `enctype="multipart/form-data"` posts to `admin-post.php?action=tt_import_preview`.
- Checks:
    1. Nonce and capability.
    2. `is_uploaded_file()`, `UPLOAD_ERR_OK`, size ≤ 64 KB.
    3. `json_decode( $raw, true, 16 )`; must be an array with `plugin === 'themetoggle'`, an integer `schema` and an array `settings`.
- Run `Settings::sanitize( $settings )` **without** `_tab`, so the whole array is validated. Compare the result with the input to list dropped values.
- Keep the sanitised result in a transient `tt_import_{user_id}` (10 minutes). Don't keep the file: read it from the temporary upload and let PHP delete it.
- Show the preview: a table of key, current value, new value, and the list of dropped values. All escaped.

### Import, step 2: apply

- `admin-post.php?action=tt_import_apply`, a separate nonce, capability check.
- Read the transient for the current user only. `update_option( Settings::OPTION_NAME, $sanitised )` runs `sanitize()` once more through the Settings API, which is harmless.
- Delete the transient; redirect with a notice.

### Files

`admin/class-admin.php` (Tools tab, three handlers) or a new `admin/class-tools.php`; `admin/views/settings-tools.php`; `includes/class-settings.php` (an `export()` method and the schema constant).

The settings page lives under `themes.php`, so `Request_Context` already excludes it from switching.

## Security

| Threat | Prevention |
| --- | --- |
| Uploading code or a web shell. | The upload is never moved or stored; only its text is read, as JSON. |
| PHP object injection. | `json_decode()` only; never `unserialize()` or `maybe_unserialize()`. |
| Oversized or deeply nested JSON (denial of service). | 64 KB limit, `json_decode()` depth 16. |
| Invalid roles or themes, or malicious button CSS values. | Full `Settings::sanitize()`, which whitelists choices and validates colours; `Settings::get_clean()` re-checks on output (invariant 12). |
| CSRF that applies someone else's settings. | Separate nonces for preview and apply; the transient is keyed by user ID. |
| XSS through file contents in the preview. | Every value escaped; nested arrays shown with `esc_html( wp_json_encode() )`. |
| Export downloaded by a non-admin. | `manage_options`. |

## Caching and headers

Export response: `nocache_headers()` and `nosniff`, so a proxy never stores a copy of the settings.

## Data integrity, privacy and leakage

- User preferences are excluded by design. If they are ever needed, it should be a separate, explicit option that maps users by login and warns about personal data.
- The backup offered before applying lets an admin undo a bad import.
- TTF006 `themetoggle_settings_saved` fires with source `import`.

## Performance

Negligible: one option write.

## Compatibility

`role_themes` refers to role and theme slugs. On a site with other roles or themes, those entries are dropped, and the preview lists them.

## Architecture invariants

- 5: nonce and capability on all three handlers.
- 12: imported values are validated on save and again on output.

## Tests

- Export: valid JSON with `plugin`, `schema` and the current settings; correct headers.
- Import a valid file: the preview lists the changes; apply changes `tt_settings`.
- Import with an unknown role, an uninstalled theme and `button_bg_color = "red;background:url(…)"`: all three dropped or reset to defaults.
- Broken JSON, a file over 64 KB, `plugin: "other"`, `schema: 99`: refused, settings unchanged.
- Apply without a preview transient, or with another user's: refused.
- Without nonces: refused.

## Documentation to update

`README.md`, `readme.txt` (FAQ "Move settings between sites"), `docs/ARCHITECTURE.md` (security table), `docs/TESTING.md`, POT.

## Open questions

1. A new Tools tab, or a section on General? Recommendation: a Tools tab, which can also host Reset All later.

## Decision log

- 2026-11-26: Spec written. User preferences excluded from export.
