<?php
/**
 * "Personal Theme Settings" section on the user profile screen.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

use WP_Error;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and saves the per-user theme selector.
 */
class User_Profile {

	/** Radio value meaning "leave the stored (currently invalid) preference untouched". */
	const KEEP_VALUE = '__tt_keep__';

	/**
	 * Resolver.
	 *
	 * @var Theme_Resolver
	 */
	private $resolver;

	/**
	 * Theme manager.
	 *
	 * @var Theme_Manager
	 */
	private $themes;

	/**
	 * Preview.
	 *
	 * @var Preview
	 */
	private $preview;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Validation error from the save step, reported via user_profile_update_errors.
	 *
	 * @var WP_Error|null
	 */
	private $error = null;

	/**
	 * Constructor.
	 *
	 * @param Settings       $settings Settings.
	 * @param Theme_Resolver $resolver Resolver.
	 * @param Theme_Manager  $themes   Theme manager.
	 * @param Preview        $preview  Preview.
	 */
	public function __construct( Settings $settings, Theme_Resolver $resolver, Theme_Manager $themes, Preview $preview ) {
		$this->settings = $settings;
		$this->resolver = $resolver;
		$this->themes   = $themes;
		$this->preview  = $preview;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'show_user_profile', array( $this, 'render' ) );
		add_action( 'edit_user_profile', array( $this, 'render' ) );
		add_action( 'personal_options_update', array( $this, 'save' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save' ) );
		add_action( 'user_profile_update_errors', array( $this, 'report_errors' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Whether the selector is shown for a user.
	 *
	 * @param WP_User $user Profile owner.
	 * @return bool
	 */
	public function is_available_for( WP_User $user ) {
		return $this->settings->is_enabled() && ! empty( $this->resolver->get_selectable_themes( $user ) );
	}

	/**
	 * Loads profile styles only on profile screens.
	 *
	 * @param string $hook_suffix Admin page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'profile.php' === $hook_suffix || 'user-edit.php' === $hook_suffix ) {
			wp_enqueue_style( 'themetoggle-admin', PLUGIN_URL . 'assets/css/admin.css', array(), VERSION );
		}
	}

	/**
	 * Renders the section.
	 *
	 * @param WP_User $user Profile owner.
	 * @return void
	 */
	public function render( $user ) {
		if ( ! $user instanceof WP_User || ! current_user_can( 'edit_user', $user->ID ) || ! $this->is_available_for( $user ) ) {
			return;
		}

		$themes     = $this->resolver->get_selectable_themes( $user );
		$state      = $this->resolver->get_user_state( $user );
		$preference = $state['preference'];
		$is_valid   = Theme_Resolver::STATUS_ACTIVE === $state['status'];
		$has_stale  = '' !== $preference && ! $is_valid;
		$checked    = $is_valid ? $preference : ( $has_stale ? self::KEEP_VALUE : '' );
		$is_self    = get_current_user_id() === (int) $user->ID;
		$default    = $this->themes->get_default_theme();
		$current    = $is_valid ? $state['theme']->display( 'Name', false ) : __( 'Default Theme', 'themetoggle' );
		$stale_name = $has_stale ? $this->themes->get_theme_name( $preference ) : '';

		$view = array(
			'user'       => $user,
			'themes'     => $themes,
			'checked'    => $checked,
			'is_self'    => $is_self,
			'default'    => $default,
			'current'    => $current,
			'has_stale'  => $has_stale,
			'stale_name' => $stale_name,
			'preview'    => $this->preview,
			'manager'    => $this->themes,
			'keep_value' => self::KEEP_VALUE,
		);

		require PLUGIN_DIR . 'admin/views/profile-section.php';
	}

	/**
	 * Saves the selection for the edited user.
	 *
	 * @param int $user_id Profile owner ID.
	 * @return void
	 */
	public function save( $user_id ) {
		$user_id = (int) $user_id;

		if ( ! isset( $_POST['tt_user_theme_nonce'] ) ) {
			return; // Selector was not rendered.
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['tt_user_theme_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'tt_save_user_theme_' . $user_id ) || ! current_user_can( 'edit_user', $user_id ) ) {
			$this->error = new WP_Error( 'tt_forbidden', __( 'Your theme preference could not be saved. Please try again.', 'themetoggle' ) );
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user || ! $this->is_available_for( $user ) ) {
			return;
		}

		$value = isset( $_POST['tt_user_theme'] ) ? sanitize_text_field( wp_unslash( $_POST['tt_user_theme'] ) ) : self::KEEP_VALUE;

		if ( self::KEEP_VALUE === $value ) {
			return;
		}

		if ( ! $this->resolver->save_preference( $user, $value ) ) {
			$this->error = new WP_Error( 'tt_invalid_theme', __( 'The selected theme is not available for this account. The theme preference was not changed.', 'themetoggle' ) );
		}
	}

	/**
	 * Surfaces save errors on the profile screen.
	 *
	 * @param WP_Error $errors Profile errors.
	 * @return void
	 */
	public function report_errors( $errors ) {
		if ( $this->error && $errors instanceof WP_Error ) {
			$errors->add( $this->error->get_error_code(), $this->error->get_error_message() );
		}
	}
}
