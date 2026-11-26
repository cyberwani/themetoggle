# TTF015 — Settings for non-active classic themes

| Field | Value |
| --- | --- |
| Feature ID | TTF015 |
| Status | Idea |
| Group | Larger |
| Effort | Large |
| Risk | High |
| Build order | — |
| Depends on | — |
| Related | — |
| Released in | — |

## Summary

Let admins set menu locations, the custom logo and simple theme options for a selectable **classic** theme without making it the site's active theme. Block themes are out of scope.

## Current state, required state, gap

- **Current:**
    - `Data_Guard` serves a selected theme its own `theme_mods_{stylesheet}` row, and redirects theme-mod writes there.
    - A theme without its own row inherits the default theme's mods read-only (`themetoggle_inherit_default_theme_mods`).
    - But there is no screen to edit a non-active theme's mods: the Customizer, `nav-menus.php` and `widgets.php` are excluded from switching (`Request_Context`), and the Customizer's "Live Preview" of another theme **activates** it on publish.
- **Required:** a safe way to write `theme_mods_{stylesheet}` for a theme that isn't active.
- **Gap:** an admin screen that knows the theme's registered menu locations and supports, which requires loading that theme's code.

## The hard part

A theme's menu locations (`register_nav_menus()`) and supports (`add_theme_support( 'custom-logo' )`) are only known after its `functions.php` runs. In a normal admin request, only the active theme's code runs. Options:

| Option | How | Verdict |
| --- | --- | --- |
| A. Customizer with a "save without activating" mode | Override `WP_Customize_Manager` publishing. | Rejected: deep core internals; publishing a theme preview calls `switch_theme()`; very fragile. |
| B. Write the option directly | A form writing `theme_mods_{stylesheet}` without loading the theme. | Rejected: locations and supports unknown; can't validate. |
| C. Plugin screen rendered with the target theme applied | A ThemeToggle admin page whose request context is `admin` with the target theme forced for that request only, so its `functions.php` runs and its locations are registered. Writes go through `set_theme_mod()`, which `Data_Guard` redirects to the target's row. | **Recommended.** Reuses the existing switching and guard. |

## User stories and acceptance criteria

1. On the Role-Based Themes tab, each selectable classic theme has "Configure" (admins only).
2. The screen shows the theme's registered menu locations, each with a select of existing menus, and a logo picker if the theme supports `custom-logo`.
3. Saving updates only `theme_mods_{that stylesheet}`. The active theme's mods, the `stylesheet` and `template` options and `sidebars_widgets` are unchanged.
4. Users on that theme see the menus and logo.
5. Block themes show "Edit this theme in the Site Editor after activating it", with no Configure link.

## Design

### Request

- `themes.php?page=themetoggle-configure&theme={stylesheet}&_wpnonce=…`.
- The settings page lives under `themes.php`, which `Request_Context` excludes. This page must be the one exception, classified as `admin` with the target forced. Add an explicit check in `Request_Context` for this page slug **plus** a valid nonce **plus** `manage_options`, so a bare URL can't force a theme in admin.
- The theme is supplied to `Theme_Switcher` through a narrow internal path, not the public `themetoggle_applied_theme` filter. It gets the same validation (installed, `errors()` empty, not the active theme, classic: `! $theme->is_block_theme()`).

### Saving

- POST to the same page; nonce `tt_configure_{stylesheet}`; capabilities `manage_options` and `edit_theme_options`.
- Menu locations: each location must be in `get_registered_nav_menus()` for this request; each menu ID must be a `nav_menu` term (`wp_get_nav_menu_object()`). Then `set_theme_mod( 'nav_menu_locations', $clean )`.
- Logo: an attachment ID that is an image (`wp_attachment_is_image()`), saved with `set_theme_mod( 'custom_logo', $id )`.
- `Data_Guard` redirects the writes to `theme_mods_{stylesheet}`. Add a test that the default theme's row is unchanged.

### Theme code runs in admin

Loading a theme's `functions.php` in admin is what happens when the theme is active, so it is not a new class of risk. But a theme may run activation-style code (creating pages or options) on first load. Show a warning: "This loads the theme's code once."

## Security

| Threat | Prevention |
| --- | --- |
| A URL forces a theme in wp-admin. | Page slug, nonce and `manage_options` all required for the exception; `none` stays `none` everywhere else. |
| A broken or block theme triggers `validate_current_theme()` and `switch_theme()`. | Same validation as invariant 11; this page isn't `themes.php` itself (`validate_current_theme()` runs on the Themes list screen). Test with the `tt-orphan` fixture. |
| Writes reach the active theme's mods or widgets. | `Data_Guard` active; e2e check that `theme_mods_{default}` and `sidebars_widgets` are byte-identical before and after. |
| Arbitrary menu or attachment IDs. | Validated as above. |

## Caching and headers

Admin only; `nocache_headers()`.

## Data integrity, privacy and leakage

- Writes only to `theme_mods_{stylesheet}`. Show the previous values and offer "Reset to inherited", which deletes the row so the theme inherits again.

## Performance

Admin only, on demand.

## Compatibility

- Classic themes only. Themes that store settings in their own options (not theme mods) are out of scope; their own options pages normally only work while active.
- WordPress 5.9+ for `WP_Theme::is_block_theme()`. Don't use `wp_is_block_theme()` here: it checks the theme that is loaded for the request, not the one being configured.

## Architecture invariants

- 1: never `switch_theme()`; the forced theme is per request only.
- 6: `Data_Guard` active.
- 11: same validation; this is the only `admin` exception under `themes.php`, guarded by a nonce.

## Tests

- Configure `tt-child`: set a menu location; `theme_mods_tt-child` has it; `theme_mods_{default}`, `stylesheet`, `template` and `sidebars_widgets` unchanged.
- The URL without a nonce, or as an editor: the default theme loads in admin; 403 on save.
- `tt-orphan`: no Configure link; a forced URL is refused; the site theme unchanged.
- A forged location name or a non-menu term ID: dropped.

## Documentation to update

`README.md` (Data protection; Known limitations), `readme.txt` FAQ, `docs/ARCHITECTURE.md` (the new context exception), `docs/TODO.md` (remove the old item), POT.

## Open questions

1. Is this needed often enough to justify the risk? Workaround today: activate the theme briefly on staging, configure it, export the mods.

## Decision log

- 2026-11-26: Spec written. Option C chosen; Customizer and direct-write approaches rejected.
