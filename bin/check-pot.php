<?php
/**
 * Fails when languages/themetoggle.pot is missing strings from the source, or
 * still has strings the source no longer contains.
 *
 * Only the translatable entries (context, singular, plural) are compared, so
 * line-number references and header dates do not matter. Cross-platform: runs
 * WP-CLI through PHP, so no shell is needed (Windows cmd works).
 *
 * Usage: php bin/check-pot.php   (or: composer pot:check)
 *
 * @package ThemeToggle
 */

$root      = dirname( __DIR__ );
$committed = $root . '/languages/themetoggle.pot';
$boot      = $root . '/vendor/wp-cli/wp-cli/php/boot-fs.php';
$fresh     = tempnam( sys_get_temp_dir(), 'tt-pot' );

if ( ! is_file( $boot ) ) {
	fwrite( STDERR, "WP-CLI not found in vendor/. Run 'composer install' first.\n" );
	exit( 1 );
}

$command = sprintf(
	'%s %s i18n make-pot %s %s --slug=themetoggle --domain=themetoggle --exclude=vendor,node_modules,tests,build,docs,bin',
	escapeshellarg( PHP_BINARY ),
	escapeshellarg( $boot ),
	escapeshellarg( $root ),
	escapeshellarg( $fresh )
);

exec( $command . ' 2>&1', $output, $status );

if ( 0 !== $status ) {
	fwrite( STDERR, implode( PHP_EOL, $output ) . PHP_EOL );
	unlink( $fresh );
	exit( 1 );
}

/**
 * Returns the sorted, unique translatable entries of a POT file.
 *
 * @param string $file POT file.
 * @return string[]
 */
function tt_pot_entries( $file ) {
	$lines   = preg_split( '/\R/', (string) file_get_contents( $file ) );
	$entries = array();
	$current = '';

	foreach ( $lines as $line ) {
		if ( preg_match( '/^(msgctxt|msgid|msgid_plural) /', $line ) ) {
			if ( '' !== $current ) {
				$entries[] = $current;
			}
			$current = $line;
		} elseif ( '' !== $current && 0 === strpos( $line, '"' ) ) {
			$current .= $line;
		} else {
			if ( '' !== $current ) {
				$entries[] = $current;
			}
			$current = '';
		}
	}

	if ( '' !== $current ) {
		$entries[] = $current;
	}

	$entries = array_values( array_unique( array_diff( $entries, array( 'msgid ""' ) ) ) );
	sort( $entries );

	return $entries;
}

$old = tt_pot_entries( $committed );
$new = tt_pot_entries( $fresh );
unlink( $fresh );

$missing = array_diff( $new, $old );
$stale   = array_diff( $old, $new );

if ( empty( $missing ) && empty( $stale ) ) {
	echo "POT is up to date.\n";
	exit( 0 );
}

foreach ( $missing as $entry ) {
	echo '+ ' . $entry . "\n";
}

foreach ( $stale as $entry ) {
	echo '- ' . $entry . "\n";
}

fwrite( STDERR, "POT is out of date: run 'composer make-pot' and commit languages/themetoggle.pot.\n" );
exit( 1 );
