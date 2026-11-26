# TTF003 — Usage stats

| Field | Value |
| --- | --- |
| Feature ID | TTF003 |
| Status | Idea |
| Group | Quick win |
| Effort | Small |
| Risk | Low |
| Build order | 3 |
| Depends on | — |
| Related | TTF002, TTF004 (`wp themetoggle stats`) |
| Released in | — |

## Summary

On the User Profiles tab: how many users have chosen each theme, and how many saved choices are not in effect. The second number is split by reason: theme missing, not permitted, user not eligible.

## Current state, required state, gap

- **Current:** `Admin::count_users_with_preference()` shows a single total, with a `WP_User_Query` on `tt_user_theme`.
- **Required:** counts per saved theme and per status.
- **Gap:** one grouped query for saved values, plus a status count that needs each user's roles.

## User stories and acceptance criteria

1. As an admin, I see a table: Theme, Users who chose it, of which in effect, not in effect. Each count links to Users → All Users filtered to those users.
2. A summary line: "N users have a saved theme; M of them currently see the default theme."
3. Themes that are no longer installed show as "Not installed: {slug}".
4. The numbers are at most one hour old, with a "Recalculate" button.

## Design

### Saved counts

One prepared query:

```php
$wpdb->get_results(
	$wpdb->prepare(
		"SELECT meta_value AS stylesheet, COUNT(*) AS users FROM {$wpdb->usermeta} WHERE meta_key = %s GROUP BY meta_value",
		Theme_Resolver::META_KEY
	)
);
```

### Effective counts

The status depends on each user's roles, so it cannot be done in SQL alone.

- Fetch users with the meta in batches of 500 (`WP_User_Query`, `fields => all`, paged).
- Run `Theme_Resolver::get_user_state()` for each user and count by status.
- Store the result in a transient `tt_stats` (1 hour). Clear it when `tt_settings` changes or the Recalculate button is used.
- On very large sites (over 20,000 users with the meta) stop counting after a time budget of about 5 seconds and say "estimated from the first N users". The alternative is to run the count in WP-Cron.

### Filtering the Users list

- Link to `users.php?tt_theme_filter={slug}`. `Users_List` adds a `pre_get_users` meta query for that value.
- Read the value with `sanitize_text_field( wp_unslash() )`, and accept only an installed slug or a slug present in the saved-count result.

### Files

`admin/class-admin.php` (new `Stats` helper or private methods), `admin/views/settings-users.php`, `includes/class-users-list.php` (filter).

## Security

| Threat | Prevention |
| --- | --- |
| SQL injection. | `$wpdb->prepare()` for the meta key; the filter value goes through a meta query, never into SQL text. |
| Data exposure to non-admins. | The tab is `manage_options` only, like the rest of the settings page. |
| CSRF on Recalculate (a cheap denial of service). | Nonce, `admin_post_` handler, capability check. |
| XSS through stored slugs (meta values may come from other code). | Escape every slug and name on output. Don't treat a meta value as a theme without `Theme_Manager::get_theme()`. |

## Caching and headers

Admin page only. The transient is server-side and holds only counts.

## Data integrity, privacy and leakage

- Aggregates only; no user names on the tab. The per-theme user list is the core Users screen, with its own capability checks.
- Read-only: invalid preferences are counted, never deleted (invariant 4).

## Performance

- The grouped query uses the `meta_key` index on `usermeta`.
- The status count is the expensive part, hence batches, the time budget and the transient.

## Compatibility

On multisite, `usermeta` is network-wide; the counts would include users of other sites until TTF014 makes the key per-site. Show a note on multisite.

## Architecture invariants

- 3 and 4: status through `get_user_state()`; nothing deleted.
- 8: `wp_get_themes()` is fine here (admin screen).

## Tests

- Three users on two themes, one with a non-permitted theme: the table shows the right counts and "not in effect: 1".
- Recalculate without a nonce: refused.
- The filter link shows only the matching users; a forged filter value (`' OR 1=1`) shows no users and no error.
- A non-admin opening the settings page: 403 (existing check).

## Documentation to update

`README.md` (Features), `readme.txt`, `docs/TESTING.md`, POT.

## Open questions

1. Is the Users-list filter needed, or are the counts enough? Recommendation: build the counts first; the filter is a small follow-up.

## Decision log

- 2026-11-26: Spec written.
