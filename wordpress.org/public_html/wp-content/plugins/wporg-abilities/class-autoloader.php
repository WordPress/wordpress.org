<?php
/**
 * Autoloader for wporg-abilities.
 *
 * phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed
 *
 * @package WordPressdotorg\Abilities
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Autoloader;

/**
 * An Autoloader which respects WordPress's filename standards.
 */
class Autoloader {

	/**
	 * Namespace separator.
	 */
	const NS_SEPARATOR = '\\';

	/**
	 * The prefix to compare classes against.
	 *
	 * @var string
	 */
	protected $prefix;

	/**
	 * Length of the prefix string.
	 *
	 * @var int
	 */
	protected $prefix_length;

	/**
	 * Path to the files to be loaded.
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Constructor.
	 *
	 * @param string $prefix Prefix all classes have in common.
	 * @param string $path   Path to the files to be loaded.
	 */
	public function __construct( $prefix, $path ) {
		$this->prefix        = $prefix;
		$this->prefix_length = strlen( $prefix );
		$this->path          = trailingslashit( $path );
	}

	/**
	 * Loads a class if it starts with `$this->prefix`.
	 *
	 * @param string $class The class to be loaded.
	 */
	public function load( $class ) {
		if ( strpos( $class, $this->prefix . self::NS_SEPARATOR ) !== 0 ) {
			return;
		}

		// Strip prefix from the start (ala PSR-4).
		$class = substr( $class, $this->prefix_length + 1 );
		$class = strtolower( $class );
		$file  = '';

		$last_ns_pos = strripos( $class, self::NS_SEPARATOR );
		if ( false !== $last_ns_pos ) {
			$namespace = substr( $class, 0, $last_ns_pos );
			$namespace = str_replace( '_', '-', $namespace );
			$class     = substr( $class, $last_ns_pos + 1 );
			$file      = str_replace( self::NS_SEPARATOR, DIRECTORY_SEPARATOR, $namespace ) . DIRECTORY_SEPARATOR;
		}

		$file .= 'class-' . str_replace( '_', '-', $class ) . '.php';

		$path = $this->path . $file;

		if ( file_exists( $path ) ) {
			require $path;
		}
	}
}

/**
 * Registers Autoloader's autoload function.
 *
 * @param string $prefix Namespace prefix.
 * @param string $path   Path to class files.
 */
function register_class_path( $prefix, $path ) {
	$loader = new Autoloader( $prefix, $path );
	spl_autoload_register( array( $loader, 'load' ) );
}
