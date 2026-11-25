<?php
/**
 * Settings page wrapper.
 *
 * @package ThemeToggle
 *
 * @var array $view Prepared by Admin::render_page().
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Views are required from class methods, so these variables are function-scoped, not globals.

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Display-only notice flags.
$tt_notice = isset( $_GET['tt_notice'] ) ? sanitize_key( wp_unslash( $_GET['tt_notice'] ) ) : '';
$tt_count  = isset( $_GET['tt_count'] ) ? absint( $_GET['tt_count'] ) : 0;
// phpcs:enable WordPress.Security.NonceVerification.Recommended
?>
<div class="wrap tt-settings">
	<h1><?php esc_html_e( 'ThemeToggle', 'themetoggle' ); ?></h1>

	<?php settings_errors(); ?>

	<?php if ( 'reset_all' === $tt_notice ) : ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %d: Number of users. */
					_n( 'Removed the saved theme preference of %d user.', 'Removed the saved theme preferences of %d users.', $tt_count, 'themetoggle' ),
					$tt_count
				)
			);
			?>
		</p></div>
	<?php elseif ( 'reset_unconfirmed' === $tt_notice ) : ?>
		<div class="notice notice-warning is-dismissible"><p><?php esc_html_e( 'Nothing was reset. Tick the confirmation box to reset all preferences.', 'themetoggle' ); ?></p></div>
	<?php endif; ?>

	<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'ThemeToggle settings sections', 'themetoggle' ); ?>">
		<?php foreach ( $view['tabs'] as $tt_slug => $tt_label ) : ?>
			<a href="<?php echo esc_url( $view['admin']->get_page_url( $tt_slug ) ); ?>" class="nav-tab <?php echo $tt_slug === $view['tab'] ? 'nav-tab-active' : ''; ?>" <?php echo $tt_slug === $view['tab'] ? 'aria-current="page"' : ''; ?>>
				<?php echo esc_html( $tt_label ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<?php
	$tt_views = array(
		'general' => 'settings-general.php',
		'roles'   => 'settings-role-themes.php',
		'users'   => 'settings-users.php',
		'button'  => 'settings-interface.php',
	);

	require \ThemeToggle\PLUGIN_DIR . 'admin/views/' . $tt_views[ $view['tab'] ];
	?>
</div>
