<?php
/**
 * Test fixture (must-use plugin) for the ThemeToggle e2e suite.
 *
 * Simulates other code interacting with ThemeToggle. Each behaviour only runs
 * when its query parameter is present. Installed by tests/e2e/setup.sh into a
 * disposable site; never install it on a real site.
 *
 * @package ThemeToggle\Tests
 */

defined( 'ABSPATH' ) || exit;

/*
 * Another plugin restricting the Users list with a meta_query: only users with
 * the tt_e2e_visible meta are listed. Sorting by "Selected Theme" must keep it.
 */
add_action(
	'pre_get_users',
	static function ( $query ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Test fixture switch.
		if ( isset( $_GET['tt_e2e_hide_users'] ) && $query instanceof WP_User_Query ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Test fixture.
			$query->set(
				'meta_query',
				array(
					array(
						'key'     => 'tt_e2e_visible',
						'compare' => 'EXISTS',
					),
				)
			);
		}
	}
);

/*
 * Extensions returning a theme through themetoggle_applied_theme: a broken one
 * (a child theme whose parent is missing) must be ignored, and a valid one must
 * not apply on screens ThemeToggle never switches.
 */
add_filter(
	'themetoggle_applied_theme',
	static function ( $theme ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Test fixture switches.
		if ( isset( $_GET['tt_e2e_broken_theme'] ) ) {
			return wp_get_theme( 'tt-orphan' );
		}

		// An extension forcing a valid theme; must not apply on excluded screens.
		if ( isset( $_GET['tt_e2e_force_theme'] ) ) {
			return wp_get_theme( sanitize_key( wp_unslash( $_GET['tt_e2e_force_theme'] ) ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $theme;
	}
);
