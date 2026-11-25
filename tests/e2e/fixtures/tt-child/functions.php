<?php
/**
 * Test fixture: child theme of Twenty Twenty-One that leaves detectable markers.
 *
 * @package ThemeToggle\Tests
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_head',
	static function () {
		echo '<meta name="tt-functions" content="tt-child">';
	}
);

add_action(
	'admin_head',
	static function () {
		echo '<meta name="tt-functions-admin" content="tt-child">';
	}
);

add_action(
	'after_setup_theme',
	static function () {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test fixture trigger.
		if ( isset( $_GET['tt_setmod'] ) ) {
			set_theme_mod( 'tt_probe', 'from-child' );
		}
	}
);

add_action(
	'widgets_init',
	static function () {
		register_sidebar(
			array(
				'id'   => 'tt-child-sidebar',
				'name' => 'TT Child Sidebar',
			)
		);
	}
);
