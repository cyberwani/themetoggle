# TTF014 — Multisite support

| Field | Value |
| --- | --- |
| Feature ID | TTF014 |
| Status | Idea |
| Group | Larger |
| Effort | Large |
| Risk | High |
| Build order | — |
| Depends on | — |
| Related | TTF003, TTF011 (tables per site) |
| Released in | — |

## Summary

Proper support for WordPress multisite:

- per-site preferences
- themes limited to those enabled for each site
- per-site settings, with optional network defaults
- network admin screens
- activation, upgrade and uninstall across all sites

## Current state, required state, gap

- **Current:** not supported or tested. Specific problems:
    - `tt_user_theme` is plain user meta, which is **network-wide**: a choice on site A applies on site B.
    - `Theme_Manager::is_installed()` doesn't check whether a theme is network-enabled or site-enabled.
    - `Installer::activate()` runs for the current site only; `uninstall.php` cleans the current site only.
    - `Theme_Resolver`'s per-request cache is keyed by user ID only, which is wrong after `switch_to_blog()`.
- **Required:** each site behaves like a single site, and network admins can set defaults.
- **Gap:** the list above, plus tests on a multisite install.

## User stories and acceptance criteria

1. A user's theme choice on site A doesn't affect site B.
2. Only themes allowed for a site (network-enabled or enabled for that site) can be permitted, chosen or applied there.
3. Each site admin configures their own roles and themes.
4. A super admin can set network defaults and optionally lock them (sites can't change them).
5. Network activation sets up every site, including sites created later. Uninstall removes data from all sites.

## Design

### Per-site preference

- Replace `get_user_meta( $id, 'tt_user_theme' )` with `get_user_option( 'tt_user_theme', $id )` and `update_user_option( $id, 'tt_user_theme', $value )`. These store `{$wpdb->prefix}tt_user_theme`: per site on multisite, and still per site on a single site.
- The key stays in one place (`Theme_Resolver::META_KEY` plus a helper). ARCHITECTURE.md already plans this.
- Migration from global meta:
    - a single site copies `tt_user_theme` to the prefixed key once
    - on multisite, leave old global values unused, and offer "import old choices to this site" on each site's User Profiles tab
    - store a migration flag per site

### Allowed themes

- `Theme_Manager::is_installed()` also requires `$theme->is_allowed( 'both' )`, core's check for "network-enabled or enabled for this site".
- Also apply it in `get_selectable_themes()` and in the final validation in `Theme_Switcher::get_applied_theme()`, so a theme disabled by the network admin stops at once.

### Settings

- Per-site `tt_settings` as now.
- Network option `tt_network_settings` (`get_site_option()`) holds defaults plus a "locked keys" list.
- Merge order: network locked keys → site settings → network defaults → built-in defaults.

### Admin

- Network admin page under Settings, capability `manage_network_options`.
- Site admins without `manage_network_options` see locked fields read-only.

### Lifecycle

- **Activation:** if network-wide, loop over sites with `get_sites( [ 'fields' => 'ids', 'number' => 100, 'offset' => … ] )`, `switch_to_blog()`, seed, restore.
- **New sites:** `wp_initialize_site` → seed.
- **Uninstall:** loop over sites as above, then delete the network option and all `{prefix}tt_user_theme` keys.

### Cache keys

`Theme_Resolver::$cache` keyed by `get_current_blog_id() . ':' . $user_id`; flush on `switch_blog`.

## Security

| Threat | Prevention |
| --- | --- |
| A theme disabled by the network admin used on a site. | Allowed-on-site check in validation and resolution. |
| A site admin changes network-locked settings with a forged POST. | `Settings::sanitize()` re-applies locked keys from the network option. |
| A super admin's choice on one site leaks to others. | Per-site key. |
| Users of other sites counted or reset. | Per-site key; Reset All and bulk reset affect only the current site's key. |

## Caching and headers

Unchanged per site. Network-wide page caches (some hosts) must vary by host, which they do by default.

## Data integrity, privacy and leakage

- The migration must not lose choices: copy, never move, until confirmed.
- Users removed from a site keep their per-site option; clean it up on `remove_user_from_blog`.

## Performance

- `get_user_option()` reads the same cached meta.
- Activation and uninstall loops are batched for networks with thousands of sites. WP-CLI (TTF004) can run them in steps.

## Compatibility

- Subdomain and subdirectory installs. `COOKIEPATH` differs per site in subdirectory installs, which matters for TTF013 and TTF018 cookies.
- Domain mapping: `wp_validate_redirect()` uses the site's own host.

## Architecture invariants

All of them, per site. Invariant 1 also on multisite: never call `switch_theme()`, even per site.

## Tests

- A new e2e variant on a multisite install (WP-CLI `core multisite-install`; the SQLite integration may not support multisite, so CI would use MySQL):
    - User on sites A and B: choice on A, default on B.
    - A theme enabled on A only: not offered or applied on B; a forged POST on B is refused.
    - Network lock on `enable_admin`: a site admin's forged save doesn't change it.
    - Network activation, then a new site: settings seeded.
    - Uninstall: no `%tt_user_theme` keys left in `usermeta`, no `tt_settings` on any site.

## Documentation to update

`README.md` (remove "not supported" from Known limitations), `readme.txt` (Network: true, if supported), `docs/ARCHITECTURE.md`, `docs/TESTING.md`, `docs/TODO.md` (remove the old item).

## Open questions

1. Which network features are needed: per-site only (smaller), or network defaults and locks too?
2. Migration on existing multisite installs: is "import old choices per site" acceptable?

## Decision log

- 2026-11-26: Spec written.
