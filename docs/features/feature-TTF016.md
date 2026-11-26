# TTF016 — Visitor (logged-out) switching

| Field | Value |
| --- | --- |
| Feature ID | TTF016 |
| Status | Idea |
| Group | Larger |
| Effort | Large |
| Risk | High |
| Build order | — (only if there is a clear need) |
| Depends on | TTF010 |
| Related | TTF018 (a controlled, narrow version for sharing previews) |
| Released in | — |

## Summary

A theme picker for visitors who aren't logged in, with their choice stored in a cookie. Recommendation: **don't build this unless there is a clear need**; TTF018 covers the common "let someone without an account see the site in theme X" case more safely.

## Current state, required state, gap

- **Current:** `Theme_Switcher::determine_theme()` returns `null` for logged-out users. The plugin is built for logged-in, role-based switching.
- **Required:** visitors choose from an admin-defined list.
- **Gap:**
    - visitor settings and a picker widget
    - cookie handling
    - a new resolution branch
    - cache bypass for every visitor with the cookie

## Why the risk is high

1. **Page caching for visitors.** Visitor pages are the ones every cache stores. Visitors with the cookie must bypass the page cache and CDN, or one visitor's theme is served to everyone (cache poisoning). Many CDNs don't honour `Vary: Cookie`. Hosting rules must be configured per site.
2. **Load:** every visitor on another theme is an uncached request, which can overload a site that depends on caching.
3. **SEO:** crawlers see the default theme; switching must never be reachable through crawlable links that change content, or duplicate pages appear.
4. **Scope:** it moves the plugin away from its purpose.

## User stories and acceptance criteria

1. The admin enables visitor switching, picks the themes visitors may choose, and places a "Theme" picker (block or shortcode).
2. A visitor picks a theme; every page shows it until they reset it or the cookie expires (default 30 days).
3. Logged-in users keep the existing rules; the visitor cookie is ignored for them.

## Design

- Settings:
    - `visitor_enabled` (default false)
    - `visitor_themes` (installed themes, validated like `role_themes`)
    - `visitor_cookie_days` (1–365)
- Picker: a form POST to `admin-post.php?action=tt_visitor_theme` (`admin_post_nopriv_` handler, the first `nopriv` handler in the plugin). Not a GET link, so crawlers and prefetchers don't switch themes.
- Cookie `wp-themetoggle-visitor` (the `wp-` prefix matches common "bypass cache" cookie patterns; verify per cache):
    - value: stylesheet + HMAC with `wp_salt( 'nonce' )`
    - flags: `HttpOnly`, `Secure`, `SameSite=Lax`, `COOKIEPATH`
- Resolution: in `determine_theme()`, when the user isn't logged in and the context is `frontend`, read the cookie, verify the HMAC, and require the slug in `visitor_themes` plus the usual validity checks.
- Headers: everything from TTF010, plus `Vary: Cookie`.

## Security

| Threat | Prevention |
| --- | --- |
| Cache poisoning: one visitor's theme cached for all. | TTF010 headers; documented host rules to bypass the cache on the cookie; a Site Health warning if a page cache is active. |
| CSRF: other sites change a visitor's theme. | Low impact (cosmetic). A nonce isn't meaningful for logged-out users (they all share nonce user 0), so use a same-origin check of the `Origin` / `Referer` header on the POST, or a double-submit cookie. |
| Cookie tampering to pick a non-allowed theme. | HMAC and the whitelist check on every request. |
| A broken theme reachable by anyone. | Same validation as invariant 11. |
| Open redirect after switching. | `Redirect::get_target()`. |

## Caching and headers

The central risk; see above and TTF010. Without host-level cache bypass on the cookie, don't enable it.

## Data integrity, privacy and leakage

- The cookie is a functional preference set by the visitor's own action, usually exempt from consent under EU ePrivacy rules as "strictly necessary" for a requested feature. This is not legal advice; document it for site owners to check.
- `Data_Guard` applies to visitor requests too.

## Performance

Every visitor on a non-default theme is uncached: measure before enabling on busy sites.

## Compatibility

Hosts with a forced edge cache (some managed hosts) may not allow cookie bypass at all; the feature must be off there.

## Architecture invariants

- 7 must hold for anonymous traffic, which is much harder than for logged-in users.
- 11: same validation.

## Tests

- A visitor POSTs a choice: the cookie is set; the next page uses the theme; private headers present.
- Tampered cookie or non-allowed slug: default theme.
- A GET to the handler: no change.
- A logged-in user with a visitor cookie: their normal rules apply.
- Feature disabled: the cookie is ignored.

## Documentation to update

`README.md` (Caching section, host rules), `readme.txt`, `docs/ARCHITECTURE.md` (the first `nopriv` handler in the security table).

## Open questions

1. Is there a real use case beyond sharing previews? If not, mark this Rejected in favour of TTF018.

## Decision log

- 2026-11-26: Spec written. Recommended not to build unless needed.
