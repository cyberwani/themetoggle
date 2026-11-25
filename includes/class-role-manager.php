<?php
/**
 * Role eligibility and role => theme permissions.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Computes which roles a user is eligible through and which themes those roles
 * permit. Eligibility ("allowed_roles") and assignment ("role_themes") are kept
 * as separate settings: a role must be eligible AND have themes assigned.
 */
class Role_Manager {

	/**
	 * Settings (set after construction to break the Settings <-> Role_Manager
	 * dependency cycle).
	 *
	 * @var Settings|null
	 */
	private $settings = null;

	/**
	 * Injects settings.
	 *
	 * @param Settings $settings Settings.
	 * @return void
	 */
	public function set_settings( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * All registered roles (core and third-party) as slug => translated name.
	 *
	 * @return array<string,string>
	 */
	public function get_roles() {
		$roles = array();

		foreach ( wp_roles()->get_names() as $slug => $name ) {
			$roles[ $slug ] = translate_user_role( $name );
		}

		return $roles;
	}

	/**
	 * The user's roles that are eligible to switch themes.
	 *
	 * @param WP_User $user User.
	 * @return string[]
	 */
	public function get_eligible_roles( WP_User $user ) {
		if ( ! $this->settings ) {
			return array();
		}

		return array_values( array_intersect( (array) $user->roles, $this->settings->get_allowed_roles() ) );
	}

	/**
	 * Whether the user holds at least one eligible role.
	 *
	 * @param WP_User $user User.
	 * @return bool
	 */
	public function is_user_eligible( WP_User $user ) {
		return ! empty( $this->get_eligible_roles( $user ) );
	}

	/**
	 * Union of the themes permitted by all of the user's eligible roles,
	 * de-duplicated. Themes assigned to non-eligible roles never count.
	 *
	 * @param WP_User $user User.
	 * @return string[] Stylesheets.
	 */
	public function get_permitted_themes( WP_User $user ) {
		if ( ! $this->settings ) {
			return array();
		}

		$map    = $this->settings->get_role_themes();
		$themes = array();

		foreach ( $this->get_eligible_roles( $user ) as $role ) {
			if ( isset( $map[ $role ] ) && is_array( $map[ $role ] ) ) {
				$themes = array_merge( $themes, $map[ $role ] );
			}
		}

		return array_values( array_unique( array_map( 'strval', $themes ) ) );
	}
}
