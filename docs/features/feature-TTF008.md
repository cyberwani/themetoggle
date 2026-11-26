# TTF008 — Admin-assigned, locked themes

| Field | Value |
| --- | --- |
| Feature ID | TTF008 |
| Status | Idea |
| Group | Medium |
| Effort | Medium |
| Risk | Low |
| Build order | — |
| Depends on | — |
| Related | TTF009 (time limits use the same assignment data), TTF001, TTF017 |
| Released in | — |

## Summary

An administrator sets a user's theme and can lock it, so the user can't change it through any of the plugin's paths: profile, floating button, preview "Use this theme", toolbar, REST.

## Current state, required state, gap

- **Current:** anyone who can `edit_user` the target can set their theme on `user-edit.php` (`User_Profile::save()`), and users can change their own at any time.
- **Required:** a lock flag set by admins, checked by every write path.
- **Gap:** a meta flag, a capability, and a check inside `save_preference()`.

## User stories and acceptance criteria

1. On another user's edit screen, an admin sees "Lock this theme (the user can't change it)".
2. When locked, the user's own profile shows the theme read-only, with "Set by an administrator". The floating reset button and the "Switch theme" sub-item of the ThemeToggle toolbar menu (TTF001) are hidden.
3. Any attempt by the user to change it (forged profile POST, `admin_post_tt_reset_theme`, `admin_post_tt_select_theme`, REST) is refused with 403 and changes nothing.
4. The admin can unlock or change it.
5. The Users column shows a 🔒 marker and "locked by {admin}".
6. Bulk reset and Reset All also clear locks, and say so in the confirmation.

## Design

### Data

- `tt_user_theme_lock` user meta: `{ "by": <admin user id>, "at": <UTC timestamp> }`. Present = locked.
- Uninstall deletes it.

### Capability

- Locking requires `edit_user` on the target **and** the capability from filter `themetoggle_lock_capability` (default `manage_options`).
- Editors who can edit users but aren't admins can't lock or unlock.

### Enforcement

- `Theme_Resolver::save_preference( $user, $stylesheet, $source )`: if the target has a lock and the current actor lacks the lock capability, return `false`. One place enforces it for every path.
- A locked theme must still be a permitted theme for the user (invariant 3). If the admin later removes it from the role, the user falls back to the default and the admin sees "Locked theme not permitted" in the Users column. A lock does not grant permission.

### Preview

Preview only changes one page view and never saves, so it doesn't break the lock. Still hide preview links for locked users: a locked theme usually means "this is what you should see".

### Files

- `includes/class-theme-resolver.php`
- `includes/class-user-profile.php` and `admin/views/profile-section.php`
- `includes/class-floating-button.php` (`get_mode()`)
- `includes/class-users-list.php`
- `admin/class-admin.php` (Reset All text)

## Security

| Threat | Prevention |
| --- | --- |
| A user unlocks themselves with a forged POST of the lock checkbox. | The lock field is read only when the actor has the lock capability; otherwise ignored. |
| A user changes theme through a path that was forgotten. | The check lives in `save_preference()`, which every path already uses. A test covers each path. |
| An editor removes an admin's lock. | Lock capability separate from `edit_user`. |
| A lock used to force a theme outside the role rules. | Validation still applies. |

## Caching and headers

None new.

## Data integrity, privacy and leakage

- The lock stores the admin's user ID. If that admin is deleted, show "an administrator" and keep the lock.
- On user deletion, WordPress deletes the user's meta (lock included).

## Performance

The lock meta is loaded with the user's meta cache: no extra query.

## Compatibility

No version-specific APIs.

## Architecture invariants

- 3: the lock is an extra condition on writes; it never replaces validation.
- 5: nonces stay as they are; the lock is checked after them.

## Tests

- Admin locks user A on `tt-child`. A's profile shows it read-only.
- A forges a profile POST, the reset button POST and the "Use this theme" POST: 403 or an error notice each time, meta unchanged.
- An editor with `edit_users` tries to unlock: lock unchanged.
- An admin unlocks: A can change again.
- `tt-child` removed from A's role: A sees the default theme; the Users column says "locked theme not permitted".

## Documentation to update

`README.md`, `readme.txt`, `docs/ARCHITECTURE.md` (security table, data), `docs/TESTING.md`, POT.

## Open questions

1. Should bulk actions include "Lock current theme" for many users? Recommendation: later, if asked for.

## Decision log

- 2026-11-26: Spec written. A lock never grants permission.
