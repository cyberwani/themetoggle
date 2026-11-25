<?php
/**
 * Floating button settings tab.
 *
 * @package ThemeToggle
 *
 * @var array $view Prepared by Admin::render_page().
 */

use ThemeToggle\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Views are required from class methods, so these variables are function-scoped, not globals.

$tt_settings  = $view['settings'];
$tt_name      = Settings::OPTION_NAME;
$tt_positions = array(
	'bottom-right' => __( 'Bottom Right', 'themetoggle' ),
	'bottom-left'  => __( 'Bottom Left', 'themetoggle' ),
	'top-right'    => __( 'Top Right', 'themetoggle' ),
	'top-left'     => __( 'Top Left', 'themetoggle' ),
);
$tt_sizes     = array(
	'small'  => __( 'Small', 'themetoggle' ),
	'medium' => __( 'Medium', 'themetoggle' ),
	'large'  => __( 'Large', 'themetoggle' ),
);
$tt_icons     = array(
	'none'    => __( 'No icon', 'themetoggle' ),
	'undo'    => __( 'Undo arrow', 'themetoggle' ),
	'home'    => __( 'House', 'themetoggle' ),
	'palette' => __( 'Palette', 'themetoggle' ),
);
$tt_toggles   = array(
	'show_switch_button'          => array( __( 'Enable floating button', 'themetoggle' ), __( 'Show a button that switches the user back to the default theme.', 'themetoggle' ) ),
	'show_switch_button_frontend' => array( __( 'Frontend visibility', 'themetoggle' ), __( 'Show the button on the public site.', 'themetoggle' ) ),
	'show_switch_button_admin'    => array( __( 'Admin visibility', 'themetoggle' ), __( 'Show the button in wp-admin. It only appears when admin theme switching is enabled, because otherwise no custom theme is applied there.', 'themetoggle' ) ),
);
?>
<form method="post" action="options.php">
	<?php settings_fields( Settings::OPTION_GROUP ); ?>
	<input type="hidden" name="<?php echo esc_attr( $tt_name ); ?>[_tab]" value="button" />

	<h2><?php esc_html_e( 'Floating Button Settings', 'themetoggle' ); ?></h2>
	<p class="description"><?php esc_html_e( 'The button is only shown to logged-in users whose valid custom theme is actually applied to the current page.', 'themetoggle' ); ?></p>

	<table class="form-table" role="presentation">
		<?php foreach ( $tt_toggles as $tt_key => $tt_text ) : ?>
			<tr>
				<th scope="row"><?php echo esc_html( $tt_text[0] ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="<?php echo esc_attr( $tt_name . '[' . $tt_key . ']' ); ?>" value="1" <?php checked( $tt_settings[ $tt_key ] ); ?> />
						<?php echo esc_html( $tt_text[1] ); ?>
					</label>
				</td>
			</tr>
		<?php endforeach; ?>
		<tr>
			<th scope="row"><label for="tt-button-position"><?php esc_html_e( 'Button position', 'themetoggle' ); ?></label></th>
			<td>
				<select id="tt-button-position" name="<?php echo esc_attr( $tt_name ); ?>[button_position]">
					<?php foreach ( $tt_positions as $tt_value => $tt_label ) : ?>
						<option value="<?php echo esc_attr( $tt_value ); ?>" <?php selected( $tt_settings['button_position'], $tt_value ); ?>><?php echo esc_html( $tt_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="tt-button-label"><?php esc_html_e( 'Button label', 'themetoggle' ); ?></label></th>
			<td>
				<input type="text" id="tt-button-label" class="regular-text" maxlength="60" name="<?php echo esc_attr( $tt_name ); ?>[button_label]" value="<?php echo esc_attr( $tt_settings['button_label'] ); ?>" placeholder="<?php esc_attr_e( 'Switch to Default Theme', 'themetoggle' ); ?>" />
				<p class="description"><?php esc_html_e( 'Leave empty to use the translatable default label.', 'themetoggle' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="tt-button-bg"><?php esc_html_e( 'Background color', 'themetoggle' ); ?></label></th>
			<td><input type="text" id="tt-button-bg" class="tt-color" name="<?php echo esc_attr( $tt_name ); ?>[button_bg_color]" value="<?php echo esc_attr( $tt_settings['button_bg_color'] ); ?>" data-default-color="#1d2327" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="tt-button-color"><?php esc_html_e( 'Text color', 'themetoggle' ); ?></label></th>
			<td><input type="text" id="tt-button-color" class="tt-color" name="<?php echo esc_attr( $tt_name ); ?>[button_text_color]" value="<?php echo esc_attr( $tt_settings['button_text_color'] ); ?>" data-default-color="#ffffff" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="tt-button-radius"><?php esc_html_e( 'Border radius (px)', 'themetoggle' ); ?></label></th>
			<td><input type="number" id="tt-button-radius" class="small-text" min="0" max="50" step="1" name="<?php echo esc_attr( $tt_name ); ?>[button_radius]" value="<?php echo esc_attr( (string) $tt_settings['button_radius'] ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="tt-button-size"><?php esc_html_e( 'Button size', 'themetoggle' ); ?></label></th>
			<td>
				<select id="tt-button-size" name="<?php echo esc_attr( $tt_name ); ?>[button_size]">
					<?php foreach ( $tt_sizes as $tt_value => $tt_label ) : ?>
						<option value="<?php echo esc_attr( $tt_value ); ?>" <?php selected( $tt_settings['button_size'], $tt_value ); ?>><?php echo esc_html( $tt_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="tt-button-icon"><?php esc_html_e( 'Icon', 'themetoggle' ); ?></label></th>
			<td>
				<select id="tt-button-icon" name="<?php echo esc_attr( $tt_name ); ?>[button_icon]">
					<?php foreach ( $tt_icons as $tt_value => $tt_label ) : ?>
						<option value="<?php echo esc_attr( $tt_value ); ?>" <?php selected( $tt_settings['button_icon'], $tt_value ); ?>><?php echo esc_html( $tt_label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>
