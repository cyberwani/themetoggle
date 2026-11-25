<?php
/**
 * Resolves a user's saved preference into an effective theme.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

use WP_Theme;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Separates the saved preference (user meta, never trusted) from the effective
 * theme (computed on every request from current settings, roles and installed
 * themes). Context (frontend/admin) is handled by Theme_Switcher.
 */
class Theme_Resolver {

	/**
	 * User meta key holding the preferred stylesheet.
	 */
	const META_KEY = 'tt_user_theme';

	/** No preference saved. */
	const STATUS_NONE = 'none';

	/** Preference is valid and will be applied. */
	const STATUS_ACTIVE = 'active';

	/** The master switch is off. */
	const STATUS_DISABLED = 'disabled';

	/** The user holds no eligible role. */
	const STATUS_NOT_ELIGIBLE = 'not_eligible';

	/** The theme is not permitted by any eligible role. */
	const STATUS_NOT_ALLOWED = 'not_allowed';

	/** The theme is not installed or is broken (e.g. missing parent). */
	const STATUS_MISSING = 'missing';

	/** The preference equals the globally active theme. */
	const STATUS_IS_DEFAULT = 'is_default';

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Role manager.
	 *
	 * @var Role_Manager
	 */
	private $roles;

	/**
	 * Theme manager.
	 *
	 * @var Theme_Manager
	 */
	private $themes;

	/**
	 * Per-request cache: user ID => state.
	 *
	 * @var array<int,array>
	 */
	private $cache = array();

	/**
	 * Constructor.
	 *
	 * @param Settings      $settings Settings.
	 * @param Role_Manager  $roles    Role manager.
	 * @param Theme_Manager $themes   Theme manager.
	 */
	public function __construct( Settings $settings, Role_Manager $roles, Theme_Manager $themes ) {
		$this->settings = $settings;
		$this->roles    = $roles;
		$this->themes   = $themes;
	}

	/**
	 * Registers cache invalidation hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'set_user_role', array( $this, 'flush_user' ) );
		add_action( 'add_user_role', array( $this, 'flush_user' ) );
		add_action( 'remove_user_role', array( $this, 'flush_user' ) );
		add_action( 'added_user_meta', array( $this, 'flush_on_meta_change' ), 10, 3 );
		add_action( 'updated_user_meta', array( $this, 'flush_on_meta_change' ), 10, 3 );
		add_action( 'deleted_user_meta', array( $this, 'flush_on_meta_change' ), 10, 3 );
		add_action( 'update_option_' . Settings::OPTION_NAME, array( $this, 'flush_all' ) );
	}

	/**
	 * Saved, sanitized preference (may be invalid).
	 *
	 * @param int $user_id User ID.
	 * @return string Stylesheet or empty string.
	 */
	public function get_saved_preference( $user_id ) {
		$value = get_user_meta( (int) $user_id, self::META_KEY, true );

		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Computes the user's theme state.
	 *
	 * @param WP_User $user User.
	 * @return array {
	 *     @type string        $preference Saved stylesheet ('' if none).
	 *     @type string        $status     One of the STATUS_* constants.
	 *     @type WP_Theme|null $theme      Theme to apply when status is active.
	 * }
	 */
	public function get_user_state( WP_User $user ) {
		$user_id = (int) $user->ID;

		if ( ! isset( $this->cache[ $user_id ] ) ) {
			$this->cache[ $user_id ] = $this->compute_state( $user );
		}

		return $this->cache[ $user_id ];
	}

	/**
	 * Uncached state computation, in the documented resolution order.
	 *
	 * @param WP_User $user User.
	 * @return array
	 */
	private function compute_state( WP_User $user ) {
		$preference = $this->get_saved_preference( $user->ID );
		$state      = array(
			'preference' => $preference,
			'status'     => self::STATUS_NONE,
			'theme'      => null,
		);

		if ( '' === $preference ) {
			return $state;
		}

		if ( ! $this->settings->is_enabled() ) {
			$state['status'] = self::STATUS_DISABLED;
			return $state;
		}

		if ( ! $this->roles->is_user_eligible( $user ) ) {
			$state['status'] = self::STATUS_NOT_ELIGIBLE;
			return $state;
		}

		$theme = $this->themes->get_theme( $preference );

		if ( ! $theme ) {
			$state['status'] = self::STATUS_MISSING;
			return $state;
		}

		if ( ! in_array( $preference, $this->roles->get_permitted_themes( $user ), true ) ) {
			$state['status'] = self::STATUS_NOT_ALLOWED;
			return $state;
		}

		if ( $theme->get_stylesheet() === $this->themes->get_default_stylesheet() ) {
			$state['status'] = self::STATUS_IS_DEFAULT;
			return $state;
		}

		$state['status'] = self::STATUS_ACTIVE;
		$state['theme']  = $theme;

		return $state;
	}

	/**
	 * Whether a stylesheet may be selected by a user right now.
	 *
	 * @param WP_User $user       User.
	 * @param string  $stylesheet Candidate stylesheet.
	 * @return bool
	 */
	public function can_user_select( WP_User $user, $stylesheet ) {
		return array_key_exists( (string) $stylesheet, $this->get_selectable_themes( $user ) );
	}

	/**
	 * Themes the user may choose from, excluding the globally active theme
	 * (which is always offered as "Default Theme").
	 *
	 * @param WP_User $user User.
	 * @return WP_Theme[] Keyed by stylesheet.
	 */
	public function get_selectable_themes( WP_User $user ) {
		if ( ! $this->settings->is_enabled() || ! $this->roles->is_user_eligible( $user ) ) {
			return array();
		}

		$default = $this->themes->get_default_stylesheet();
		$themes  = array();

		foreach ( $this->roles->get_permitted_themes( $user ) as $stylesheet ) {
			$theme = $this->themes->get_theme( $stylesheet );

			if ( $theme && $stylesheet !== $default ) {
				$themes[ $stylesheet ] = $theme;
			}
		}

		uasort(
			$themes,
			static function ( WP_Theme $a, WP_Theme $b ) {
				return strnatcasecmp( $a->get( 'Name' ), $b->get( 'Name' ) );
			}
		);

		return $themes;
	}

	/**
	 * Saves a preference after validating it. '' clears the preference.
	 *
	 * @param WP_User $user       User.
	 * @param string  $stylesheet Stylesheet or ''.
	 * @return bool True on success (including no-op), false when rejected.
	 */
	public function save_preference( WP_User $user, $stylesheet ) {
		$stylesheet = (string) $stylesheet;

		if ( '' === $stylesheet ) {
			delete_user_meta( $user->ID, self::META_KEY );
			return true;
		}

		if ( ! $this->can_user_select( $user, $stylesheet ) ) {
			return false;
		}

		if ( $this->get_saved_preference( $user->ID ) !== $stylesheet ) {
			update_user_meta( $user->ID, self::META_KEY, $stylesheet );
		}

		return true;
	}

	/**
	 * Clears cached state for one user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function flush_user( $user_id ) {
		unset( $this->cache[ (int) $user_id ] );
	}

	/**
	 * Clears cached state when this plugin's meta key changes.
	 *
	 * @param int|int[] $meta_ids  Meta ID(s).
	 * @param int       $object_id User ID.
	 * @param string    $meta_key  Meta key.
	 * @return void
	 */
	public function flush_on_meta_change( $meta_ids, $object_id, $meta_key ) {
		unset( $meta_ids );

		if ( self::META_KEY === $meta_key ) {
			$this->flush_user( $object_id );
		}
	}

	/**
	 * Clears all cached state.
	 *
	 * @return void
	 */
	public function flush_all() {
		$this->cache = array();
	}
}
