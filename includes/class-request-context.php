<?php
/**
 * Classifies the current request for theme switching purposes.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether the current request is a frontend request, an admin request,
 * or a request where switching must never happen.
 *
 * This runs during theme bootstrap (before `init`), so it can only use request
 * data and globals that WordPress sets up early (`$pagenow`, constants).
 * Nothing here changes state; request variables are only read.
 */
class Request_Context {

	/** Theme switching may apply as on the public site. */
	const FRONTEND = 'frontend';

	/** Theme switching may apply when admin switching is enabled. */
	const ADMIN = 'admin';

	/** Theme switching must not apply. */
	const NONE = 'none';

	/**
	 * Cached context.
	 *
	 * @var string|null
	 */
	private $context = null;

	/**
	 * Returns the request context (cached).
	 *
	 * @return string One of the class constants.
	 */
	public function get() {
		if ( null === $this->context ) {
			/**
			 * Filters the request context used for theme switching.
			 *
			 * @param string $context 'frontend', 'admin' or 'none'.
			 */
			$context       = apply_filters( 'themetoggle_request_context', $this->detect() );
			$this->context = in_array( $context, array( self::FRONTEND, self::ADMIN, self::NONE ), true ) ? $context : self::NONE;
		}

		return $this->context;
	}

	/**
	 * Whether this is a direct (top-level, non-AJAX, non-REST) frontend page view.
	 *
	 * @return bool
	 */
	public function is_direct_frontend() {
		return self::FRONTEND === $this->get() && ! is_admin() && ! $this->is_indirect_request();
	}

	/**
	 * Detection logic.
	 *
	 * @return string
	 */
	private function detect() {
		if ( $this->is_unsupported_environment() || $this->is_customizer_request() ) {
			return self::NONE;
		}

		if ( $this->is_indirect_request() ) {
			return $this->detect_from_referer();
		}

		if ( is_admin() ) {
			return $this->is_excluded_admin_page( $this->get_pagenow() ) ? self::NONE : self::ADMIN;
		}

		return self::FRONTEND;
	}

	/**
	 * CLI, cron, XML-RPC and installation requests never switch themes.
	 *
	 * @return bool
	 */
	private function is_unsupported_environment() {
		return ( defined( 'WP_CLI' ) && WP_CLI )
			|| wp_doing_cron()
			|| ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST )
			|| wp_installing();
	}

	/**
	 * The Customizer previews themes with its own `template`/`stylesheet`
	 * filters; overriding them would break theme previews and could save
	 * changesets against the wrong theme.
	 *
	 * @return bool
	 */
	private function is_customizer_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only request classification; no state change.
		$is_customizer = isset( $_REQUEST['wp_customize'] )
			|| isset( $_REQUEST['customize_changeset_uuid'] )
			|| 'customize.php' === $this->get_pagenow();
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		return $is_customizer;
	}

	/**
	 * AJAX, admin-post and REST requests are classified by the page that issued
	 * them (the referer), not by the endpoint, so that e.g. REST calls made by
	 * the Site Editor never write templates against a user-selected theme.
	 *
	 * @return bool
	 */
	private function is_indirect_request() {
		return wp_doing_ajax() || 'admin-post.php' === $this->get_pagenow() || $this->is_rest_request();
	}

	/**
	 * REST detection that works before `parse_request` (when REST_REQUEST is
	 * not yet defined).
	 *
	 * @return bool
	 */
	private function is_rest_request() {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request classification.
		if ( isset( $_GET['rest_route'] ) ) {
			return true;
		}

		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! function_exists( 'rest_get_url_prefix' ) ) {
			return false;
		}

		$uri    = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$prefix = '/' . trim( rest_get_url_prefix(), '/' ) . '/';

		return false !== strpos( $uri, $prefix );
	}

	/**
	 * Classifies an indirect request from its referer. Missing or foreign
	 * referers never switch (fail safe).
	 *
	 * @return string
	 */
	private function detect_from_referer() {
		if ( wp_doing_ajax() && $this->is_excluded_ajax_action() ) {
			return self::NONE;
		}

		$referer = wp_get_raw_referer();

		if ( ! $referer ) {
			return self::NONE;
		}

		$referer_host = wp_parse_url( $referer, PHP_URL_HOST );
		$home_host    = wp_parse_url( home_url(), PHP_URL_HOST );
		$admin_host   = wp_parse_url( admin_url(), PHP_URL_HOST );

		if ( ! $referer_host || ( $referer_host !== $home_host && $referer_host !== $admin_host ) ) {
			return self::NONE;
		}

		$admin_path   = (string) wp_parse_url( admin_url(), PHP_URL_PATH );
		$referer_path = (string) wp_parse_url( $referer, PHP_URL_PATH );

		if ( '' !== $admin_path && 0 === strpos( $referer_path, $admin_path ) ) {
			$page = basename( $referer_path );
			$page = ( '' === $page || basename( $admin_path ) === $page ) ? 'index.php' : $page;

			return $this->is_excluded_admin_page( $page ) ? self::NONE : self::ADMIN;
		}

		return self::FRONTEND;
	}

	/**
	 * Admin screens that manage themes, widgets, menus or site structure.
	 * Switching here could mis-attribute theme-specific data to the wrong theme.
	 * This also covers this plugin's own settings page (themes.php?page=...).
	 *
	 * @param string $page Admin file name, e.g. 'themes.php'.
	 * @return bool
	 */
	private function is_excluded_admin_page( $page ) {
		$excluded = array(
			'themes.php',
			'theme-install.php',
			'theme-editor.php',
			'site-editor.php',
			'customize.php',
			'widgets.php',
			'nav-menus.php',
			'update.php',
			'update-core.php',
		);

		/**
		 * Filters admin pages on which a user-selected theme is never applied.
		 *
		 * @param string[] $excluded Admin file names.
		 */
		$excluded = (array) apply_filters( 'themetoggle_excluded_admin_pages', $excluded );

		return in_array( $page, $excluded, true );
	}

	/**
	 * AJAX actions that manage themes, widgets or the Customizer.
	 *
	 * @return bool
	 */
	private function is_excluded_ajax_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request classification.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';

		if ( 0 === strpos( $action, 'customize' ) ) {
			return true;
		}

		$excluded = array(
			'install-theme',
			'update-theme',
			'delete-theme',
			'query-themes',
			'save-widget',
			'widgets-order',
			'add-menu-item',
			'menu-locations-save',
			'menu-get-metabox',
			'menu-quick-search',
		);

		/**
		 * Filters AJAX actions for which a user-selected theme is never applied.
		 *
		 * @param string[] $excluded Action names.
		 */
		$excluded = (array) apply_filters( 'themetoggle_excluded_ajax_actions', $excluded );

		return in_array( $action, $excluded, true );
	}

	/**
	 * Current admin file name.
	 *
	 * @return string
	 */
	private function get_pagenow() {
		return isset( $GLOBALS['pagenow'] ) && is_string( $GLOBALS['pagenow'] ) ? $GLOBALS['pagenow'] : '';
	}
}
