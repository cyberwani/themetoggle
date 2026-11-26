# TTF009 — Time-limited assignments

| Field | Value |
| --- | --- |
| Feature ID | TTF009 |
| Status | Idea |
| Group | Medium |
| Effort | Medium |
| Risk | Low |
| Build order | — |
| Depends on | TTF008 (admin assignment UI and data) |
| Related | TTF013, TTF018 |
| Released in | — |

## Summary

"Use Theme X until 15 December", after which the user falls back automatically. Useful for theme trials and client reviews. The expiry is checked on every request, so no cron job is needed for it to work.

## Current state, required state, gap

- **Current:** a preference lasts until changed.
- **Required:** an optional end time per preference, set by the user (if allowed) or an admin.
- **Gap:** an expiry meta key, a new status, and the date field.

## User stories and acceptance criteria

1. In Personal Theme Settings, next to the theme, an optional "Until" date and time, in the site's timezone.
2. After that time, the user gets their fallback (role default from TTF007, or the site default). The Users column shows "Expired on 15 Dec: Astra Child".
3. The saved theme is kept (invariant 4); the user can renew it.
4. An admin setting decides who may set an end time: everyone eligible, or only admins on other users.
5. The profile shows the end time. There are no reminder emails or notices in this version.

## Design

### Data

- `tt_user_theme_until` user meta: UTC Unix timestamp (integer). No meta = no limit.
- Saving a new preference without an end time deletes it.

### Validation

- Parse with `DateTimeImmutable` in `wp_timezone()`, then store UTC.
- It must be in the future and at most 365 days ahead (filter `themetoggle_max_assignment_days`).
- Store with `absint()`.

### Resolution

`compute_state()`: if `until` exists and `time() >= until`, return `STATUS_EXPIRED` (new constant) with the preference kept, then fall through to the fallback chain from TTF007 if present.

### Cleanup (optional)

A daily cron job may delete expired `until` and preference pairs older than 30 days. It is optional and not needed for correctness. If built, unschedule it on deactivation.

## Security

| Threat | Prevention |
| --- | --- |
| A user extends an admin-set limit. | When TTF008's lock is set, the user can't change `until`. Without a lock it is their own choice. |
| Bad input (`until=99999999999`, strings). | Parse, range check, `absint()`. |

## Caching and headers

- An expired theme must not keep being served from a browser or proxy cache. The pages are already private and no-cache (`send_private_cache_headers()`).
- With a page cache that stores logged-in pages, TTF010 matters.

## Data integrity, privacy and leakage

All times are stored in UTC and shown in the site timezone, so DST changes don't shift them.

## Performance

One extra meta value, loaded with the user's meta cache.

## Compatibility

PHP 7.4 `DateTimeImmutable` and WordPress `wp_timezone()` (5.3+).

## Architecture invariants

- 3: expiry is checked on every request, like permission.
- 4: the expired preference is kept, not deleted.

## Tests

The e2e suite can't wait for a real expiry:

- Write `tt_user_theme_until = time() - 60` through WP-CLI: the front end uses the default; the Users column says "Expired".
- Set it to `time() + 3600`: theme applied.
- Profile save with a past date, a date 2 years ahead, or text: refused with an error notice.
- Saving a new theme without a date clears `until`.

## Documentation to update

`README.md`, `readme.txt`, `docs/ARCHITECTURE.md` (statuses), POT.

## Open questions

1. May users set their own end time, or only admins? Recommendation: an admin setting, default "admins only".

## Decision log

- 2026-11-26: Spec written. Expiry is checked per request; cron is cleanup only.
