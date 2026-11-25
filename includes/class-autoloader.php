<?php
/**
 * PSR-4-like autoloader that follows WordPress file naming conventions.
 *
 * @package ThemeToggle
 */

namespace ThemeToggle;

defined( 'ABSPATH' ) || exit;

/**
 * Maps ThemeToggle\Class_Name to includes/class-class-name.php and
 * ThemeToggle\Admin\Class_Name to admin/class-class-name.php.
 */
class Autoloader {

	/**
	 * Root namespace handled by this autoloader.
	 *
	 * @var string
	 */
	private $root_namespace;

	/**
	 * Absolute plugin directory with trailing slash.
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * Constructor.
	 *
	 * @param string $root_namespace Root namespace.
	 * @param string $base_dir       Plugin directory.
	 */
	public function __construct( $root_namespace, $base_dir ) {
		$this->root_namespace = trim( $root_namespace, '\\' ) . '\\';
		$this->base_dir       = trailingslashit( $base_dir );
	}

	/**
	 * Registers the autoloader with SPL.
	 *
	 * @return void
	 */
	public function register() {
		spl_autoload_register( array( $this, 'load' ) );
	}

	/**
	 * Loads a class file if it belongs to this plugin.
	 *
	 * @param string $class_name Fully qualified class name.
	 * @return void
	 */
	public function load( $class_name ) {
		if ( 0 !== strpos( $class_name, $this->root_namespace ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $this->root_namespace ) );
		$parts    = explode( '\\', $relative );
		$class    = array_pop( $parts );
		$dir      = empty( $parts ) ? 'includes' : strtolower( implode( '/', $parts ) );
		$file     = $this->base_dir . $dir . '/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
