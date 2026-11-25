<?php
/**
 * Access to installed themes.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

use WP_Theme;

defined( 'ABSPATH' ) || exit;

/**
 * Thin, cached wrapper around the WordPress Theme API.
 *
 * Every lookup passes an explicit stylesheet to wp_get_theme(). Calling
 * wp_get_theme() without an argument would call get_stylesheet(), which is
 * filtered by this plugin, and would recurse.
 */
class Theme_Manager {

	/**
	 * Per-request cache: stylesheet => WP_Theme|false.
	 *
	 * @var array<string,WP_Theme|false>
	 */
	private $themes = array();

	/**
	 * Per-request cache of all installed themes.
	 *
	 * @var WP_Theme[]|null
	 */
	private $installed = null;

	/**
	 * Stylesheet of the globally active theme, read from the raw option so it is
	 * never affected by this plugin's own `stylesheet` filter.
	 *
	 * @return string
	 */
	public function get_default_stylesheet() {
		return (string) get_option( 'stylesheet' );
	}

	/**
	 * The globally active theme.
	 *
	 * @return WP_Theme
	 */
	public function get_default_theme() {
		return wp_get_theme( $this->get_default_stylesheet() );
	}

	/**
	 * Returns a usable theme for a stylesheet, or null.
	 *
	 * "Usable" means the directory exists, the stylesheet is a safe relative
	 * path, and WordPress reports no errors (for child themes this includes a
	 * missing parent).
	 *
	 * @param string $stylesheet Theme stylesheet (directory name).
	 * @return WP_Theme|null
	 */
	public function get_theme( $stylesheet ) {
		if ( ! is_string( $stylesheet ) || '' === $stylesheet ) {
			return null;
		}

		if ( ! array_key_exists( $stylesheet, $this->themes ) ) {
			$this->themes[ $stylesheet ] = false;

			if ( 0 === validate_file( $stylesheet ) ) {
				$theme = wp_get_theme( $stylesheet );

				if ( $theme->exists() && ! $theme->errors() ) {
					$this->themes[ $stylesheet ] = $theme;
				}
			}
		}

		return $this->themes[ $stylesheet ] ? $this->themes[ $stylesheet ] : null;
	}

	/**
	 * Whether a theme is installed and usable.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return bool
	 */
	public function is_installed( $stylesheet ) {
		return null !== $this->get_theme( $stylesheet );
	}

	/**
	 * All installed, error-free themes keyed by stylesheet, sorted by name.
	 * Only used on admin screens; never on the theme-resolution path.
	 *
	 * @return WP_Theme[]
	 */
	public function get_installed_themes() {
		if ( null === $this->installed ) {
			$themes = wp_get_themes( array( 'errors' => false ) );

			uasort(
				$themes,
				static function ( WP_Theme $a, WP_Theme $b ) {
					return strnatcasecmp( $a->get( 'Name' ), $b->get( 'Name' ) );
				}
			);

			$this->installed = $themes;

			foreach ( $themes as $stylesheet => $theme ) {
				$this->themes[ $stylesheet ] = $theme;
			}
		}

		return $this->installed;
	}

	/**
	 * Display name for a stylesheet, falling back to the stylesheet itself.
	 *
	 * @param string $stylesheet Theme stylesheet.
	 * @return string
	 */
	public function get_theme_name( $stylesheet ) {
		$theme = $this->get_theme( $stylesheet );

		return $theme ? $theme->display( 'Name', false ) : $stylesheet;
	}

	/**
	 * Human-readable parent description for child themes.
	 *
	 * @param WP_Theme $theme Theme.
	 * @return string Empty for parent/standalone themes.
	 */
	public function get_parent_label( WP_Theme $theme ) {
		$parent = $theme->parent();

		if ( ! $parent ) {
			return '';
		}

		/* translators: %s: Parent theme name. */
		return sprintf( __( 'Child of %s', 'themetoggle' ), $parent->display( 'Name', false ) );
	}
}
