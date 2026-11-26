<?php
/**
 * Main plugin class: builds the object graph and registers hooks once.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

use ThemeToggle\Admin\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Composition root. Services are created with explicit constructor injection
 * and are reachable through read-only accessors for extensions and tests.
 */
class Plugin {

	/**
	 * Whether init() already ran.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Services keyed by name.
	 *
	 * @var array<string,object>
	 */
	private $services = array();

	/**
	 * Builds services and registers hooks. Safe to call more than once.
	 *
	 * @return void
	 */
	public function init() {
		if ( $this->initialized ) {
			return;
		}

		$this->initialized = true;

		$themes   = new Theme_Manager();
		$roles    = new Role_Manager();
		$settings = new Settings( $themes, $roles );
		$roles->set_settings( $settings );

		$resolver = new Theme_Resolver( $settings, $roles, $themes );
		$context  = new Request_Context();
		$preview  = new Preview( $resolver, $context );
		$guard    = new Data_Guard();
		$switcher = new Theme_Switcher( $settings, $resolver, $context, $preview, $guard );
		$button   = new Floating_Button( $settings, $switcher, $resolver );

		$this->services = array(
			'themes'   => $themes,
			'roles'    => $roles,
			'settings' => $settings,
			'resolver' => $resolver,
			'context'  => $context,
			'preview'  => $preview,
			'guard'    => $guard,
			'switcher' => $switcher,
			'button'   => $button,
		);

		// Theme filters first: they must be in place before setup_theme.
		$switcher->register_hooks();
		$settings->register_hooks();
		$resolver->register_hooks();
		$preview->register_hooks();
		$button->register_hooks();

		add_action( 'init', array( $this, 'load_textdomain' ) );

		if ( is_admin() ) {
			$profile = new User_Profile( $settings, $resolver, $themes, $preview );
			$users   = new Users_List( $settings, $resolver, $themes );
			$admin   = new Admin( $settings, $themes, $roles );

			$profile->register_hooks();
			$users->register_hooks();
			$admin->register_hooks();

			$this->services['profile'] = $profile;
			$this->services['users']   = $users;
			$this->services['admin']   = $admin;
		}

		/**
		 * Fires after ThemeToggle registered its hooks.
		 *
		 * @param Plugin $plugin Plugin instance.
		 */
		do_action( 'themetoggle_loaded', $this );
	}

	/**
	 * Returns a service.
	 *
	 * @param string $name Service name.
	 * @return object|null
	 */
	public function get( $name ) {
		return isset( $this->services[ $name ] ) ? $this->services[ $name ] : null;
	}

	/**
	 * Loads bundled translations (WordPress.org language packs load automatically).
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'themetoggle', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
	}
}
