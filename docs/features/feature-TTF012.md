# TTF012 — Rules beyond roles

| Field | Value |
| --- | --- |
| Feature ID | TTF012 |
| Status | Idea |
| Group | Medium |
| Effort | Medium |
| Risk | Medium |
| Build order | — |
| Depends on | — |
| Related | TTF007 (role defaults would become rule defaults) |
| Released in | — |

## Summary

Permit themes by capability, by named users, or by a user meta value (for example a membership level), not only by role. Start with a developer filter, which is small and safe, and add a rules UI only if needed.

## Current state, required state, gap

- **Current:** eligibility is "the user has an allowed role" (`Role_Manager::get_eligible_roles()` / `is_user_eligible()`). Permitted themes are the union of the themes of those roles (`get_permitted_themes()`).
- **Required:** other ways to decide eligibility and permitted themes.
- **Gap:** an extension point, and optionally a rules model with UI and a migration.

## Recommendation: two steps

### Step 1: filters (small)

- `themetoggle_user_is_eligible( bool $eligible, WP_User $user )`
- `themetoggle_user_permitted_themes( string[] $stylesheets, WP_User $user )`

Apply them in `Role_Manager`. Validate the filter output: an array of strings; keep only installed themes (`Theme_Manager::is_installed()`), the same way invariant 11 treats `themetoggle_applied_theme`.

This covers membership plugins with a few lines of site code, and keeps the admin UI simple.

### Step 2: rules UI (only if asked for)

- New setting `rules`: a list of `{ type, value, themes[] }`, with `type` one of `role`, `capability`, `user`, `meta`.
- A user is eligible if any rule matches; permitted themes are the union, as today.
- Migration: on upgrade, turn `allowed_roles` and `role_themes` into role rules, and keep reading the old keys until the next major version. Store a `tt_settings_schema` version.

## User stories and acceptance criteria

Step 1:

1. A site developer adds a filter that permits `tt-child` for users with meta `membership = gold`, and those users get it in their profile and on the front end.
2. A filter returning an uninstalled or invalid theme has no effect.

Step 2:

1. An admin adds a rule "capability `edit_posts` → themes A, B" in the UI.
2. The Users column and TTF002 explain which rule gave the theme.

## Design

### Matching (step 2)

| Type | Match | Notes |
| --- | --- | --- |
| `role` | `in_array( $role, $user->roles, true )` | As today. |
| `capability` | `user_can( $user, $cap )` | Use primitive capabilities; meta capabilities such as `edit_post` need an object and are refused. |
| `user` | `in_array( $user->ID, $ids, true )` | IDs stored with `absint()`; the UI shows names. |
| `meta` | `get_user_meta( $user->ID, $key, true ) === $value` | Exact string comparison. No regex, no `eval`, no serialized values. |

### Sanitising

- Capability names are matched against the known list from all roles (`wp_roles()`), or validated as a string ≤ 100 characters with `sanitize_key()`-like rules.
- Meta keys: string, ≤ 255 characters, not starting with the database prefix plus `capabilities` (to discourage rules on core role storage, which `role` already covers).
- Theme lists as today.

## Security

| Threat | Prevention |
| --- | --- |
| A filter or rule makes a broken theme reach the site. | Filter output and rule themes validated like preferences; context `none` never switched. |
| Rule input used in code or queries. | Exact comparisons only; values never reach SQL text or `eval`. |
| Users set their own meta to match a rule (privilege escalation). | Document that meta rules are only safe for meta that users can't edit themselves. Most membership plugins store levels in protected meta; profile fields are user-editable. Show a warning in the UI. |
| Huge rule lists slow down every request. | Limit to 50 rules. |

## Caching and headers

More users may get personal themes; see TTF010.

## Data integrity, privacy and leakage

The migration must be reversible: keep the old keys until the new format is confirmed. Exports (TTF005) include the schema version.

## Performance

- Rules are evaluated per request for the current user only, and cached in `Theme_Resolver`.
- `user_can()` and user meta reads use caches already loaded.

## Compatibility

Membership plugins (Paid Memberships Pro, MemberPress, WooCommerce Memberships) each store levels differently. The filter approach avoids building an integration for each.

## Architecture invariants

- 3: whatever decides eligibility, it's evaluated per request, and writes validate against it.
- 11: filter output is validated.

## Tests

Step 1, with the e2e mu-plugin:

- A filter permits `tt-child` for one user: their profile lists it; the front end uses it after saving.
- A filter returns `'../../etc'` and an uninstalled slug: ignored.
- A filter makes a non-role user eligible: the profile section appears.

Step 2:

- Each rule type matches and doesn't match.
- The migration turns roles into rules with the same results.

## Documentation to update

`README.md` and `readme.txt` hooks tables, `docs/ARCHITECTURE.md` (resolution order).

## Open questions

1. Is there a real need beyond roles on your sites? If not, step 1 is enough.

## Decision log

- 2026-11-26: Spec written. Filters first, UI later.
