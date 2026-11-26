# TTF001 — Toolbar menu (theme switcher and feature links)

| Field | Value |
| --- | --- |
| Feature ID | TTF001 |
| Status | Idea |
| Group | Quick win |
| Effort | Small |
| Risk | Low |
| Build order | 1 |
| Depends on | — |
| Related | TTF002 ("Why this theme?" sub-item), TTF018 ("Share this page…" and "My preview links" sub-items), TTF008 (locked themes hide "Switch theme") |
| Released in | — |

## Summary

One "ThemeToggle" menu in the WordPress toolbar, with the current theme in its label ("ThemeToggle: Astra Child"). This feature builds the parent menu and its first sub-item, **Switch theme**: switch, reset or preview without visiting the profile page. Later features add their own sub-items to the same parent.

```text
ThemeToggle: Astra Child ▾
├── Switch theme ▸                 (this feature)
│   ├── ✓ Astra Child
│   ├── Neve
│   ├── Default theme
│   └── Preview ▸ (one sub-item per theme)
├── Share this page…               (TTF018)
├── My preview links (2 active)    (TTF018)
└── Why this theme?                (TTF002, admins only)
```

## Current state, required state, gap

- **Current:** a user changes theme in Users → Profile → Personal Theme Settings (`User_Profile::render()` / `save()`), resets with the floating button (`admin_post_tt_reset_theme` in `Floating_Button::handle_reset()`), or picks a previewed theme (`admin_post_tt_select_theme` in `Preview::handle_select()`). There is no toolbar presence.
- **Required:** a single toolbar menu that hosts all of ThemeToggle's quick actions, starting with theme switching, with the same validation as the profile.
- **Gap:**
    - a toolbar class that builds the parent and lets other features add sub-items
    - the Switch theme sub-menu
    - one new handler that saves through `Theme_Resolver::save_preference()`

## User stories and acceptance criteria

### The parent menu

1. As a logged-in user, I see "ThemeToggle: {current theme}" in the toolbar on the front end and in wp-admin, but only if at least one sub-item applies to me. Otherwise there is no menu at all.
2. The current theme in the label is the theme applied to this request (`Theme_Switcher::get_applied_theme()`), or the site's default theme when none is applied.
3. On excluded screens (`Request_Context::is_excluded_admin_page()`), the label says "ThemeToggle: {default theme} (not applied here)".

### Switch theme

1. As an eligible user, I see "Switch theme" listing "Default theme" and every theme in `Theme_Resolver::get_selectable_themes()`.
    - The saved choice is marked (✓ and `aria-current="true"`).
    - Users who aren't eligible, have no selectable themes, or are locked (TTF008) don't see this sub-item.
2. Clicking a theme saves it as my preference and reloads the same page with that theme.
3. Clicking "Default theme" clears my preference.
4. "Preview ▸" lists each selectable theme and opens the existing one-page preview (`Preview::get_preview_url()`) of the current front-end page. In wp-admin it previews the home page.
5. When admin switching is off (`enable_admin` false), "Switch theme" in wp-admin notes "Applies to the front end".

### Small screens

1. Below 782 px wide, WordPress hides most toolbar items. The parent gets the class that keeps it visible on small screens, so the menu also works on phones (check the current core class name while building).

## Design

### Settings

- `show_admin_bar_menu` (bool, default true), in `Settings::TAB_KEYS['general']`, with a checkbox "Show the ThemeToggle menu in the toolbar" on the General tab.
- It hides the whole menu, all sub-items included. Users who turned the toolbar off in their profile ("Show Toolbar when viewing site") see no menu on the front end; the profile page still works.

### Toolbar class

- New `includes/class-toolbar.php` (`Toolbar`), built in `Plugin::init()` on both front end and admin.
- Hook `admin_bar_menu` at priority 80, so the menu sits after the core items. Do nothing when `is_admin_bar_showing()` is false or the setting is off.
- **Sub-item registry:** other services add sub-items through an internal method, not a public hook:

    ```php
    $toolbar->add_section( 'switch', 10, array( $this, 'add_switch_nodes' ) );   // TTF001
    $toolbar->add_section( 'share', 20, array( $share, 'add_toolbar_nodes' ) );  // TTF018
    $toolbar->add_section( 'why', 90, array( $debug, 'add_toolbar_nodes' ) );    // TTF002
    ```

    Each callback receives the `WP_Admin_Bar` and the parent ID, checks its own conditions (capability, setting, eligibility) and returns whether it added anything. The parent node is only added if at least one section added something. Build the sections first, then the parent, or remove the parent when nothing was added.

    A public filter (`themetoggle_toolbar_sections`) can come later if other plugins need it; it's left out for now so the menu's contents stay under the plugin's control.
- **Node IDs:**
    - `themetoggle` (parent)
    - `themetoggle-switch`
    - `themetoggle-switch-default`, `themetoggle-switch-{n}`
    - `themetoggle-preview`, `themetoggle-preview-{n}`

    Use a counter, not the stylesheet, in IDs, so slugs never end up in HTML IDs.
- **Labels:** theme names from `Theme_Manager::get_theme_name()`, escaped with `esc_html()`. Mark child themes with `Theme_Manager::get_parent_label()`. `WP_Admin_Bar` escapes titles only partly, so never pass raw theme headers.

### Switch flow

Toolbar items are links, and links are GET requests. State changes over GET are acceptable in WordPress only with a nonce (core does the same for "Log out"). The handler:

1. `admin-post.php?action=tt_bar_switch&theme={slug}&_wpnonce={nonce}&tt_redirect={current}`.
2. Nonce action `tt_bar_switch_{user_id}_{stylesheet}` (or `_default`), so a nonce can't be reused for another theme.
3. `check_admin_referer( 'tt_bar_switch_' … )`, then `is_user_logged_in()`.
4. `sanitize_text_field( wp_unslash( $_GET['theme'] ) )`, then `Theme_Resolver::save_preference( wp_get_current_user(), $stylesheet )`, or `''` for the default theme. A rejected value returns 403.
5. Redirect through `Redirect::get_target()`. That method reads `$_POST` today, so add a `get_target_from_query()` variant or a source parameter. Keep `wp_validate_redirect()` and the removal of preview arguments.

Alternative: a small `<form method="post">` inside each menu item. It's cleaner HTTP, but the toolbar doesn't support forms well and keyboard focus gets awkward. Recommendation: GET with a nonce, as above.

### Files

- New `includes/class-toolbar.php`.
- `includes/class-plugin.php` (build it and register the switch section).
- `includes/class-redirect.php` (query-string target).
- `includes/class-settings.php`, `admin/views/settings-general.php`.
- `assets/css/frontend.css` and `assets/css/admin.css` (✓ marker, label truncation for long theme names).

## Security

| Threat | Prevention |
| --- | --- |
| CSRF: another site links a user to the switch URL. | Nonce bound to user and theme, checked with `check_admin_referer()`. |
| Switching to a theme the user isn't allowed. | `save_preference()` checks `can_user_select()` for the current user. The menu content is not trusted. |
| Open redirect through `tt_redirect`. | `wp_validate_redirect()` with a same-site fallback, as in `Redirect::get_target()`. |
| Slug injection into HTML. | Slugs only in escaped URLs (`esc_url()` after `add_query_arg()`); theme names through `esc_html()`. |
| Locked themes (TTF008) bypassed through the toolbar. | `save_preference()` enforces the lock; the Switch theme sub-item is hidden when locked. |
| A sub-item shown to someone who may not use it. | Each section checks its own capability and conditions; the parent never adds items itself. |

## Caching and headers

- The toolbar is only shown to logged-in users, and page caches normally bypass logged-in users.
- If a cache does store logged-in pages, the nonces in the toolbar are bound to that user and useless to anyone else. The menu would still show the wrong user's theme list, which is one more reason for TTF010.
- The handler response is a redirect; send `nocache_headers()` before `wp_safe_redirect()`.

## Data integrity, privacy and leakage

- The menu shows only themes the user may pick. Never list all installed themes.
- No change to stored data apart from `tt_user_theme`.

## Performance

- `get_selectable_themes()` already runs for eligible users and is cached per request. The menu adds no queries.
- Each section's callback must be cheap: sections that need counts (TTF018's "2 active") use one indexed query, only when the toolbar is showing.

## Compatibility

- WordPress 5.9+: `admin_bar_menu` and `WP_Admin_Bar::add_node()` are stable.
- Plugins that restyle or hide the toolbar (white-label plugins) may hide the menu; the profile page stays the fallback.

## Architecture invariants

- 3: writes go through `save_preference()`.
- 5: nonce, login check, `Redirect`.
- 2: the menu must not call `wp_get_theme()` without an argument. Read the applied theme from `Theme_Switcher::get_applied_theme()` and the default theme from `Theme_Manager::get_default_theme()`.

## Tests

- The menu appears for an eligible user with the current theme in its label, and lists exactly the selectable themes.
- A user with no applicable sub-item (not eligible, not allowed to share, not an admin): no `wp-admin-bar-themetoggle` node in the HTML.
- An admin who isn't eligible: the parent shows with only "Why this theme?" (once TTF002 exists).
- Switching with a valid nonce changes `tt_user_theme`, and the next front-end request uses the theme.
- Missing or wrong nonce: 403 (or the core "link expired" page), preference unchanged.
- A nonce for theme A used with `theme=B`: rejected.
- A forged slug for a non-permitted theme: 403, unchanged.
- `tt_redirect=https://evil.example/`: ends on the site.
- With `show_admin_bar_menu` off: no menu.
- On `widgets.php` with admin switching on: the label says "not applied here".

## Documentation to update

`README.md` (Features, Data), `readme.txt` (description, FAQ), `docs/ARCHITECTURE.md` (class table; security model table: new endpoint), `docs/TESTING.md` (manual check, also on a phone-width window), POT.

## Open questions

1. Parent label: "ThemeToggle: Astra Child", or just the theme name with an icon, to save toolbar space? Recommendation: the plugin name plus theme, truncated with CSS for long names.

## Decision log

- 2026-11-26: Spec written. GET with a per-theme nonce chosen over toolbar forms.
- 2026-11-26: Scope widened from "Admin-bar theme switcher" to "Toolbar menu": one ThemeToggle parent hosting Switch theme (this feature), Share this page… and My preview links (TTF018) and Why this theme? (TTF002). Sub-items are added through an internal section registry; the parent shows only when a section added something.
