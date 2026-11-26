# TTF010 — Cache-plugin compatibility

| Field | Value |
| --- | --- |
| Feature ID | TTF010 |
| Status | Idea |
| Group | Medium |
| Effort | Medium |
| Risk | Medium |
| Build order | 4 (before TTF016 and TTF018) |
| Depends on | — |
| Related | TTF016, TTF018 (both need it first), TTF007 |
| Released in | — |

## Summary

Make sure a page rendered with one user's theme is never stored by a page cache, proxy or CDN and served to someone else. Add explicit signals for popular caches, a Site Health check that spots risky setups, and documentation for server caches.

## Current state, required state, gap

- **Current:** `Theme_Switcher::send_private_cache_headers()` sends `nocache_headers()`, `Vary: Cookie` and defines `DONOTCACHEPAGE` when a theme is applied. Most caches also skip logged-in users by default.
- **Required:** the same guarantee on setups that cache logged-in pages, ignore `DONOTCACHEPAGE`, or ignore `Vary`.
- **Gap:**
    - cache-specific signals
    - detection of risky settings
    - CDN headers
    - documentation
    - tests against real cache plugins

## The risk

Two failures are possible:

1. **Leak:** a user's personal theme page is cached and served to visitors or to other users.
2. **Wrong theme for the user:** a cached default-theme page is served to a user who chose another theme.

Failure 1 is the serious one. Both get worse with TTF007 (more users on other themes), TTF016 and TTF018 (anonymous visitors on other themes).

## User stories and acceptance criteria

1. With any supported cache plugin active and "cache logged-in users" on, a personal-theme page is never served to another user.
2. Site Health → Status shows a warning when an active cache plugin is set to cache logged-in users, with what to change.
3. Every response with a personal theme has headers that common CDNs respect as "do not store".
4. The README has a "Caching" section with rules for Nginx FastCGI cache, Varnish and Cloudflare.

## Design

### Headers, when a theme is applied

| Header | Why |
| --- | --- |
| `Cache-Control: private, no-cache, no-store, must-revalidate, max-age=0` | Extends `nocache_headers()`, which doesn't send `private` and `no-store` on every WordPress version. `no-store` also keeps the page out of the browser's back/forward cache. |
| `Vary: Cookie` | Already sent; keep it. Many CDNs ignore it, so it is not enough on its own. |
| `CDN-Cache-Control: no-store` | Targeted CDN header (RFC 9213), respected by Cloudflare and others. |
| `X-Accel-Expires: 0` | Nginx FastCGI and proxy caches honour it over `Cache-Control`. |
| `X-LiteSpeed-Cache-Control: no-cache` | LiteSpeed server cache, even without its plugin. |

Send them at `send_headers` (already used) and again at `template_redirect` for safety. Check `headers_sent()` first.

### Plugin integrations

Each is used only when that plugin is active, and wrapped in `function_exists()` / `has_action()` checks. **Verify each API against the current plugin docs while building; these names are from memory:**

| Plugin | Signal |
| --- | --- |
| WP Rocket | `DONOTCACHEPAGE` (honoured); also check its "cache for logged-in users" option. |
| W3 Total Cache | `DONOTCACHEPAGE` (honoured). |
| LiteSpeed Cache | `do_action( 'litespeed_control_set_nocache', 'themetoggle' )`. |
| WP Super Cache | `DONOTCACHEPAGE` (honoured); it doesn't cache logged-in users by default. |
| Cloudflare APO | It bypasses requests with `wp-*` / `wordpress_*` cookies by default; `CDN-Cache-Control` covers custom rules. |
| SG Optimizer, Breeze, Hummingbird, Kinsta, WP Engine | Check each one's API; most honour `DONOTCACHEPAGE` and `Cache-Control: private`. |

New class `includes/class-cache-compat.php`, called from `Theme_Switcher` when a theme is applied. The filter `themetoggle_cache_signals` lets sites add their own.

### Site Health

- `site_status_tests` → a direct test named "ThemeToggle: page cache and personal themes".
- It detects active cache plugins and, where readable, their "logged-in cache" option. It reads options only; nothing is changed.
- Result: good, recommended or critical, with a link to the README section.

### Static assets

Theme CSS and JS have per-theme URLs, so caching them is safe. Nothing to do.

### Service workers

PWA plugins can cache HTML in the browser. Document that HTML pages should be network-first for logged-in users.

## Security

| Threat | Prevention |
| --- | --- |
| Cross-user leak of a personal page, which can hold user-specific content such as the toolbar, names and nonces. | Layered "do not store" signals; Site Health warning; tests with real caches. |
| Spoofing: making the cache store a page by sending special headers. | All signals are response headers set by the server; nothing is read from the request. |

## Caching and headers

This whole feature is about caching. Keep the list of headers in one place (`Cache_Compat`) so TTF016 and TTF018 reuse it.

## Data integrity, privacy and leakage

Read-only checks of other plugins' options. No data stored.

## Performance

Only requests that already have a personal theme are affected; they were already uncached. No change for visitors on the default theme.

## Compatibility

- Some hosts strip or override `Cache-Control` at the edge. Document that their "bypass on cookie" rule is what really protects.
- The usual pattern is `wordpress_logged_in_*`.

## Architecture invariants

7: "Responses rendered with a user-specific theme are never publicly cacheable." This feature makes that true on more setups.

## Tests

- e2e (no cache plugin): every response with a personal theme has the full header set; a default-theme response for a logged-in user has none of the extra headers.
- Manual matrix, recorded in `docs/TESTING.md`: WP Rocket, W3TC, LiteSpeed Cache, WP Super Cache, each with logged-in caching on. Two users on different themes request the same URL alternately 10 times; each always gets their own theme.
- Site Health: with a stub plugin that sets a "cache logged-in" option, the test reports "recommended".

## Documentation to update

`README.md` (new "Caching" section), `readme.txt` (FAQ: "Does it work with caching plugins?"), `docs/ARCHITECTURE.md`, `docs/TESTING.md`.

## Open questions

1. Which cache plugins matter for your sites? The integration list should start with those.

## Decision log

- 2026-11-26: Spec written. Integration APIs marked "verify before building".
