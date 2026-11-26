# TTF006 — Change hooks

| Field | Value |
| --- | --- |
| Feature ID | TTF006 |
| Status | Idea |
| Group | Quick win |
| Effort | Small |
| Risk | Low |
| Build order | — |
| Depends on | — |
| Related | TTF011 (audit log is the first consumer), TTF004, TTF005, TTF017 |
| Released in | — |

## Summary

Public actions that fire when a user's theme preference changes and when settings are saved, so other plugins and a later audit log can react.

## Current state, required state, gap

- **Current:** public hooks are filters for resolution (`themetoggle_applied_theme`, `themetoggle_request_context`, `themetoggle_excluded_admin_pages`, `themetoggle_excluded_ajax_actions`, `themetoggle_inherit_default_theme_mods`) and the `themetoggle_loaded` action. Nothing fires on changes.
- **Required:** actions with the old value, the new value, the actor and the source.
- **Gap:** fire actions from the plugin's own write paths.

## User stories and acceptance criteria

1. `do_action( 'themetoggle_preference_changed', $user_id, $old, $new, $context )` fires after a preference is saved or cleared, and only when the value really changed.
2. `do_action( 'themetoggle_preferences_reset', $user_ids, $context )` fires once for a bulk reset, with the affected IDs.
3. `do_action( 'themetoggle_preferences_reset_all', $count, $context )` fires once for Reset All.
4. `do_action( 'themetoggle_settings_saved', $new, $old, $context )` fires after `tt_settings` changes.
5. `$context` is an array: `source` (`profile`, `user_edit`, `preview`, `button`, `admin_bar`, `bulk`, `reset_all`, `settings`, `import`, `cli`, `rest`) and `actor_id` (0 for CLI or system).

## Design

- `Theme_Resolver::save_preference( WP_User $user, $stylesheet, $source = 'unknown' )`. Read the old value, write, then fire. The extra parameter is optional, so existing callers keep working. Update every caller to pass its source.
- `actor_id` = `get_current_user_id()`.
- Bulk reset (`Users_List::handle_bulk_action()`) and Reset All (`Admin::handle_reset_all()`) fire their own single action. Don't fire `themetoggle_preference_changed` thousands of times for Reset All.
- Settings: hook `update_option_tt_settings` (old and new values are passed by core) and `add_option_tt_settings`. Fire only if `$old !== $new`.
- Each action gets a docblock at the `do_action()` call, as the skill's "Add a public hook" recipe requires.

### What is not covered

Code that writes `tt_user_theme` with `update_user_meta()` directly bypasses the plugin. Catching that would need the `update_user_metadata` filter (for the old value), which also runs for the plugin's own writes and would double-fire. Decision: document that only ThemeToggle's own paths fire the hooks.

## Security

| Threat | Prevention |
| --- | --- |
| Listeners trust request data. | The hooks pass validated values only (the slug already accepted by `save_preference()`), never `$_POST`. |
| A listener throws and breaks the save. | Fire after the write, so the data is already consistent. Don't catch exceptions; behave like core hooks. |
| Recursion: a listener calls `save_preference()`. | Document it; `save_preference()` fires only on a real change, so a listener writing the same value ends the loop. |

## Caching and headers

None.

## Data integrity, privacy and leakage

User IDs and theme slugs only. Listeners decide what they store; TTF011 handles retention.

## Performance

Actions with no listeners cost nothing. Nothing fires on the resolution path (per request), only on writes.

## Compatibility

Adding an optional parameter keeps backward compatibility for code calling `save_preference()` directly.

## Architecture invariants

- 3: writes still go through `save_preference()`.
- The hooks must never fire from `determine_theme()` or `compute_state()`.

## Tests

The e2e mu-plugin (`tests/e2e/fixtures/mu-plugins/tt-e2e-hooks.php`) gets a listener that appends each call to an option. Then:

- Profile save: one `preference_changed` with source `profile`, old and new correct.
- Saving the same value again: no call.
- Bulk reset of 2 users: one `preferences_reset` with 2 IDs.
- Reset All: one `preferences_reset_all` with the count.
- Settings tab save: one `settings_saved`; saving unchanged settings: no call.

## Documentation to update

`README.md` and `readme.txt` hooks tables; `docs/ARCHITECTURE.md` ("Extending").

## Open questions

1. Should Reset All also pass the user IDs? On large sites that array could be huge. Recommendation: only the count; TTF011 can log the count.

## Decision log

- 2026-11-26: Spec written. External meta writes are out of scope.
