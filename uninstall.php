<?php
/**
 * Uninstall: removes plugin settings and saved user preferences.
 *
 * Runs only when the plugin is deleted from Plugins → Installed Plugins,
 * never on deactivation.
 *
 * @package ThemeToggle
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'tt_settings' );
delete_metadata( 'user', 0, 'tt_user_theme', '', true );
