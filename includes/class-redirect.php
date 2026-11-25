<?php
/**
 * Safe redirect target handling.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless helper shared by all POST handlers. Static by design: it has no
 * dependencies or state, and centralising it guarantees every handler uses the
 * same open-redirect protection.
 */
final class Redirect {

	/**
	 * Returns the current request URI as a relative URL for use in forms,
	 * without preview arguments.
	 *
	 * @return string
	 */
	public static function current_uri() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		return remove_query_arg( array( Preview::QUERY_VAR, Preview::NONCE_VAR ), $uri );
	}

	/**
	 * Reads a redirect target from POST and validates it against allowed hosts
	 * (wp_validate_redirect()), falling back to the referer or the home page.
	 *
	 * @param string $field POST field name.
	 * @return string
	 */
	public static function get_target( $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Callers verify the nonce before calling.
		$raw      = isset( $_POST[ $field ] ) && is_string( $_POST[ $field ] ) ? esc_url_raw( wp_unslash( $_POST[ $field ] ) ) : '';
		$fallback = wp_get_referer();
		$fallback = $fallback ? $fallback : home_url( '/' );
		$target   = '' === trim( $raw ) ? $fallback : wp_validate_redirect( $raw, $fallback );
		$target   = '' === $target ? home_url( '/' ) : $target;

		return remove_query_arg( array( Preview::QUERY_VAR, Preview::NONCE_VAR ), $target );
	}
}
