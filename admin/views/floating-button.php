<?php
/**
 * Floating button / preview bar view.
 *
 * @package ThemeToggle
 *
 * @var array $view Prepared by Floating_Button::render().
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Views are required from class methods, so these variables are function-scoped, not globals.

$tt_icons    = array(
	'undo'    => '<path d="M12 5V2L7 6l5 4V7a6 6 0 1 1-6 6H4a8 8 0 1 0 8-8z"/>',
	'home'    => '<path d="M12 3 2 12h3v8h5v-6h4v6h5v-8h3z"/>',
	'palette' => '<path d="M12 3a9 9 0 0 0 0 18 1.5 1.5 0 0 0 1.1-2.5 1.5 1.5 0 0 1 1.1-2.5H16a5 5 0 0 0 5-5c0-4.4-4-8-9-8zM6.5 12a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3-4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm5 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm3 4a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/>',
);
$tt_svg_kses = array(
	'path' => array( 'd' => true ),
);
?>
<div class="<?php echo esc_attr( implode( ' ', $view['classes'] ) ); ?>" style="<?php echo esc_attr( $view['style'] ); ?>" data-themetoggle>
	<?php if ( 'preview' === $view['mode'] ) : ?>
		<div class="tt-floating__preview" role="region" aria-label="<?php esc_attr_e( 'Theme preview', 'themetoggle' ); ?>">
			<span class="tt-floating__text">
				<?php
				/* translators: %s: Theme name. */
				printf( esc_html__( 'Previewing: %s', 'themetoggle' ), esc_html( $view['theme']->display( 'Name', false ) ) );
				?>
			</span>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tt_select_theme" />
				<input type="hidden" name="tt_theme" value="<?php echo esc_attr( $view['theme']->get_stylesheet() ); ?>" />
				<input type="hidden" name="tt_redirect" value="<?php echo esc_attr( $view['redirect'] ); ?>" />
				<?php wp_nonce_field( 'tt_select_theme', 'tt_nonce', false ); ?>
				<button type="submit" class="tt-floating__button"><?php esc_html_e( 'Use this theme', 'themetoggle' ); ?></button>
			</form>
			<a class="tt-floating__button tt-floating__button--secondary" href="<?php echo esc_url( $view['redirect'] ); ?>"><?php esc_html_e( 'Exit preview', 'themetoggle' ); ?></a>
		</div>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tt_reset_theme" />
			<input type="hidden" name="tt_redirect" value="<?php echo esc_attr( $view['redirect'] ); ?>" />
			<?php wp_nonce_field( 'tt_reset_theme', 'tt_nonce', false ); ?>
			<button type="submit" class="tt-floating__button" title="<?php echo esc_attr( $view['theme']->display( 'Name', false ) ); ?>">
				<?php if ( isset( $tt_icons[ $view['icon'] ] ) ) : ?>
					<svg class="tt-floating__icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><?php echo wp_kses( $tt_icons[ $view['icon'] ], $tt_svg_kses ); ?></svg>
				<?php endif; ?>
				<span><?php echo esc_html( $view['label'] ); ?></span>
			</button>
		</form>
	<?php endif; ?>
</div>
