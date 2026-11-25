<?php
/**
 * General settings tab.
 *
 * @package ThemeToggle
 *
 * @var array $view Prepared by Admin::render_page().
 */

use ThemeToggle\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Views are required from class methods, so these variables are function-scoped, not globals.

$tt_settings = $view['settings'];
$tt_default  = $view['themes']->get_default_theme();
$tt_parent   = $view['themes']->get_parent_label( $tt_default );
?>
<form method="post" action="options.php">
	<?php settings_fields( Settings::OPTION_GROUP ); ?>
	<input type="hidden" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[_tab]" value="general" />

	<h2><?php esc_html_e( 'General Settings', 'themetoggle' ); ?></h2>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Theme Switching', 'themetoggle' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[enabled]" value="1" <?php checked( $tt_settings['enabled'] ); ?> />
					<?php esc_html_e( 'Enable user-specific theme switching', 'themetoggle' ); ?>
				</label>
				<p class="description"><?php esc_html_e( 'When disabled, everyone sees the globally active theme. Saved user preferences are kept and apply again when switching is re-enabled.', 'themetoggle' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Theme Switching in Admin Panel', 'themetoggle' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION_NAME ); ?>[enable_admin]" value="1" <?php checked( $tt_settings['enable_admin'] ); ?> />
					<?php esc_html_e( 'Load the user\'s selected theme on wp-admin requests', 'themetoggle' ); ?>
				</label>
				<div class="tt-callout">
					<p><strong><?php esc_html_e( 'What this actually changes:', 'themetoggle' ); ?></strong></p>
					<ul>
						<li><?php esc_html_e( 'The selected theme\'s functions.php (and any admin pages, menus, meta boxes or editor features it registers) is loaded instead of the default theme\'s.', 'themetoggle' ); ?></li>
						<li><?php esc_html_e( 'The block editor uses the selected theme\'s theme.json, editor styles and templates.', 'themetoggle' ); ?></li>
						<li><?php esc_html_e( 'The WordPress admin interface itself is NOT restyled by the frontend theme.', 'themetoggle' ); ?></li>
						<li><?php esc_html_e( 'For safety the default theme is always used on: Themes, Theme File Editor, Add Themes, Site Editor, Customizer, Widgets, Menus, Updates, and this settings page.', 'themetoggle' ); ?></li>
					</ul>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Default Theme', 'themetoggle' ); ?></th>
			<td>
				<div class="tt-default-theme">
					<?php if ( $tt_default->get_screenshot() ) : ?>
						<img src="<?php echo esc_url( $tt_default->get_screenshot() ); ?>" alt="" class="tt-default-theme__shot" />
					<?php endif; ?>
					<div>
						<p><strong><?php echo esc_html( $tt_default->display( 'Name', false ) ); ?></strong> <?php echo esc_html( $tt_default->get( 'Version' ) ); ?></p>
						<p>
							<?php esc_html_e( 'Stylesheet directory:', 'themetoggle' ); ?>
							<code><?php echo esc_html( $tt_default->get_stylesheet() ); ?></code>
						</p>
						<?php if ( $tt_parent ) : ?>
							<p><?php echo esc_html( $tt_parent ); ?> (<code><?php echo esc_html( $tt_default->get_template() ); ?></code>)</p>
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Detected automatically. ThemeToggle never changes the globally active theme; change it under Appearance → Themes.', 'themetoggle' ); ?></p>
					</div>
				</div>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Fallback behavior', 'themetoggle' ); ?></th>
			<td>
				<p><?php esc_html_e( 'The default theme is always used when a user has no selection, is not authorized, or has a saved theme that is invalid, no longer permitted, or no longer installed.', 'themetoggle' ); ?></p>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>
