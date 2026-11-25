<?php
/**
 * Activation routine.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

defined( 'ABSPATH' ) || exit;

/**
 * Seeds default settings on activation. Never touches user preferences or the
 * globally active theme.
 */
class Installer {

	/**
	 * Activation callback. Static because register_activation_hook() runs before
	 * the plugin object graph exists.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( Settings::OPTION_NAME, false ) ) {
			add_option( Settings::OPTION_NAME, Settings::get_defaults(), '', true );
		}
	}
}
