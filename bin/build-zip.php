<?php
/**
 * Builds build/themetoggle.zip from the working tree.
 *
 * Packs exactly the runtime paths listed in "files" in package.json, inside a
 * top-level themetoggle/ folder, so the result matches `composer zip`
 * (git archive) without needing a commit. Cross-platform: needs only PHP with
 * the zip extension (enabled by default in WAMP/XAMPP).
 *
 * Usage: php bin/build-zip.php   (or: composer zip:local, npm run plugin-zip)
 *
 * @package ThemeToggle
 */

// Command-line only: refuse to run when the file is requested over HTTP
// (for example on a site that runs a git checkout instead of the release zip).
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit;
}

$root   = dirname( __DIR__ );
$slug   = 'themetoggle';
$target = $root . '/build/' . $slug . '.zip';

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "The PHP zip extension is not enabled (php.ini: extension=zip).\n" );
	exit( 1 );
}

$package = json_decode( (string) file_get_contents( $root . '/package.json' ), true );
$entries = isset( $package['files'] ) && is_array( $package['files'] ) ? $package['files'] : array();

if ( empty( $entries ) ) {
	fwrite( STDERR, "package.json has no \"files\" list.\n" );
	exit( 1 );
}

$files = array();

foreach ( $entries as $entry ) {
	$path = $root . '/' . trim( $entry, '/' );

	if ( is_file( $path ) ) {
		$files[] = trim( $entry, '/' );
		continue;
	}

	if ( ! is_dir( $path ) ) {
		fwrite( STDERR, "Listed in package.json \"files\" but missing: {$entry}\n" );
		exit( 1 );
	}

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( $file->isFile() && ! in_array( $file->getFilename(), array( '.DS_Store', 'Thumbs.db', 'desktop.ini' ), true ) ) {
			$files[] = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
		}
	}
}

$files = array_values( array_unique( $files ) );
sort( $files );

if ( ! is_dir( dirname( $target ) ) ) {
	mkdir( dirname( $target ), 0777, true );
}

if ( is_file( $target ) ) {
	unlink( $target );
}

$zip = new ZipArchive();

if ( true !== $zip->open( $target, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Cannot create {$target}\n" );
	exit( 1 );
}

foreach ( $files as $relative ) {
	$zip->addFile( $root . '/' . $relative, $slug . '/' . $relative );
}

$zip->close();

printf( "Created build/%s.zip with %d files.\n", $slug, count( $files ) );
