<?php
/**
 * Checks that every place holding the plugin version agrees.
 *
 * Usage: php bin/check-version.php [expected-version]
 *        composer version:check            (compares against the plugin header)
 *        composer version:check -- 1.0.1   (CI passes the tag without "v")
 *
 * Cross-platform (no shell needed).
 *
 * @package ThemeToggle
 */

$root = dirname( __DIR__ );

/**
 * Returns the first regex capture found in a file, or ''.
 *
 * @param string $file    File relative to the plugin root.
 * @param string $pattern Regex with one capture group.
 * @return string
 */
function tt_version_find( $file, $pattern ) {
	$contents = (string) file_get_contents( dirname( __DIR__ ) . '/' . $file );

	return preg_match( $pattern, $contents, $match ) ? trim( $match[1] ) : '';
}

$found = array(
	'themetoggle.php header Version' => tt_version_find( 'themetoggle.php', '/^\s*\*\s*Version:\s*(\S+)/m' ),
	'themetoggle.php const VERSION'  => tt_version_find( 'themetoggle.php', "/^const VERSION\s*=\s*'([^']+)'/m" ),
	'readme.txt Stable tag'          => tt_version_find( 'readme.txt', '/^Stable tag:\s*(\S+)/m' ),
	'package.json version'           => (string) ( json_decode( (string) file_get_contents( $root . '/package.json' ), true )['version'] ?? '' ),
);

$expected = isset( $argv[1] ) && '' !== $argv[1] ? $argv[1] : $found['themetoggle.php header Version'];
$status   = 0;

foreach ( $found as $label => $value ) {
	if ( $value === $expected ) {
		echo "ok    {$label}: {$value}\n";
	} else {
		echo "FAIL  {$label}: '{$value}' (expected '{$expected}')\n";
		$status = 1;
	}
}

if ( preg_match( '/^= ' . preg_quote( $expected, '/' ) . ' =$/m', (string) file_get_contents( $root . '/readme.txt' ) ) ) {
	echo "ok    readme.txt changelog entry: = {$expected} =\n";
} else {
	echo "FAIL  readme.txt has no changelog entry '= {$expected} ='\n";
	$status = 1;
}

exit( $status );
