<?php
namespace WordPressdotorg\Plugin_Directory\Tools;

/**
 * Various filesystem-related methods
 *
 * @package WordPressdotorg\Plugin_Directory\Tools
 */
class Filesystem {

	/**
	 * Path to temporary directory.
	 *
	 * @var string
	 */
	const TMP_DIR = '/tmp/plugin-directory/';

	/**
	 * Retrieve a unique temporary directory.
	 *
	 * The temporary directory returned will be removed upon script termination.
	 *
	 * @static
	 *
	 * @param string $prefix Optional. The prefix for the directory, 'hello-dolly' for example. Default: empty string.
	 * @return string The temporary directory.
	 */
	public static function temp_directory( $prefix = '' ) {
		if ( ! is_dir( self::TMP_DIR ) ) {
			mkdir( self::TMP_DIR );
			chmod( self::TMP_DIR, 0777 );
		}

		// Generate a unique filename.
		$tmp_dir = tempnam( self::TMP_DIR, $prefix );

		// Replace that filename with a directory.
		unlink( $tmp_dir );
		mkdir( $tmp_dir );
		chmod( $tmp_dir, 0777 );

		// Automatically remove this temporary directory on shutdown:
		register_shutdown_function( array( __CLASS__, 'rmdir' ), $tmp_dir );

		return $tmp_dir;
	}

	/**
	 * Extract a ZIP file to a directory.
	 *
	 * @static
	 *
	 * @param string $zip_file  The ZIP file to extract.
	 * @param string $directory Optional. The Directory to extract the ZIP to. Default: A Temporary directory.
	 * @return string The directory the ZIP was extracted to.
	 */
	public static function unzip( $zip_file, $directory = '' ) {
		if ( ! $directory ) {
			$directory = self::temp_directory( basename( $zip_file ) );
		}
		$esc_zip_file  = escapeshellarg( $zip_file );
		$esc_directory = escapeshellarg( $directory );

		// Unzip it into the plugin directory.
		exec( "unzip -DD {$esc_zip_file} -d {$esc_directory}" );

		// Fix any permissions issues with the files. Sets 755 on directories, 644 on files.
		exec( "chmod -R 755 {$esc_directory}" );
		exec( "find {$esc_directory} -type f -exec chmod 644 {} \;" );

		// Remove unwanted Mac files.
		exec( "find {$esc_directory} \( -path '*/__MACOSX*' -o -path '*/.DS_Store' \) -delete" );

		return $directory;
	}

	/**
	 * Returns all (usable) files of a given directory.
	 *
	 * @static
	 *
	 * @param string $directory Path to directory to search.
	 * @param bool   $recursive Optional. Whether to recurse into subdirectories. Default: false.
	 * @param string $pattern   Optional. A regular expression to match files against. Default: null.
	 * @param int    $depth     Optional. Recursion depth. Default: -1 (infinite).
	 * @return array All files within the passed directory.
	 */
	public static function list_files( $directory, $recursive = false, $pattern = null, $depth = -1 ) {
		if ( $recursive ) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $directory ),
				\RecursiveIteratorIterator::SELF_FIRST
			);

			if ( $depth > -1 ) {
				$iterator->setMaxDepth( $depth );
			}
		} else {
			$iterator = new \DirectoryIterator( $directory );
		}

		// If a regular expression was given, filter to that.
		$filtered = empty( $pattern ) ? $iterator : new \RegexIterator( $iterator, $pattern );

		$files = array();
		foreach ( $filtered as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			} elseif ( stristr( $file->getPathname(), '__MACOSX' ) ) {
				continue;
			}

			$files[] = $file->getPathname();
		}

		return $files;
	}

	/**
	 * Forcibly remove a directory recursively.
	 *
	 * @static
	 *
	 * @param string $dir The directory to remove.
	 * @return bool Whether the directory was removed.
	 */
	public static function rmdir( $dir ) {
		if ( trim( $dir, '/' ) ) {
			exec( 'rm -rf ' . escapeshellarg( $dir ) );
		}

		return is_dir( $dir );
	}
}
