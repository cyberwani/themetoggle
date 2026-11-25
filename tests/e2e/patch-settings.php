<?php
/**
 * Test helper for `wp eval-file`: merges a JSON object into tt_settings.
 *
 * @package ThemeToggle\Tests
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- WP-CLI eval-file scope.
$tt_current = get_option( 'tt_settings', array() );
$tt_patch   = isset( $args[0] ) ? json_decode( $args[0], true ) : array();
update_option( 'tt_settings', array_merge( (array) $tt_current, (array) $tt_patch ) );
