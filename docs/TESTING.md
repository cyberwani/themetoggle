# ThemeToggle testing

Three layers: static checks (every change), the automated end-to-end suite (every change to PHP behaviour), and a manual checklist (before a release, on a real browser).

## 1. Static checks

```bash
composer check   # PHP lint + PHPCS (WordPress standard + PHP 7.4 compatibility)
npm run lint     # ESLint + Stylelint + the PHP checks above
```

Both must report 0 errors and 0 warnings. Never add a `phpcs:ignore` without a `--` justification on the same line.

## 2. End-to-end suite

`tests/e2e/http-tests.sh` logs in as real users over HTTP and checks which theme each response was rendered with. It needs a **disposable** WordPress site: `setup.sh` creates users and a custom role, installs two fixture themes and switches the active theme to Twenty Twenty-Five.

```bash
export TT_WP_PATH=/path/to/disposable-site TT_BASE=http://127.0.0.1:8899 TT_WP_CLI="wp"
bash tests/e2e/setup.sh
php -S 127.0.0.1:8899 -t "$TT_WP_PATH" &
composer test:e2e
```

`home` and `siteurl` of the site must equal `TT_BASE`. Expected result: `RESULT: 123 passed, 0 failed`, and no ThemeToggle lines in `wp-content/debug.log`.

Fixtures:

- `tt-child` — child of Twenty Twenty-One. Prints `<meta name="tt-functions">` on the frontend and `tt-functions-admin` in wp-admin, calls `set_theme_mod()` when `?tt_setmod=1`, registers its own sidebar.
- `tt-orphan` — child of a theme that does not exist (must never load).

Adding a test: use the helpers in `lib.sh` (`check`, `contains`, `lacks`, `theme_of`, `meta`, `setmeta`, `patch_settings`), put it under the matching `== section`, and reset any settings you change with `set_settings "$SETTINGS_BASE"`.

### In CI

`.github/workflows/ci.yml` runs this suite on WordPress 6.7 + PHP 7.4 and on the latest WordPress + PHP 8.3, against MySQL 8. `tests/e2e/ci-install-wordpress.sh` downloads WordPress, installs it at `TT_WP_PATH` (needs `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `DB_HOST`), installs Twenty Twenty-One…Twenty-Five, blocks outbound HTTP and links the plugin in. You can run the same script locally against a scratch MySQL database.

## 3. Manual checklist

Use the accounts from the e2e setup or your own. ✅ = expected result.

### Administrator

- [ ] Appearance → ThemeToggle shows four tabs; each saves without touching the others' values.
- [ ] Turning off "Enable Theme Switching" sends everyone to the default theme; saved preferences remain (Users column shows "switching disabled").
- [ ] Turning admin switching on/off changes only wp-admin; the Themes, Widgets, Menus, Site Editor, Customizer and ThemeToggle screens never switch.
- [ ] Allowed roles list includes custom roles (for example WooCommerce "Shop manager").
- [ ] Role table shows screenshots, stylesheet directory, "Child of …" and "(site default)".
- [ ] Users → All Users shows "Selected Theme": theme name in bold for valid choices, "Default Theme (Invalid Selection: …)" for invalid ones; the column sorts without hiding users.
- [ ] Bulk action "Reset selected users' themes" clears only the ticked users.
- [ ] Editing another user's profile shows that user's permitted themes only.
- [ ] Reset All requires the checkbox and a confirm dialog.

### Editor / Author (allowed roles)

- [ ] Profile shows "Personal Theme Settings" with Default Theme plus permitted themes only.
- [ ] Choosing a theme changes the frontend for this user only (check in a private window as a visitor).
- [ ] "Preview before applying" opens one page in the theme with a "Previewing" bar; the saved choice is unchanged until "Use this theme".
- [ ] The floating button returns to the default theme and lands on the same page.

### Contributor / Subscriber (not allowed by default)

- [ ] No profile section, no button, always the default theme.

### Multi-role user

- [ ] Gets the union of the themes of every allowed role, without duplicates.

### Abuse

- [ ] `?theme=…`, `?stylesheet=…`, a copied preview link, or a hand-made POST cannot apply a theme the user is not permitted.
- [ ] Redirect targets pointing to another host end on the site instead.

### Theme removal and role changes

- [ ] Deleting or renaming the chosen theme's folder → default theme, Users column says "theme not installed".
- [ ] Removing the theme from the role, or the role from the user → default theme; restoring it brings the choice back.

### Caching

- [ ] With a page cache plugin active, a visitor never sees a user's personal theme; switched responses carry `Cache-Control: … private` and `Vary: Cookie`.

### Classic theme data

- [ ] A user on a classic theme saving Customizer-style settings through the theme does not change the default theme's menus, logo or widgets.
