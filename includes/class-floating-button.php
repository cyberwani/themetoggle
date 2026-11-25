<?php
/**
 * Floating "Switch to Default Theme" button and preview bar.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

defined( 'ABSPATH' ) || exit;

/**
 * Renders a small fixed-position form that POSTs to admin-post.php. No
 * JavaScript is required; the reset is nonce-protected and redirects back to
 * a validated same-site URL.
 */
class Floating_Button {

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Switcher.
	 *
	 * @var Theme_Switcher
	 */
	private $switcher;

	/**
	 * Resolver.
	 *
	 * @var Theme_Resolver
	 */
	private $resolver;

	/**
	 * Constructor.
	 *
	 * @param Settings       $settings Settings.
	 * @param Theme_Switcher $switcher Switcher.
	 * @param Theme_Resolver $resolver Resolver.
	 */
	public function __construct( Settings $settings, Theme_Switcher $switcher, Theme_Resolver $resolver ) {
		$this->settings = $settings;
		$this->switcher = $switcher;
		$this->resolver = $resolver;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render' ) );
		add_action( 'admin_footer', array( $this, 'render' ) );
		add_action( 'admin_post_tt_reset_theme', array( $this, 'handle_reset' ) );
	}

	/**
	 * Whether anything should be rendered in this request.
	 *
	 * @return string '' (nothing), 'button' or 'preview'.
	 */
	private function get_mode() {
		if ( ! is_user_logged_in() || ! $this->switcher->is_applied() ) {
			return '';
		}

		if ( $this->switcher->is_preview() ) {
			return is_admin() ? '' : 'preview';
		}

		if ( ! $this->settings->get( 'show_switch_button' ) ) {
			return '';
		}

		$key = is_admin() ? 'show_switch_button_admin' : 'show_switch_button_frontend';

		return $this->settings->get( $key ) ? 'button' : '';
	}

	/**
	 * Enqueues the tiny stylesheet only when the button will render.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( '' !== $this->get_mode() ) {
			wp_enqueue_style( 'themetoggle-frontend', PLUGIN_URL . 'assets/css/frontend.css', array(), VERSION );
		}
	}

	/**
	 * Renders the button or preview bar.
	 *
	 * @return void
	 */
	public function render() {
		$mode = $this->get_mode();

		if ( '' === $mode ) {
			return;
		}

		$theme    = $this->switcher->get_applied_theme();
		$settings = $this->settings->get_all();
		$classes  = array(
			'tt-floating',
			'tt-floating--' . $settings['button_position'],
			'tt-floating--' . $settings['button_size'],
		);
		$style    = sprintf(
			'--tt-bg:%1$s;--tt-color:%2$s;--tt-radius:%3$dpx;',
			$settings['button_bg_color'],
			$settings['button_text_color'],
			(int) $settings['button_radius']
		);
		$view     = array(
			'mode'     => $mode,
			'classes'  => $classes,
			'style'    => $style,
			'label'    => $this->settings->get_button_label(),
			'icon'     => $settings['button_icon'],
			'theme'    => $theme,
			'redirect' => Redirect::current_uri(),
		);

		require PLUGIN_DIR . 'admin/views/floating-button.php';
	}

	/**
	 * Clears the current user's preference.
	 *
	 * @return void
	 */
	public function handle_reset() {
		check_admin_referer( 'tt_reset_theme', 'tt_nonce' );

		$user = wp_get_current_user();

		if ( ! $user->exists() ) {
			wp_die( esc_html__( 'You must be logged in to change your theme.', 'themetoggle' ), '', array( 'response' => 403 ) );
		}

		$this->resolver->save_preference( $user, '' );

		wp_safe_redirect( Redirect::get_target( 'tt_redirect' ) );
		exit;
	}
}
