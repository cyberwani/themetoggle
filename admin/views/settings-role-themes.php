<?php
/**
 * Role-based theme assignment tab.
 *
 * @package ThemeToggle
 *
 * @var array $view Prepared by Admin::render_page().
 */

use ThemeToggle\Settings;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Views are required from class methods, so these variables are function-scoped, not globals.

$tt_settings      = $view['settings'];
$tt_roles         = $view['roles']->get_roles();
$tt_installed     = $view['themes']->get_installed_themes();
$tt_default_slug  = $view['themes']->get_default_stylesheet();
$tt_allowed_roles = (array) $tt_settings['allowed_roles'];
$tt_role_themes   = (array) $tt_settings['role_themes'];
$tt_name          = Settings::OPTION_NAME;
$tt_any_allowed   = ! empty( array_intersect( array_keys( $tt_roles ), $tt_allowed_roles ) );
?>
<form method="post" action="options.php" class="tt-roles-form">
	<?php settings_fields( Settings::OPTION_GROUP ); ?>
	<input type="hidden" name="<?php echo esc_attr( $tt_name ); ?>[_tab]" value="roles" />

	<h2><?php esc_html_e( 'User Roles Allowed to Switch Themes', 'themetoggle' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Only users holding at least one of these roles see the profile theme selector. All other users always get the default theme.', 'themetoggle' ); ?></p>

	<fieldset class="tt-role-list">
		<legend class="screen-reader-text"><?php esc_html_e( 'User Roles Allowed to Switch Themes', 'themetoggle' ); ?></legend>
		<?php foreach ( $tt_roles as $tt_role => $tt_role_name ) : ?>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $tt_name ); ?>[allowed_roles][]" value="<?php echo esc_attr( $tt_role ); ?>" data-tt-role-toggle <?php checked( in_array( $tt_role, $tt_allowed_roles, true ) ); ?> />
				<?php echo esc_html( $tt_role_name ); ?> <code><?php echo esc_html( $tt_role ); ?></code>
			</label>
		<?php endforeach; ?>
	</fieldset>

	<h2><?php esc_html_e( 'Role-Based Theme Assignment', 'themetoggle' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Choose which installed themes each allowed role may use. Users with several allowed roles get the combined list. A role appears here once it is ticked above; unticking it hides the role but keeps its themes, which apply again if the role is ticked later.', 'themetoggle' ); ?></p>

	<?php if ( empty( $tt_installed ) ) : ?>
		<p><?php esc_html_e( 'No usable themes are installed.', 'themetoggle' ); ?></p>
	<?php else : ?>
		<p class="tt-no-roles" data-tt-no-roles <?php echo $tt_any_allowed ? 'hidden' : ''; ?>>
			<?php esc_html_e( 'Tick at least one role under "User Roles Allowed to Switch Themes" to assign themes to it.', 'themetoggle' ); ?>
		</p>
		<?php
		/*
		 * Rows of roles that are not allowed are hidden, not removed: their checkboxes
		 * are still submitted, so saving never wipes a hidden role's assignments.
		 */
		?>
		<table class="widefat striped tt-role-table" data-tt-role-table <?php echo $tt_any_allowed ? '' : 'hidden'; ?>>
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'User Role', 'themetoggle' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Allowed Themes', 'themetoggle' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $tt_roles as $tt_role => $tt_role_name ) : ?>
					<?php
					$tt_assigned = isset( $tt_role_themes[ $tt_role ] ) ? (array) $tt_role_themes[ $tt_role ] : array();
					$tt_eligible = in_array( $tt_role, $tt_allowed_roles, true );
					?>
					<tr data-tt-role="<?php echo esc_attr( $tt_role ); ?>" <?php echo $tt_eligible ? '' : 'hidden'; ?>>
						<th scope="row" class="tt-role-table__role">
							<strong><?php echo esc_html( $tt_role_name ); ?></strong>
							<span class="tt-role-table__tools">
								<button type="button" class="button-link" data-tt-select="all"><?php esc_html_e( 'All', 'themetoggle' ); ?></button>
								|
								<button type="button" class="button-link" data-tt-select="none"><?php esc_html_e( 'None', 'themetoggle' ); ?></button>
							</span>
						</th>
						<td>
							<fieldset class="tt-theme-grid">
								<legend class="screen-reader-text">
									<?php
									/* translators: %s: Role name. */
									printf( esc_html__( 'Allowed themes for %s', 'themetoggle' ), esc_html( $tt_role_name ) );
									?>
								</legend>
								<?php foreach ( $tt_installed as $tt_slug => $tt_theme ) : ?>
									<?php $tt_parent = $view['themes']->get_parent_label( $tt_theme ); ?>
									<label class="tt-theme-card">
										<input type="checkbox" name="<?php echo esc_attr( $tt_name ); ?>[role_themes][<?php echo esc_attr( $tt_role ); ?>][]" value="<?php echo esc_attr( $tt_slug ); ?>" <?php checked( in_array( $tt_slug, $tt_assigned, true ) ); ?> />
										<?php if ( $tt_theme->get_screenshot() ) : ?>
											<img src="<?php echo esc_url( $tt_theme->get_screenshot() ); ?>" alt="" loading="lazy" class="tt-theme-card__shot" />
										<?php else : ?>
											<span class="tt-theme-card__shot tt-theme-card__shot--empty" aria-hidden="true"></span>
										<?php endif; ?>
										<span class="tt-theme-card__name"><?php echo esc_html( $tt_theme->display( 'Name', false ) ); ?></span>
										<code class="tt-theme-card__slug"><?php echo esc_html( $tt_slug ); ?></code>
										<?php if ( $tt_parent ) : ?>
											<span class="tt-theme-card__meta"><?php echo esc_html( $tt_parent ); ?></span>
										<?php endif; ?>
										<?php if ( $tt_slug === $tt_default_slug ) : ?>
											<span class="tt-theme-card__meta"><?php esc_html_e( '(site default)', 'themetoggle' ); ?></span>
										<?php endif; ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php submit_button(); ?>
</form>
