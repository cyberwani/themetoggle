<?php
/**
 * Protects site-wide theme data while a user-specific theme is applied.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

defined( 'ABSPATH' ) || exit;

/**
 * Two pieces of core data are keyed by the raw `stylesheet` option, or are
 * global, rather than by the filtered get_stylesheet():
 *
 * 1. get_theme_mods()/set_theme_mod() read and write
 *    `theme_mods_{get_option('stylesheet')}`, i.e. the GLOBAL theme's mods.
 *    Without isolation, a user-selected theme would read the default theme's
 *    settings and, worse, any set_theme_mod() it performs would overwrite the
 *    default theme's settings for every visitor.
 * 2. `sidebars_widgets` is a single site-wide option. retrieve_widgets() (run
 *    by the widget screens and the REST sidebars/widgets endpoints) remaps it
 *    to the sidebars registered by the currently loaded theme and saves it.
 *
 * While a user-specific theme is applied, theme mod reads come from that
 * theme's own `theme_mods_{stylesheet}` row (falling back, read-only, to the
 * default theme's mods if the selected theme has none yet), theme mod writes
 * are redirected to that row, and writes to `sidebars_widgets` are discarded.
 *
 * Known limitation: remove_theme_mods() deletes the option directly and cannot
 * be intercepted; core only calls it from the Customizer, which is excluded.
 */
class Data_Guard {

	/**
	 * Applied stylesheet, or '' when inactive.
	 *
	 * @var string
	 */
	private $applied = '';

	/**
	 * Global stylesheet at activation time.
	 *
	 * @var string
	 */
	private $default = '';

	/**
	 * Activates the guard for this request. Idempotent.
	 *
	 * @param string $applied_stylesheet Stylesheet applied for the user.
	 * @return void
	 */
	public function activate( $applied_stylesheet ) {
		$default = (string) get_option( 'stylesheet' );

		if ( '' !== $this->applied || '' === $default || $default === $applied_stylesheet ) {
			return;
		}

		$this->applied = (string) $applied_stylesheet;
		$this->default = $default;

		add_filter( 'pre_option_theme_mods_' . $this->default, array( $this, 'filter_read_theme_mods' ), 10, 1 );
		add_filter( 'pre_update_option_theme_mods_' . $this->default, array( $this, 'redirect_theme_mods_write' ), 10, 2 );
		add_filter( 'pre_update_option_sidebars_widgets', array( $this, 'discard_sidebars_widgets_write' ), 10, 2 );
	}

	/**
	 * Whether the guard is active.
	 *
	 * @return bool
	 */
	public function is_active() {
		return '' !== $this->applied;
	}

	/**
	 * Serves the applied theme's own mods in place of the default theme's.
	 *
	 * @param mixed $pre Short-circuit value (false = not short-circuited).
	 * @return mixed
	 */
	public function filter_read_theme_mods( $pre ) {
		$mods = get_option( 'theme_mods_' . $this->applied, false );

		if ( is_array( $mods ) ) {
			return $mods;
		}

		/**
		 * Whether a selected theme without its own settings inherits the default
		 * theme's mods (read-only). When false, it starts with empty mods.
		 *
		 * @param bool   $inherit    Default true.
		 * @param string $stylesheet Applied stylesheet.
		 */
		if ( apply_filters( 'themetoggle_inherit_default_theme_mods', true, $this->applied ) ) {
			return $pre;
		}

		return array();
	}

	/**
	 * Writes mods to the applied theme's row and cancels the write to the
	 * default theme's row (returning the old value makes update_option() a no-op).
	 *
	 * @param mixed $value     New value.
	 * @param mixed $old_value Old value.
	 * @return mixed
	 */
	public function redirect_theme_mods_write( $value, $old_value ) {
		update_option( 'theme_mods_' . $this->applied, $value );

		return $old_value;
	}

	/**
	 * Cancels writes to the site-wide widget layout.
	 *
	 * @param mixed $value     New value.
	 * @param mixed $old_value Old value.
	 * @return mixed
	 */
	public function discard_sidebars_widgets_write( $value, $old_value ) {
		unset( $value );

		return $old_value;
	}
}
