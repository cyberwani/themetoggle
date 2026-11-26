<?php
/**
 * Plugin Name:       ThemeToggle
 * Description:       Enable user-specific WordPress themes with role-based permissions and personalized theme switching.
 * Version:           1.0.1
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       themetoggle
 * Domain Path:       /languages
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

defined( 'ABSPATH' ) || exit;

/*
 * Namespaced constants (ThemeToggle\VERSION etc.) instead of global ones, so
 * nothing is added to the global constant table.
 */
const VERSION     = '1.0.1';
const PLUGIN_FILE = __FILE__;
define( __NAMESPACE__ . '\\PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( __NAMESPACE__ . '\\PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PLUGIN_DIR . 'includes/class-autoloader.php';

( new Autoloader( __NAMESPACE__, PLUGIN_DIR ) )->register();

register_activation_hook( __FILE__, array( Installer::class, 'activate' ) );

/**
 * Returns the single plugin instance.
 *
 * A function-level static is used (instead of a global variable) so that the
 * object graph is built exactly once per request.
 *
 * @return Plugin
 */
function plugin() {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new Plugin();
	}

	return $instance;
}

/*
 * Boot as early as possible on plugins_loaded: pluggable.php (and therefore
 * wp_get_current_user()) is available, while the active theme has not been
 * located yet (that happens after the setup_theme action).
 */
add_action( 'plugins_loaded', array( plugin(), 'init' ), 1 );
