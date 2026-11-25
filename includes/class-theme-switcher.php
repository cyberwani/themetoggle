<?php
/**
 * Applies the effective theme through the `template` and `stylesheet` filters.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

use WP_Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Per-request theme override for the current user.
 *
 * WordPress locates the active theme through get_template() and
 * get_stylesheet(), which apply the `template` and `stylesheet` filters. They
 * are first called after the `setup_theme` action (wp_templating_constants()
 * and the loading of the theme's functions.php), so filters registered on
 * `plugins_loaded` take effect for the whole request. The global `template`
 * and `stylesheet` options are never written.
 */
class Theme_Switcher {

	/**
	 * Filter priority. Late enough to win over generic filters registered at the
	 * default priority (10), so the user's explicit choice is authoritative.
	 * Contexts where core itself filters the theme (Customizer previews) are
	 * excluded by Request_Context rather than out-prioritised.
	 */
	const PRIORITY = 99;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Resolver.
	 *
	 * @var Theme_Resolver
	 */
	private $resolver;

	/**
	 * Context.
	 *
	 * @var Request_Context
	 */
	private $context;

	/**
	 * Preview.
	 *
	 * @var Preview
	 */
	private $preview;

	/**
	 * Data guard.
	 *
	 * @var Data_Guard
	 */
	private $guard;

	/**
	 * Resolved theme: null = not resolved yet, false = no override.
	 *
	 * @var WP_Theme|false|null
	 */
	private $applied = null;

	/**
	 * Whether the applied theme comes from a preview.
	 *
	 * @var bool
	 */
	private $is_preview = false;

	/**
	 * Recursion guard.
	 *
	 * @var bool
	 */
	private $resolving = false;

	/**
	 * Constructor.
	 *
	 * @param Settings        $settings Settings.
	 * @param Theme_Resolver  $resolver Resolver.
	 * @param Request_Context $context  Context.
	 * @param Preview         $preview  Preview.
	 * @param Data_Guard      $guard    Data guard.
	 */
	public function __construct( Settings $settings, Theme_Resolver $resolver, Request_Context $context, Preview $preview, Data_Guard $guard ) {
		$this->settings = $settings;
		$this->resolver = $resolver;
		$this->context  = $context;
		$this->preview  = $preview;
		$this->guard    = $guard;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'template', array( $this, 'filter_template' ), self::PRIORITY );
		add_filter( 'stylesheet', array( $this, 'filter_stylesheet' ), self::PRIORITY );
		add_action( 'send_headers', array( $this, 'send_private_cache_headers' ) );
	}

	/**
	 * `template` filter: parent theme directory for child themes, otherwise
	 * the theme's own directory.
	 *
	 * @param string $template Original template.
	 * @return string
	 */
	public function filter_template( $template ) {
		$theme = $this->get_applied_theme();

		return $theme ? $theme->get_template() : $template;
	}

	/**
	 * `stylesheet` filter: always the selected (possibly child) theme.
	 *
	 * @param string $stylesheet Original stylesheet.
	 * @return string
	 */
	public function filter_stylesheet( $stylesheet ) {
		$theme = $this->get_applied_theme();

		return $theme ? $theme->get_stylesheet() : $stylesheet;
	}

	/**
	 * Whether a user-specific theme is applied to this request.
	 *
	 * @return bool
	 */
	public function is_applied() {
		return null !== $this->get_applied_theme();
	}

	/**
	 * Whether the applied theme is a temporary preview.
	 *
	 * @return bool
	 */
	public function is_preview() {
		return $this->is_applied() && $this->is_preview;
	}

	/**
	 * The theme applied to this request, if any. Resolved once per request.
	 *
	 * @return WP_Theme|null
	 */
	public function get_applied_theme() {
		if ( null !== $this->applied ) {
			return $this->applied ? $this->applied : null;
		}

		// Never resolve re-entrantly or before the current user can be known.
		if ( $this->resolving || ! did_action( 'plugins_loaded' ) || ! function_exists( 'wp_get_current_user' ) ) {
			return null;
		}

		$this->resolving = true;
		$theme           = $this->determine_theme();

		/**
		 * Filters the theme applied for the current user and request.
		 *
		 * Return null to keep the globally active theme.
		 *
		 * @param WP_Theme|null $theme      Theme to apply.
		 * @param bool          $is_preview Whether it is a preview.
		 */
		$theme = apply_filters( 'themetoggle_applied_theme', $theme, $this->is_preview );

		if ( ! $theme instanceof WP_Theme || $theme->get_stylesheet() === get_option( 'stylesheet' ) ) {
			$theme            = null;
			$this->is_preview = false;
		}

		$this->applied   = $theme ? $theme : false;
		$this->resolving = false;

		if ( $theme ) {
			$this->guard->activate( $theme->get_stylesheet() );
		}

		return $theme;
	}

	/**
	 * Resolution order: master switch, context, login, eligible roles with
	 * assigned themes, preview, saved preference validated against current
	 * permissions and installed themes.
	 *
	 * @return WP_Theme|null
	 */
	private function determine_theme() {
		if ( ! $this->settings->is_enabled() || empty( $this->settings->get_allowed_roles() ) ) {
			return null;
		}

		$context = $this->context->get();

		if ( Request_Context::NONE === $context ) {
			return null;
		}

		if ( Request_Context::ADMIN === $context && ! $this->settings->is_admin_enabled() ) {
			return null;
		}

		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			return null;
		}

		$preview = $this->preview->get_previewed_theme( $user );

		if ( $preview ) {
			$this->is_preview = true;
			return $preview;
		}

		$state = $this->resolver->get_user_state( $user );

		return Theme_Resolver::STATUS_ACTIVE === $state['status'] ? $state['theme'] : null;
	}

	/**
	 * Responses rendered with a user-specific theme must never be stored by a
	 * shared (page/proxy) cache and served to someone else.
	 *
	 * @return void
	 */
	public function send_private_cache_headers() {
		if ( ! $this->is_applied() ) {
			return;
		}

		nocache_headers();

		if ( ! headers_sent() ) {
			header( 'Vary: Cookie', false );
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard constant honoured by caching plugins.
		}
	}
}
