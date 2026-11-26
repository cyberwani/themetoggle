# TTF007 — Default theme per role

| Field | Value |
| --- | --- |
| Feature ID | TTF007 |
| Status | Idea |
| Group | Medium |
| Effort | Medium |
| Risk | Medium |
| Build order | 2 |
| Depends on | — |
| Related | TTF008, TTF012 (rules would replace roles), TTF010 (more users on other themes) |
| Released in | — |

## Summary

An admin sets a default theme per role, such as "Editors see Theme X". A user gets their own valid choice first, then their role's default, then the site default.

## Current state, required state, gap

- **Current:** with no valid preference, a user gets the site's active theme. `Theme_Resolver::compute_state()` returns `STATUS_NONE` when the meta is empty. "Default Theme" in the profile deletes the meta (`save_preference( $user, '' )`).
- **Required:** a role default between the user's choice and the site default, and a way for a user to explicitly choose the site default even though their role has another default.
- **Gap:**
    - a `role_defaults` setting
    - a role priority for users with several roles
    - a new status
    - a stored value meaning "explicitly the site default"

## User stories and acceptance criteria

1. On Role-Based Themes, each allowed role has a "Default for this role" select. It offers "Site default" plus the themes ticked for that role.
2. A user with no saved choice gets their role default, if it is permitted, installed and error-free.
3. A user whose saved choice is invalid (`not_allowed`, `missing`) also falls back to the role default, not to the site default.
4. A user who explicitly picks "Site default" in the profile gets the site default, even though their role has another default.
5. A user with several eligible roles gets the default of the role with the highest priority. Roles with no default are skipped.
6. The Users column shows "Role default: Astra Child (Editor)".

## Design

### Settings

- `role_defaults`: `role => stylesheet`. Stored in `TAB_KEYS['roles']`.
- Sanitising:
    - the role must be in `allowed_roles`
    - the stylesheet must be installed and in that role's `role_themes`
    - otherwise drop the entry
- `role_priority`: an ordered list of role slugs (drag to sort, or up/down buttons, in the Role-Based Themes table). Default: the order of `wp_roles()->roles`. Roles missing from the list sort last, by slug.

### Stored value for "explicitly site default"

- Today, "Default Theme" deletes the meta, so "no choice" and "chose the default" are the same.
- New sentinel value `__tt_site_default__` in `tt_user_theme`, used when the user picks "Site default" while a role default would apply.
- `save_preference()` accepts the sentinel, and `compute_state()` returns `STATUS_IS_DEFAULT` for it.
- Delete the meta when no role default exists, which keeps today's data unchanged.
- Uninstall already deletes the meta key, sentinel included.

### Resolution (`compute_state()`)

1. Saved preference valid → `STATUS_ACTIVE` (today).
2. Saved preference is the sentinel → `STATUS_IS_DEFAULT`.
3. Otherwise compute the role default:
    1. eligible roles of the user, sorted by `role_priority`
    2. first role with a default that is installed, error-free, permitted and not the site's active theme
    3. `STATUS_ROLE_DEFAULT`, with `theme` and a new `role` key in the state
4. Otherwise the existing statuses.

Keep the reported status honest: a user with an invalid saved theme who gets a role default should show as "Saved theme Neve not permitted → role default Astra Child". Add a `fallback` key rather than hiding the original status.

`Theme_Switcher::determine_theme()` applies `STATUS_ROLE_DEFAULT` the same way as `STATUS_ACTIVE`.

### UI

- `admin/views/settings-role-themes.php`: a select per role row; `assets/js/admin.js` keeps the select options in step with the ticked themes.
- `admin/views/profile-section.php`: the "Default Theme" option is labelled "Role default (Astra Child)" when one applies, plus a separate "Site default".
- `Users_List::describe_state()`: the new texts.

## Security

| Threat | Prevention |
| --- | --- |
| A role default outside the role's permitted themes, for example through a forged settings POST. | Checked on save, and again during resolution (the default must be in `get_permitted_themes()`). |
| A broken theme as a role default switches the site theme on the Themes screen. | The same `exists()` / `errors()` checks as for preferences; context `none` is never switched (invariant 11). |
| Forged sentinel through the profile form. | The sentinel is just another allowed value; `save_preference()` accepts it only for eligible users. |

## Caching and headers

- Many more users may now be on a non-default theme, without choosing one. Each such request is private (`send_private_cache_headers()`), so the cache hit rate for logged-in users drops. Logged-in users are rarely cached anyway; TTF010 handles caches that do.
- No new headers.

## Data integrity, privacy and leakage

- No migration: existing preferences keep their meaning.
- Changing a role default changes what many users see at once. Show a notice when saving: "N users will see Theme X".

## Performance

The role default is computed in `compute_state()`, which is cached per user per request. No extra queries: roles and settings are loaded already.

## Compatibility

- Custom roles work, because the role list comes from `wp_roles()`.
- Membership plugins that add roles on the fly: the default applies as soon as the role is assigned.

## Architecture invariants

- 3: the role default is checked on every request, like a preference.
- 4: an invalid preference is kept (the user's saved Neve is still stored).
- 11: same validation.

## Tests

- Editor with no preference, role default `tt-child`: frontend uses `tt-child`; the Users column says "Role default".
- Editor picks "Site default": the meta holds the sentinel; frontend uses the site theme.
- An Editor and Author user, Author priority higher: gets the Author default.
- The role default theme is unticked for the role: the user falls back to the site default; the setting is dropped on the next save.
- Forged `role_defaults[editor]=<uninstalled theme>`: dropped.
- A role default that is a broken child theme (`tt-orphan` fixture): not applied, the Themes screen keeps the site theme.

## Documentation to update

`README.md` (Features, How theme switching works, resolution order), `readme.txt`, `docs/ARCHITECTURE.md` (resolution order, statuses, sentinel), `docs/TESTING.md`, POT.

## Open questions

1. Role priority as an ordered list (drag) or a number per role? Recommendation: an ordered list; numbers allow ties.
2. Should a role default also apply in wp-admin when `enable_admin` is on? Recommendation: yes, the same rules as a preference.

## Decision log

- 2026-11-26: Spec written. Sentinel value chosen for "explicitly site default".
