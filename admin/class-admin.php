<?php
/**
 * Settings screen under Appearance.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle\Admin;

use ThemeToggle\Role_Manager;
use ThemeToggle\Settings;
use ThemeToggle\Theme_Manager;
use ThemeToggle\Theme_Resolver;
use WP_User_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Appearance → ThemeToggle page, its tabs, assets and the
 * "reset all preferences" tool.
 */
class Admin {

	/** Page slug. */
	const PAGE_SLUG = 'themetoggle';

	/** Capability required to manage the plugin. */
	const CAPABILITY = 'manage_options';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Theme manager.
	 *
	 * @var Theme_Manager
	 */
	private $themes;

	/**
	 * Role manager.
	 *
	 * @var Role_Manager
	 */
	private $roles;

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private $hook_suffix = '';

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings Settings.
	 * @param Theme_Manager $themes   Theme manager.
	 * @param Role_Manager  $roles    Role manager.
	 */
	public function __construct( Settings $settings, Theme_Manager $themes, Role_Manager $roles ) {
		$this->settings = $settings;
		$this->themes   = $themes;
		$this->roles    = $roles;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_tt_reset_all', array( $this, 'handle_reset_all' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( \ThemeToggle\PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	/**
	 * Tabs: slug => label.
	 *
	 * @return array<string,string>
	 */
	public function get_tabs() {
		return array(
			'general' => __( 'General Settings', 'themetoggle' ),
			'roles'   => __( 'Role-Based Themes', 'themetoggle' ),
			'users'   => __( 'User Profiles', 'themetoggle' ),
			'button'  => __( 'Floating Button', 'themetoggle' ),
		);
	}

	/**
	 * Adds the submenu page.
	 *
	 * @return void
	 */
	public function register_menu() {
		$hook = add_theme_page(
			__( 'ThemeToggle', 'themetoggle' ),
			__( 'ThemeToggle', 'themetoggle' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		$this->hook_suffix = $hook ? $hook : '';
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		if ( current_user_can( self::CAPABILITY ) ) {
			array_unshift(
				$links,
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $this->get_page_url() ),
					esc_html__( 'Settings', 'themetoggle' )
				)
			);
		}

		return $links;
	}

	/**
	 * URL of a tab.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public function get_page_url( $tab = 'general' ) {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'themes.php' )
		);
	}

	/**
	 * Loads assets on the settings page only.
	 *
	 * @param string $hook_suffix Current admin page.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( '' === $this->hook_suffix || $hook_suffix !== $this->hook_suffix ) {
			return;
		}

		$deps = array();

		if ( 'button' === $this->get_current_tab() ) {
			wp_enqueue_style( 'wp-color-picker' );
			$deps = array( 'jquery', 'wp-color-picker' );
		}

		wp_enqueue_style( 'themetoggle-admin', \ThemeToggle\PLUGIN_URL . 'assets/css/admin.css', array(), \ThemeToggle\VERSION );
		wp_enqueue_script( 'themetoggle-admin', \ThemeToggle\PLUGIN_URL . 'assets/js/admin.js', $deps, \ThemeToggle\VERSION, true );
		wp_localize_script(
			'themetoggle-admin',
			'themetoggleAdmin',
			array(
				'confirmResetAll' => __( 'This permanently removes the saved theme preference of every user. Continue?', 'themetoggle' ),
			)
		);
	}

	/**
	 * Active tab (whitelisted).
	 *
	 * @return string
	 */
	public function get_current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Navigation only.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		return array_key_exists( $tab, $this->get_tabs() ) ? $tab : 'general';
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'themetoggle' ), '', array( 'response' => 403 ) );
		}

		$tab  = $this->get_current_tab();
		$view = array(
			'tab'      => $tab,
			'tabs'     => $this->get_tabs(),
			'admin'    => $this,
			'settings' => $this->settings->get_all(),
			'themes'   => $this->themes,
			'roles'    => $this->roles,
		);

		if ( 'users' === $tab ) {
			$view['pref_count'] = $this->count_users_with_preference();
		}

		require \ThemeToggle\PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Number of users with a saved preference.
	 *
	 * @return int
	 */
	private function count_users_with_preference() {
		$query = new WP_User_Query(
			array(
				'meta_key'    => Theme_Resolver::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin-only count.
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			)
		);

		return (int) $query->get_total();
	}

	/**
	 * Deletes every user's saved preference.
	 *
	 * @return void
	 */
	public function handle_reset_all() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'themetoggle' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'tt_reset_all', 'tt_nonce' );

		if ( empty( $_POST['tt_confirm_reset'] ) ) {
			wp_safe_redirect( add_query_arg( 'tt_notice', 'reset_unconfirmed', $this->get_page_url( 'users' ) ) );
			exit;
		}

		$count = $this->count_users_with_preference();

		delete_metadata( 'user', 0, Theme_Resolver::META_KEY, '', true );

		wp_safe_redirect(
			add_query_arg(
				array(
					'tt_notice' => 'reset_all',
					'tt_count'  => $count,
				),
				$this->get_page_url( 'users' )
			)
		);
		exit;
	}
}
