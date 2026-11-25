<?php
/**
 * Single-page theme preview before saving a preference.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

use WP_Theme;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Lets an eligible user view one frontend page with a permitted theme without
 * changing the saved preference. The preview is carried by a user-bound nonce
 * in the URL and is validated exactly like a saved preference; it is never
 * persisted. Saving requires an explicit, nonce-protected POST.
 */
class Preview {

	/** Query var carrying the previewed stylesheet. */
	const QUERY_VAR = 'tt_preview';

	/** Query var carrying the nonce. */
	const NONCE_VAR = 'tt_preview_nonce';

	/**
	 * Resolver.
	 *
	 * @var Theme_Resolver
	 */
	private $resolver;

	/**
	 * Request context.
	 *
	 * @var Request_Context
	 */
	private $context;

	/**
	 * Cached previewed theme: null = not computed, false = none.
	 *
	 * @var WP_Theme|false|null
	 */
	private $theme = null;

	/**
	 * Constructor.
	 *
	 * @param Theme_Resolver  $resolver Resolver.
	 * @param Request_Context $context  Request context.
	 */
	public function __construct( Theme_Resolver $resolver, Request_Context $context ) {
		$this->resolver = $resolver;
		$this->context  = $context;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_post_tt_select_theme', array( $this, 'handle_select' ) );
	}

	/**
	 * Builds a preview URL for the current user.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return string
	 */
	public function get_preview_url( $stylesheet ) {
		return add_query_arg(
			array(
				self::QUERY_VAR => rawurlencode( $stylesheet ),
				self::NONCE_VAR => wp_create_nonce( $this->nonce_action( $stylesheet ) ),
			),
			home_url( '/' )
		);
	}

	/**
	 * The theme being previewed in this request, if valid.
	 *
	 * @param WP_User $user Current user.
	 * @return WP_Theme|null
	 */
	public function get_previewed_theme( WP_User $user ) {
		if ( null !== $this->theme ) {
			return $this->theme ? $this->theme : null;
		}

		$this->theme = false;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The nonce is verified below.
		if ( ! $this->context->is_direct_frontend() || ! isset( $_GET[ self::QUERY_VAR ], $_GET[ self::NONCE_VAR ] ) ) {
			return null;
		}

		$stylesheet = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) );
		$nonce      = sanitize_text_field( wp_unslash( $_GET[ self::NONCE_VAR ] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! wp_verify_nonce( $nonce, $this->nonce_action( $stylesheet ) ) ) {
			return null;
		}

		if ( ! $this->resolver->can_user_select( $user, $stylesheet ) ) {
			return null;
		}

		$themes      = $this->resolver->get_selectable_themes( $user );
		$this->theme = $themes[ $stylesheet ];

		return $this->theme;
	}

	/**
	 * Handles "Use this theme" from the preview bar.
	 *
	 * @return void
	 */
	public function handle_select() {
		check_admin_referer( 'tt_select_theme', 'tt_nonce' );

		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			wp_die( esc_html__( 'You must be logged in to choose a theme.', 'themetoggle' ), '', array( 'response' => 403 ) );
		}

		$stylesheet = isset( $_POST['tt_theme'] ) ? sanitize_text_field( wp_unslash( $_POST['tt_theme'] ) ) : '';

		if ( '' === $stylesheet || ! $this->resolver->save_preference( $user, $stylesheet ) ) {
			wp_die( esc_html__( 'That theme is not available for your account.', 'themetoggle' ), '', array( 'response' => 403 ) );
		}

		wp_safe_redirect( Redirect::get_target( 'tt_redirect' ) );
		exit;
	}

	/**
	 * Nonce action for a stylesheet.
	 *
	 * @param string $stylesheet Stylesheet.
	 * @return string
	 */
	private function nonce_action( $stylesheet ) {
		return 'tt_preview_' . $stylesheet;
	}
}
