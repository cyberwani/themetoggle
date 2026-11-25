<?php
/**
 * User profiles tab: explanation and reset-all tool.
 *
 * @package ThemeToggle
 *
 * @var array $view Prepared by Admin::render_page().
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Views are required from class methods, so these variables are function-scoped, not globals.
?>
<h2><?php esc_html_e( 'User Profile Theme Selector', 'themetoggle' ); ?></h2>

<p><?php esc_html_e( 'Eligible users choose their theme under Users → Profile → Personal Theme Settings. The selector is only shown when theme switching is enabled, the user holds an allowed role, and at least one permitted theme (other than the site default) is installed.', 'themetoggle' ); ?></p>
<p><?php esc_html_e( 'Administrators can change another user\'s choice on that user\'s Edit User screen, and reset several users at once from Users → All Users with the "Reset selected users\' themes" bulk action.', 'themetoggle' ); ?></p>
<p><?php esc_html_e( 'A saved choice is a preference, not a permission: it is re-validated on every request against the user\'s current roles and the installed themes. Preferences that become invalid are ignored (the default theme is used) but kept, so they apply again if they become valid.', 'themetoggle' ); ?></p>

<p>
	<a class="button" href="<?php echo esc_url( admin_url( 'profile.php#themetoggle' ) ); ?>"><?php esc_html_e( 'Open my profile', 'themetoggle' ); ?></a>
	<a class="button" href="<?php echo esc_url( admin_url( 'users.php' ) ); ?>"><?php esc_html_e( 'View users', 'themetoggle' ); ?></a>
</p>

<hr />

<h2><?php esc_html_e( 'Reset All User Theme Preferences', 'themetoggle' ); ?></h2>
<p>
	<?php
	echo esc_html(
		sprintf(
			/* translators: %d: Number of users. */
			_n( '%d user currently has a saved theme preference.', '%d users currently have a saved theme preference.', (int) $view['pref_count'], 'themetoggle' ),
			(int) $view['pref_count']
		)
	);
	?>
</p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tt-reset-all">
	<input type="hidden" name="action" value="tt_reset_all" />
	<?php wp_nonce_field( 'tt_reset_all', 'tt_nonce' ); ?>
	<p>
		<label>
			<input type="checkbox" name="tt_confirm_reset" value="1" required />
			<?php esc_html_e( 'I understand this permanently deletes every user\'s saved theme preference.', 'themetoggle' ); ?>
		</label>
	</p>
	<?php submit_button( __( 'Reset All User Theme Preferences', 'themetoggle' ), 'delete', 'tt_reset_all_submit', false, array( 'disabled' => empty( $view['pref_count'] ) ) ); ?>
</form>
