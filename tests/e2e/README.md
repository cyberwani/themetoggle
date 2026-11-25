# ThemeToggle end-to-end tests

HTTP-level tests run against a **disposable** WordPress install (they create users and roles and switch the active theme). Never point them at a real site.

Requirements: bash, curl, WP-CLI, a WordPress site with the bundled Twenty Twenty-One…Twenty-Five themes, user `admin`.

```bash
export TT_WP_PATH=/path/to/test-site TT_BASE=http://127.0.0.1:8899 TT_WP_CLI="wp"
bash setup.sh
php -S 127.0.0.1:8899 -t "$TT_WP_PATH" &   # or any web server
bash http-tests.sh
```

Fixtures: `tt-child` (child of Twenty Twenty-One that prints markers and calls set_theme_mod), `tt-orphan` (child of a missing parent).
