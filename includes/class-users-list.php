<?php
/**
 * Users → All Users integration: column, sorting and bulk reset.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

use WP_User_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the saved preference next to the effective result, so an invalid
 * preference is never presented as the active theme.
 *
 * Performance: the list table queries users with 'fields' => 'all_with_meta',
 * which primes the user meta cache, so get_user_meta() per row costs no query.
 */
class Users_List {

	/** Column key. */
	const COLUMN = 'tt_theme';

	/** Bulk action key. */
	const BULK_ACTION = 'tt_reset_theme';

	/**
	 * Resolver.
	 *
	 * @var Theme_Resolver
	 */
	private $resolver;

	/**
	 * Theme manager.
	 *
	 * @var Theme_Manager
	 */
	private $themes;

	/**
	 * Constructor.
	 *
	 * @param Theme_Resolver $resolver Resolver.
	 * @param Theme_Manager  $themes   Theme manager.
	 */
	public function __construct( Theme_Resolver $resolver, Theme_Manager $themes ) {
		$this->resolver = $resolver;
		$this->themes   = $themes;
	}

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'manage_users_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_column' ), 10, 3 );
		add_filter( 'manage_users_sortable_columns', array( $this, 'add_sortable' ) );
		add_action( 'pre_get_users', array( $this, 'apply_sorting' ) );
		add_filter( 'bulk_actions-users', array( $this, 'add_bulk_action' ) );
		add_filter( 'handle_bulk_actions-users', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_notice' ) );
		add_filter( 'removable_query_args', array( $this, 'removable_query_args' ) );
	}

	/**
	 * Adds the column after "Role" when present.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$label  = __( 'Selected Theme', 'themetoggle' );
		$result = array();

		foreach ( $columns as $key => $title ) {
			$result[ $key ] = $title;

			if ( 'role' === $key ) {
				$result[ self::COLUMN ] = $label;
			}
		}

		if ( ! isset( $result[ self::COLUMN ] ) ) {
			$result[ self::COLUMN ] = $label;
		}

		return $result;
	}

	/**
	 * Renders a cell.
	 *
	 * @param string $output      Existing output.
	 * @param string $column_name Column.
	 * @param int    $user_id     User ID.
	 * @return string
	 */
	public function render_column( $output, $column_name, $user_id ) {
		if ( self::COLUMN !== $column_name ) {
			return $output;
		}

		$user = get_userdata( (int) $user_id );

		if ( ! $user ) {
			return $output;
		}

		return $this->describe_state( $this->resolver->get_user_state( $user ) );
	}

	/**
	 * Converts a state into escaped HTML.
	 *
	 * @param array $state Resolver state.
	 * @return string
	 */
	public function describe_state( array $state ) {
		$default = esc_html__( 'Default Theme', 'themetoggle' );
		$name    = '' !== $state['preference'] ? $this->themes->get_theme_name( $state['preference'] ) : '';

		switch ( $state['status'] ) {
			case Theme_Resolver::STATUS_ACTIVE:
				return '<strong>' . esc_html( $name ) . '</strong>';

			case Theme_Resolver::STATUS_IS_DEFAULT:
				/* translators: %s: Theme name. */
				return $default . ' <span class="description">' . esc_html( sprintf( __( '(saved: %s, now the site default)', 'themetoggle' ), $name ) ) . '</span>';

			case Theme_Resolver::STATUS_DISABLED:
				/* translators: %s: Theme name. */
				$note = sprintf( __( '(switching disabled; saved: %s)', 'themetoggle' ), $name );
				break;

			case Theme_Resolver::STATUS_NOT_ELIGIBLE:
				/* translators: %s: Theme name. */
				$note = sprintf( __( '(Invalid Selection: %s — role not eligible)', 'themetoggle' ), $name );
				break;

			case Theme_Resolver::STATUS_NOT_ALLOWED:
				/* translators: %s: Theme name. */
				$note = sprintf( __( '(Invalid Selection: %s — not permitted for role)', 'themetoggle' ), $name );
				break;

			case Theme_Resolver::STATUS_MISSING:
				/* translators: %s: Theme directory. */
				$note = sprintf( __( '(Invalid Selection: %s — theme not installed)', 'themetoggle' ), $state['preference'] );
				break;

			default:
				return $default;
		}

		return $default . ' <span class="description">' . esc_html( $note ) . '</span>';
	}

	/**
	 * Makes the column sortable (by saved stylesheet).
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function add_sortable( $columns ) {
		$columns[ self::COLUMN ] = self::COLUMN;

		return $columns;
	}

	/**
	 * Sorts by the saved stylesheet while keeping users without a preference
	 * (a plain meta_key orderby would drop them via an INNER JOIN).
	 *
	 * @param WP_User_Query $query Query.
	 * @return void
	 */
	public function apply_sorting( $query ) {
		if ( ! is_admin() || ! $query instanceof WP_User_Query || self::COLUMN !== $query->get( 'orderby' ) ) {
			return;
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only, user-initiated sort.
		$query->set(
			'meta_query',
			array(
				'relation'      => 'OR',
				'tt_theme_pref' => array(
					'key'     => Theme_Resolver::META_KEY,
					'compare' => 'EXISTS',
				),
				array(
					'key'     => Theme_Resolver::META_KEY,
					'compare' => 'NOT EXISTS',
				),
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$query->set( 'orderby', 'tt_theme_pref' );
	}

	/**
	 * Adds the bulk action.
	 *
	 * @param array $actions Actions.
	 * @return array
	 */
	public function add_bulk_action( $actions ) {
		if ( current_user_can( 'edit_users' ) ) {
			$actions[ self::BULK_ACTION ] = __( 'Reset selected users\' themes', 'themetoggle' );
		}

		return $actions;
	}

	/**
	 * Handles the bulk reset. Core does not verify the nonce for custom user
	 * bulk actions, so it is verified here.
	 *
	 * @param string $sendback Redirect URL.
	 * @param string $action   Action.
	 * @param int[]  $user_ids User IDs.
	 * @return string
	 */
	public function handle_bulk_action( $sendback, $action, $user_ids ) {
		if ( self::BULK_ACTION !== $action ) {
			return $sendback;
		}

		check_admin_referer( 'bulk-users' );

		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit users.', 'themetoggle' ), '', array( 'response' => 403 ) );
		}

		$count = 0;

		foreach ( array_map( 'absint', (array) $user_ids ) as $user_id ) {
			if ( $user_id && current_user_can( 'edit_user', $user_id ) && '' !== $this->resolver->get_saved_preference( $user_id ) ) {
				delete_user_meta( $user_id, Theme_Resolver::META_KEY );
				++$count;
			}
		}

		$sendback = $sendback ? $sendback : admin_url( 'users.php' );

		return add_query_arg( 'tt_reset', $count, remove_query_arg( 'tt_reset', $sendback ) );
	}

	/**
	 * Shows the bulk result.
	 *
	 * @return void
	 */
	public function bulk_notice() {
		$screen = get_current_screen();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only count.
		if ( ! $screen || 'users' !== $screen->id || ! isset( $_GET['tt_reset'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display-only count.
		$count = absint( $_GET['tt_reset'] );

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %d: Number of users. */
					_n( 'Theme preference reset for %d user.', 'Theme preferences reset for %d users.', $count, 'themetoggle' ),
					$count
				)
			)
		);
	}

	/**
	 * Removes the notice arg from the address bar after display.
	 *
	 * @param string[] $args Args.
	 * @return string[]
	 */
	public function removable_query_args( $args ) {
		$args[] = 'tt_reset';

		return $args;
	}
}
