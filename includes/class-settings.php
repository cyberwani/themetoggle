<?php
/**
 * Settings storage, defaults and validation.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

defined( 'ABSPATH' ) || exit;

/**
 * Wraps the single `tt_settings` option.
 */
class Settings {

	/**
	 * Option name.
	 */
	const OPTION_NAME = 'tt_settings';

	/**
	 * Settings API group.
	 */
	const OPTION_GROUP = 'tt_settings_group';

	/**
	 * Allowed button positions.
	 */
	const POSITIONS = array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' );

	/**
	 * Allowed button sizes.
	 */
	const SIZES = array( 'small', 'medium', 'large' );

	/**
	 * Allowed button icons.
	 */
	const ICONS = array( 'none', 'undo', 'home', 'palette' );

	/**
	 * Keys owned by each settings tab. Saving a tab only touches its own keys.
	 */
	const TAB_KEYS = array(
		'general' => array( 'enabled', 'enable_admin' ),
		'roles'   => array( 'allowed_roles', 'role_themes' ),
		'button'  => array(
			'show_switch_button',
			'show_switch_button_frontend',
			'show_switch_button_admin',
			'button_position',
			'button_label',
			'button_bg_color',
			'button_text_color',
			'button_radius',
			'button_size',
			'button_icon',
		),
	);

	/**
	 * Request-level cache of the merged settings.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Theme manager, used to validate theme slugs on save.
	 *
	 * @var Theme_Manager
	 */
	private $themes;

	/**
	 * Role manager, used to validate role slugs on save.
	 *
	 * @var Role_Manager
	 */
	private $roles;

	/**
	 * Constructor.
	 *
	 * @param Theme_Manager $themes Theme manager.
	 * @param Role_Manager  $roles  Role manager.
	 */
	public function __construct( Theme_Manager $themes, Role_Manager $roles ) {
		$this->themes = $themes;
		$this->roles  = $roles;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'update_option_' . self::OPTION_NAME, array( $this, 'flush_cache' ) );
		add_action( 'add_option_' . self::OPTION_NAME, array( $this, 'flush_cache' ) );
	}

	/**
	 * Default settings. Static because the activation hook needs them before the
	 * plugin object graph exists; it is a pure function.
	 *
	 * An empty button label means "use the translatable default label".
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'                     => true,
			'enable_admin'                => false,
			'allowed_roles'               => array(),
			'role_themes'                 => array(),
			'show_switch_button'          => true,
			'show_switch_button_frontend' => true,
			'show_switch_button_admin'    => true,
			'button_position'             => 'bottom-right',
			'button_label'                => '',
			'button_bg_color'             => '#1d2327',
			'button_text_color'           => '#ffffff',
			'button_radius'               => 4,
			'button_size'                 => 'medium',
			'button_icon'                 => 'undo',
		);
	}

	/**
	 * Registers the option with the Settings API.
	 *
	 * @return void
	 */
	public function register_setting() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::get_defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Returns all settings merged with defaults.
	 *
	 * @return array
	 */
	public function get_all() {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION_NAME, array() );
			$this->cache = array_merge( self::get_defaults(), is_array( $stored ) ? $stored : array() );
		}

		return $this->cache;
	}

	/**
	 * Returns one setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed|null
	 */
	public function get( $key ) {
		$all = $this->get_all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : null;
	}

	/**
	 * Returns one setting re-validated with the same rules as on save.
	 *
	 * Used where a stored value reaches output (for example inside a style
	 * attribute), so a value written to the database without going through
	 * sanitize() can never be printed as-is.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public function get_clean( $key ) {
		if ( ! array_key_exists( $key, self::get_defaults() ) ) {
			return null;
		}

		return $this->sanitize_value( $key, $this->get( $key ) );
	}

	/**
	 * Whether the master switch is on.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (bool) $this->get( 'enabled' );
	}

	/**
	 * Whether switching applies to wp-admin requests.
	 *
	 * @return bool
	 */
	public function is_admin_enabled() {
		return (bool) $this->get( 'enable_admin' );
	}

	/**
	 * Roles that may switch themes.
	 *
	 * @return string[]
	 */
	public function get_allowed_roles() {
		$roles = $this->get( 'allowed_roles' );

		return is_array( $roles ) ? array_values( array_map( 'strval', $roles ) ) : array();
	}

	/**
	 * Role => theme stylesheet list.
	 *
	 * @return array<string,string[]>
	 */
	public function get_role_themes() {
		$map = $this->get( 'role_themes' );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * Effective button label.
	 *
	 * @return string
	 */
	public function get_button_label() {
		$label = (string) $this->get_clean( 'button_label' );

		return '' !== $label ? $label : __( 'Switch to Default Theme', 'themetoggle' );
	}

	/**
	 * Clears the request cache.
	 *
	 * @return void
	 */
	public function flush_cache() {
		$this->cache = null;
	}

	/**
	 * Sanitizes the option.
	 *
	 * When the payload contains `_tab`, only the keys owned by that tab are
	 * replaced; the rest of the stored settings are preserved. Without `_tab`
	 * (programmatic updates, or the second pass WordPress performs when the
	 * option is first created) the full payload is validated.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public function sanitize( $input ) {
		$input   = is_array( $input ) ? $input : array();
		$current = $this->get_all();

		if ( isset( $input['_tab'] ) && is_string( $input['_tab'] ) && isset( self::TAB_KEYS[ $input['_tab'] ] ) ) {
			$keys = self::TAB_KEYS[ $input['_tab'] ];
		} else {
			$keys = array_keys( self::get_defaults() );
		}

		$output = $current;

		foreach ( $keys as $key ) {
			$output[ $key ] = $this->sanitize_value( $key, isset( $input[ $key ] ) ? $input[ $key ] : null );
		}

		// Drop unknown keys.
		return array_intersect_key( $output, self::get_defaults() );
	}

	/**
	 * Sanitizes a single value. A null value means "not submitted", which for
	 * checkboxes and multi-selects means "off / empty".
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Raw value.
	 * @return mixed
	 */
	private function sanitize_value( $key, $value ) {
		$defaults = self::get_defaults();

		switch ( $key ) {
			case 'enabled':
			case 'enable_admin':
			case 'show_switch_button':
			case 'show_switch_button_frontend':
			case 'show_switch_button_admin':
				return ! empty( $value );

			case 'allowed_roles':
				return $this->sanitize_roles( $value );

			case 'role_themes':
				return $this->sanitize_role_themes( $value );

			case 'button_position':
				return $this->sanitize_choice( $value, self::POSITIONS, $defaults[ $key ] );

			case 'button_size':
				return $this->sanitize_choice( $value, self::SIZES, $defaults[ $key ] );

			case 'button_icon':
				return $this->sanitize_choice( $value, self::ICONS, $defaults[ $key ] );

			case 'button_label':
				$label = is_string( $value ) ? sanitize_text_field( $value ) : '';
				return mb_substr( $label, 0, 60 );

			case 'button_bg_color':
			case 'button_text_color':
				$color = is_string( $value ) ? sanitize_hex_color( $value ) : null;
				return $color ? $color : $defaults[ $key ];

			case 'button_radius':
				return is_numeric( $value ) ? max( 0, min( 50, absint( $value ) ) ) : $defaults[ $key ];
		}

		return $defaults[ $key ];
	}

	/**
	 * Returns $value if it is one of $choices, otherwise $fallback.
	 *
	 * @param mixed    $value    Raw value.
	 * @param string[] $choices  Allowed values.
	 * @param string   $fallback Fallback.
	 * @return string
	 */
	private function sanitize_choice( $value, array $choices, $fallback ) {
		return ( is_string( $value ) && in_array( $value, $choices, true ) ) ? $value : $fallback;
	}

	/**
	 * Keeps only registered role slugs. Role slugs are matched exactly (not via
	 * sanitize_key()) because third-party roles are not guaranteed lowercase.
	 *
	 * @param mixed $value Raw value.
	 * @return string[]
	 */
	private function sanitize_roles( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$known = array_keys( $this->roles->get_roles() );
		$roles = array();

		foreach ( $value as $role ) {
			if ( is_string( $role ) && in_array( $role, $known, true ) ) {
				$roles[] = $role;
			}
		}

		return array_values( array_unique( $roles ) );
	}

	/**
	 * Keeps only registered roles mapped to installed, error-free themes.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string,string[]>
	 */
	private function sanitize_role_themes( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$known_roles = array_keys( $this->roles->get_roles() );
		$map         = array();

		foreach ( $value as $role => $themes ) {
			if ( ! is_string( $role ) || ! in_array( $role, $known_roles, true ) || ! is_array( $themes ) ) {
				continue;
			}

			$clean = array();

			foreach ( $themes as $stylesheet ) {
				if ( is_string( $stylesheet ) && $this->themes->is_installed( $stylesheet ) ) {
					$clean[] = $stylesheet;
				}
			}

			$clean = array_values( array_unique( $clean ) );

			if ( ! empty( $clean ) ) {
				$map[ $role ] = $clean;
			}
		}

		return $map;
	}
}
