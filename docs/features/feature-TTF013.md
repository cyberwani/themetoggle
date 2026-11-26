# TTF013 — Preview across pages

| Field | Value |
| --- | --- |
| Feature ID | TTF013 |
| Status | Idea |
| Group | Medium |
| Effort | Medium |
| Risk | Medium |
| Build order | — |
| Depends on | — |
| Related | TTF018 (preview for people without an account), TTF001 |
| Released in | — |

## Summary

Browse the whole site in a theme before choosing it. Today a preview lasts one page view: following a link returns to the saved theme. The new preview lasts until the user ends it, picks the theme, or a time limit passes (default 30 minutes).

## Current state, required state, gap

- **Current:** `Preview::get_previewed_theme()` accepts `?tt_preview={slug}&tt_preview_nonce={nonce}` only on a direct front-end page view (`Request_Context::is_direct_frontend()`). The nonce action is `tt_preview_{stylesheet}`, bound to the user. Links on the page don't carry the arguments, and `Redirect` strips them.
- **Required:** a preview state that survives navigation, front-end AJAX and REST calls from those pages, and ends cleanly.
- **Gap:** server-verifiable preview state carried by the browser, plus "End preview" and a time limit.

## User stories and acceptance criteria

1. I click "Preview" on a theme in my profile; the site opens in that theme with the preview bar: "Previewing Astra Child · 29 min left · Use this theme · End preview".
2. Every front-end page I open shows that theme until I end the preview.
3. Front-end AJAX and REST requests from those pages use the preview theme too, so "load more" and forms render consistently.
4. wp-admin never uses the preview theme.
5. "Use this theme" saves it (existing `admin_post_tt_select_theme`) and ends the preview. "End preview" returns to my saved theme.
6. The preview ends by itself after 30 minutes, and on logout.
7. The preview applies only to this browser session, not to my other devices.

## Design

### Options

| Option | How | Trade-off |
| --- | --- | --- |
| A. Signed cookie (recommended) | Cookie holding user ID, stylesheet, expiry and a hash of the login session token, signed with `hash_hmac( 'sha256', …, wp_salt( 'auth' ) )`. | No database writes; bound to one login session; needs careful signing. |
| B. User meta | `tt_preview` meta `{ stylesheet, until, session }`. | Simple, but a write per preview, and it would apply to every device unless bound to the session token. |

### Option A in detail

- **Start:** the existing preview URL is still the entry point. On a valid nonce (existing checks), set cookie `tt_preview_{COOKIEHASH}`:
    - value: `base64url( json )` + `.` + HMAC, with JSON `{ u: user_id, t: stylesheet, e: expiry, s: sha256( wp_get_session_token() ) }`
    - flags: `HttpOnly`, `Secure` when `is_ssl()`, `SameSite=Lax`, `path=COOKIEPATH`, expiry = `e`
    - then redirect to the same URL without the preview arguments, so the nonce leaves the address bar
- **Each request:** if the cookie is present and the context is `frontend` (direct, AJAX or REST with a front-end referer), verify the HMAC with `hash_equals()`, then check:
    1. `u` is the current user
    2. `s` matches the current session token
    3. `e` is in the future
    4. `can_user_select( $user, $t )`

    On any failure, ignore the cookie and expire it.
- **End:** `admin_post_tt_end_preview` with a nonce; expires the cookie; redirects with `Redirect::get_target()`.
- **Logout:** hook `clear_auth_cookie` to expire the cookie.
- `Theme_Switcher::determine_theme()` asks `Preview` as it does now; `Preview::get_previewed_theme()` checks the query arguments first, then the cookie.

### Settings

`preview_minutes` (5–240, default 30) on the General tab.

## Security

| Threat | Prevention |
| --- | --- |
| Forged cookie choosing any theme. | HMAC with the site's auth salt, `hash_equals()`, and `can_user_select()` on every request. |
| A stolen cookie used by someone else. | Bound to user ID and session token: useless without that user's login session, and it dies on logout. |
| Preview leaks into admin (Site Editor or Customizer would save against the wrong theme). | Applied in `frontend` context only; `none` contexts never switched (invariant 11). |
| CSRF on "End preview" or "Use this theme". | Nonces, as today. |
| Nonce left in history or the Referer header. | Redirect without the query arguments after the cookie is set. |

## Caching and headers

- Preview requests are already private through `send_private_cache_headers()` (the theme counts as applied). Keep `Vary: Cookie`; add TTF010's headers.
- The preview cookie is set only for logged-in users, whose pages page caches normally skip.

## Data integrity, privacy and leakage

- `Data_Guard` is active during previews (it activates whenever a theme is applied), so theme mods go to the previewed theme's row and widgets aren't rewritten.
- The cookie holds only a user ID, a slug, a time and a hash: no personal data beyond the ID.

## Performance

One HMAC per front-end request when the cookie is present. No database access.

## Compatibility

`wp_get_session_token()` exists since WordPress 4.0. SameSite on `setcookie()` needs the PHP 7.3+ options array form, which is fine for PHP 7.4.

## Architecture invariants

- 3: the preview is checked on every request like a preference.
- 6: `Data_Guard` active.
- 11: never in context `none`.

## Tests

- Start a preview, then request two other front-end pages with the cookie: both use the preview theme.
- A front-end AJAX request with a front-end referer: preview theme. An admin page: default or saved theme.
- Change one character of the cookie: ignored, saved theme used.
- Another user sends user A's cookie: ignored.
- A cookie with `e` in the past: ignored and expired.
- The theme is removed from the user's role: the preview ends.
- End preview without a nonce: refused; with a nonce: cookie expired.
- Logout: cookie cleared.

## Documentation to update

`README.md` (preview section; remove the one-page limitation from "Known limitations"), `readme.txt`, `docs/ARCHITECTURE.md` (security table, resolution order), `docs/TODO.md` (remove the old "Preview across page views" item), POT.

## Open questions

1. Should the preview also apply in wp-admin when `enable_admin` is on? Recommendation: no; previews are for looking at the site.

## Decision log

- 2026-11-26: Spec written. Option A (signed, session-bound cookie) recommended.
