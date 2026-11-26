<?php
/**
 * Profile section view.
 *
 * @package ThemeToggle
 *
 * @var array $view Prepared by User_Profile::render().
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Views are required from class methods, so these variables are function-scoped, not globals.

$tt_user    = $view['user'];
$tt_default = $view['default'];
?>
<h2 id="themetoggle"><?php esc_html_e( 'Personal Theme Settings', 'themetoggle' ); ?></h2>
<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Choose Your Website Theme', 'themetoggle' ); ?></th>
		<td>
			<?php wp_nonce_field( 'tt_save_user_theme_' . $tt_user->ID, 'tt_user_theme_nonce' ); ?>
			<p>
				<strong><?php esc_html_e( 'Current Selection:', 'themetoggle' ); ?></strong>
				<?php echo esc_html( $view['current'] ); ?>
			</p>

			<?php if ( $view['has_stale'] ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<?php
						printf(
							/* translators: %s: Theme name. */
							esc_html__( 'The saved theme "%s" is currently not available for this account, so the default theme is used. The saved preference is kept and will apply again if it becomes available.', 'themetoggle' ),
							esc_html( $view['stale_name'] )
						);
						?>
					</p>
				</div>
			<?php endif; ?>

			<fieldset class="tt-theme-choices">
				<legend class="screen-reader-text"><?php esc_html_e( 'Choose Your Website Theme', 'themetoggle' ); ?></legend>

				<?php if ( $view['has_stale'] ) : ?>
					<label class="tt-theme-choice">
						<input type="radio" name="tt_user_theme" value="<?php echo esc_attr( $view['keep_value'] ); ?>" <?php checked( $view['checked'], $view['keep_value'] ); ?> />
						<span class="tt-theme-choice__body">
							<span class="tt-theme-choice__name">
								<?php
								/* translators: %s: Theme name. */
								printf( esc_html__( 'Keep saved preference (%s, currently unavailable)', 'themetoggle' ), esc_html( $view['stale_name'] ) );
								?>
							</span>
						</span>
					</label>
				<?php endif; ?>

				<label class="tt-theme-choice">
					<input type="radio" name="tt_user_theme" value="" <?php checked( $view['checked'], '' ); ?> />
					<?php if ( $tt_default->get_screenshot() ) : ?>
						<img class="tt-theme-choice__shot" src="<?php echo esc_url( $tt_default->get_screenshot() ); ?>" alt="" loading="lazy" />
					<?php endif; ?>
					<span class="tt-theme-choice__body">
						<span class="tt-theme-choice__name"><?php esc_html_e( 'Default Theme', 'themetoggle' ); ?></span>
						<span class="tt-theme-choice__meta"><?php echo esc_html( $tt_default->display( 'Name', false ) ); ?></span>
					</span>
				</label>

				<?php foreach ( $view['themes'] as $tt_stylesheet => $tt_theme ) : ?>
					<?php $tt_parent = $view['manager']->get_parent_label( $tt_theme ); ?>
					<label class="tt-theme-choice">
						<input type="radio" name="tt_user_theme" value="<?php echo esc_attr( $tt_stylesheet ); ?>" <?php checked( $view['checked'], $tt_stylesheet ); ?> />
						<?php if ( $tt_theme->get_screenshot() ) : ?>
							<img class="tt-theme-choice__shot" src="<?php echo esc_url( $tt_theme->get_screenshot() ); ?>" alt="" loading="lazy" />
						<?php endif; ?>
						<span class="tt-theme-choice__body">
							<span class="tt-theme-choice__name"><?php echo esc_html( $tt_theme->display( 'Name', false ) ); ?></span>
							<span class="tt-theme-choice__meta">
								<?php
								if ( $view['is_self'] ) {
									esc_html_e( 'Available for your role', 'themetoggle' );
								} else {
									esc_html_e( 'Available for this user\'s role', 'themetoggle' );
								}
								?>
								<?php if ( $tt_parent ) : ?>
									&middot; <?php echo esc_html( $tt_parent ); ?>
								<?php endif; ?>
							</span>
							<?php if ( $view['is_self'] ) : ?>
								<a class="tt-theme-choice__preview" href="<?php echo esc_url( $view['preview']->get_preview_url( $tt_stylesheet ) ); ?>" target="_blank" rel="noopener">
									<?php esc_html_e( 'Preview before applying', 'themetoggle' ); ?>
									<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'themetoggle' ); ?></span>
								</a>
							<?php endif; ?>
						</span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<p class="description">
				<?php
				if ( $view['is_self'] ) {
					esc_html_e( 'The selected theme applies only to your own logged-in sessions. Visitors and other users keep seeing the site default theme.', 'themetoggle' );
				} else {
					esc_html_e( 'The selected theme applies only to this user\'s logged-in sessions.', 'themetoggle' );
				}
				?>
			</p>
		</td>
	</tr>
</table>
