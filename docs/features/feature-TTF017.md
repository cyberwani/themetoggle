# TTF017 — REST API for preferences

| Field | Value |
| --- | --- |
| Feature ID | TTF017 |
| Status | Idea |
| Group | Larger |
| Effort | Medium |
| Risk | Medium |
| Build order | — (low priority unless there is a consumer) |
| Depends on | — |
| Related | TTF006 (hooks with source `rest`), TTF008 (locks) |
| Released in | — |

## Summary

REST endpoints to read and change theme preferences, for headless set-ups, mobile admin apps and integrations. They use the same validation and capability checks as the profile screen.

## Current state, required state, gap

- **Current:** no REST routes. `Request_Context` classifies REST requests by their referer, so external REST calls (no referer) are context `none` and never switch theme.
- **Required:** a small, versioned API.
- **Gap:** route registration, schemas and tests.

## Endpoints (`themetoggle/v1`)

| Method and route | Permission | Result |
| --- | --- | --- |
| `GET /me` | logged in | Saved preference, status, effective theme, selectable themes (slug and name). |
| `PUT /me` `{ "theme": "<slug>" }` | logged in and eligible | Saves through `save_preference()`; 400 if not allowed; 403 if locked (TTF008). |
| `DELETE /me` | logged in | Clears the preference. |
| `GET /users/<id>` | `edit_user` on `<id>` | The same as `/me`, for that user. |
| `PUT /users/<id>` and `DELETE /users/<id>` | `edit_user` on `<id>` | Saves or clears, validated for **that** user's roles. |

No endpoint lists all users' preferences: use core `/wp/v2/users` with the right capability, or TTF003 stats.

## Design

- `includes/class-rest-controller.php` extending `WP_REST_Controller`, registered on `rest_api_init`.
- Every route has a `permission_callback` (never `__return_true`), `args` with `type`, `required` and a `validate_callback` / `sanitize_callback` (`sanitize_text_field`), and a response schema (`get_item_schema()`).
- `<id>` is validated with `absint()`; a missing user returns 404 **only** to callers who could see it, otherwise 403. That avoids revealing which user IDs exist.
- Errors are `WP_Error` with codes `themetoggle_not_allowed`, `themetoggle_locked`, `themetoggle_not_eligible`.
- Authentication is WordPress core's: cookie and `X-WP-Nonce` for browser code, application passwords for external apps. Nothing custom.

## Security

| Threat | Prevention |
| --- | --- |
| Broken access control: one user changes another's theme. | `edit_user` check per target, the same as `user-edit.php`. |
| Mass assignment: extra fields in the body. | Only `theme` is read; the schema sets `additionalProperties: false`. |
| CSRF from browsers. | Core's cookie authentication requires `X-WP-Nonce`; without it the request is treated as logged out. |
| User enumeration through 404 vs 403. | Same 403 for "not allowed" and "no such user" unless the caller has `list_users`. |
| Theme switching on API requests themselves. | External calls are context `none`; the API never changes the theme for the API response. |

## Caching and headers

- Core sends `Cache-Control: no-cache, must-revalidate, max-age=0` for authenticated REST responses. Check that this holds, and add `nocache_headers()` in the controller if not.
- Responses contain user-specific data; never cache them at a CDN.

## Data integrity, privacy and leakage

Returns only slugs, theme names and statuses: no emails or names of other users.

## Performance

One user state computation per request.

## Compatibility

REST API (WordPress 4.7+). Application passwords: WordPress 5.6+, which is within the 5.9 minimum.

## Architecture invariants

- 3: writes through `save_preference()` for the target user.
- 5: core handles nonces for cookie auth; capabilities per route.

## Tests

The e2e suite already gets a REST nonce from `admin-ajax.php?action=rest-nonce`:

- `GET /me` as an eligible user: correct state.
- `PUT /me` with a permitted theme: 200, meta set; with a non-permitted one: 400, unchanged.
- `PUT /users/<other>` as a subscriber: 403.
- `PUT /me` without a nonce (cookie only): 401 or 403.
- An extra field in the body: ignored or 400.
- An unknown user ID as a subscriber: 403, not 404.

## Documentation to update

`README.md` (REST section), `readme.txt`, `docs/ARCHITECTURE.md` (security table).

## Open questions

1. Is there a consumer (app or integration)? Without one, keep this as Idea.

## Decision log

- 2026-11-26: Spec written.
