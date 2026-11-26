# TTF002 — "Why this theme?" admin info

| Field | Value |
| --- | --- |
| Feature ID | TTF002 |
| Status | Idea |
| Group | Quick win |
| Effort | Small |
| Risk | Low |
| Build order | 3 |
| Depends on | TTF001 optional (shares its toolbar menu) |
| Related | TTF001 (its toolbar item sits in the ThemeToggle menu), TTF003 (stats) |
| Released in | — |

## Summary

For administrators, a toolbar item that says which theme the current request uses and why. Examples: "Personal theme: Astra Child (saved preference)", "Default theme: saved preference Neve is not permitted for your role", "Not switched: excluded screen (widgets.php)". Plus a "Check a user" tool on the User Profiles tab that shows what any user would get.

## Current state, required state, gap

- **Current:** the decision is made in `Theme_Switcher::determine_theme()` and `Theme_Resolver::compute_state()`. Only the result is kept (`Theme_Switcher::$applied`). The status constants exist (`STATUS_NOT_ALLOWED`, `STATUS_MISSING` …) and `Users_List::describe_state()` turns them into text for the Users column.
- **Required:** the reason for the decision, available to admins, without changing the decision.
- **Gap:** record the reason during resolution and display it.

## User stories and acceptance criteria

1. As an admin, I see "Why this theme?" as the last sub-item of the ThemeToggle toolbar menu (TTF001), on every front-end and admin page. Its own sub-item states the reason. Admins who aren't eligible to switch still get the ThemeToggle menu, with only this sub-item.
2. The reason covers every exit of the resolution order:
    - plugin disabled
    - no allowed roles
    - request context `none`, with the cause (excluded page, Customizer, AJAX with a foreign referer)
    - admin switching off
    - logged out
    - preview active
    - each `Theme_Resolver` status
    - a theme changed by the `themetoggle_applied_theme` filter
    - a filter theme rejected by the validation in `get_applied_theme()`
3. On User Profiles, "Check a user": pick a user, see their saved preference, status, effective theme and permitted themes. This uses no impersonation.
4. Non-admins never see any of this.

## Design

### Recording the reason

- Add a private `$decision` array to `Theme_Switcher`: `context`, `source` (`none`, `preview`, `preference`, `filter`), `status`, `reason_code`, `theme`.
- `determine_theme()` sets `reason_code` at each `return`. `get_applied_theme()` records `filter_changed` and `filter_rejected`.
- Public `get_decision()` returns a copy. It never triggers resolution itself: if resolution has not run, it returns `reason_code = 'not_resolved'`.
- `Request_Context` gets a `get_reason()` that names the cause of `none`.

### Display

- Registered as the `why` section of TTF001's toolbar registry (`$toolbar->add_section( 'why', 90, … )`), which places it last under the `themetoggle` parent. Node IDs `themetoggle-why` and `themetoggle-why-reason`. The section adds its nodes only when `current_user_can( 'manage_options' )` (filter `themetoggle_debug_capability`).
- If TTF002 is built before TTF001, it ships the `Toolbar` class with only this section; TTF001 then adds Switch theme to it.
- The reason text is a fixed translatable message per `reason_code`. Variable parts (theme names, page names) go through `esc_html()`.
- "Check a user": a `<select>` of users or a user ID field, form GET to the settings page with a nonce. It calls `Theme_Resolver::get_user_state()` and `Role_Manager::get_permitted_themes()` for that user, and shows the result read-only.

### Files

`includes/class-theme-switcher.php`, `includes/class-request-context.php`, `includes/class-toolbar.php` from TTF001 (section registration), `admin/views/settings-users.php`, `admin/class-admin.php`.

## Security

| Threat | Prevention |
| --- | --- |
| Information leakage to non-admins (roles, theme names, site configuration). | Capability check before building the node or running the user check. Nothing is printed in HTML comments, headers or the console. |
| Enumerating users through "Check a user". | `manage_options` only; nonce on the form. Admins can already list users. |
| XSS through theme names or screen names. | Fixed messages with escaped variables. |
| The diagnostic changes the decision. | `get_decision()` only reads cached state and has no side effects. |

## Caching and headers

- Shown only to logged-in admins, whose pages are not page-cached by standard setups.
- No new headers. Do **not** add an `X-ThemeToggle-Reason` response header: it would reach any cache and anyone viewing the response.

## Data integrity, privacy and leakage

Read-only feature: nothing is stored.

## Performance

- The decision is recorded during resolution that already happens: no extra queries.
- "Check a user" computes one user's state on demand.

## Compatibility

No version-specific APIs.

## Architecture invariants

- 2: the reason code must not call `get_stylesheet()` or `wp_get_theme()` without an argument.
- 10: if resolution has not run, report `not_resolved`; don't force it.

## Tests

- As an admin with a valid preference: the reason says "saved preference".
- After removing the theme from the role: the reason says "not permitted".
- On `widgets.php` with admin switching on: the reason says "excluded screen".
- A non-admin eligible user: no `themetoggle-why` node in the HTML.
- "Check a user" without a nonce: refused. As a non-admin: 403.
- With the e2e mu-plugin `tt_e2e_broken_theme` switch: the reason says "filter theme rejected".

## Documentation to update

`README.md` (Features), `readme.txt` (FAQ: "Why does a user see the default theme?"), `docs/TESTING.md`, POT.

## Open questions

1. Should the reason also appear in Site Health → Info for support requests? Recommendation: yes, as a later extra; Site Health is admin-only.

## Decision log

- 2026-11-26: Spec written. Response headers rejected for leaking.
- 2026-11-26: The toolbar item moved under TTF001's ThemeToggle menu as its "Why this theme?" sub-item, instead of a separate toolbar item.
