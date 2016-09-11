<?php
namespace WordPressdotorg\Autoload;

/**
 * An Autoloader which respects WordPress's filename standards.
 *
 * @package WordPressdotorg\Autoload
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
	 * @access protected
	 */
	protected $prefix;

	/**
	 * Length of the prefix string.
	 *
	 * @var int
	 * @access protected
	 */
	protected $prefix_length;

	/**
	 * Path to the file to be loaded.
	 *
	 * @var string
	 * @access protected
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

		// Strip prefix from the start (ala PSR-4)
		$class = substr( $class, $this->prefix_length + 1 );
		$class = strtolower( $class );
		$file  = '';

		if ( false !== ( $last_ns_pos = strripos( $class, self::NS_SEPARATOR ) ) ) {
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
 * @param string $prefix
 * @param string $path
 */
function register_class_path( $prefix, $path ) {
	$loader = new Autoloader( $prefix, $path );
	spl_autoload_register( array( $loader, 'load' ) );
}
