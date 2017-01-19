<?php
namespace WordPressdotorg\Plugin_Directory\Zip;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use Exception;

/**
 * Generates a ZIP file for a Plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Zip
 */
class Builder {

	/**
	 * The base directory for the ZIP files.
	 * Zip files will be stored in a sub-directory, such as:
	 * /tmp/plugin-zipfiles/hello-dolly/hello-dolly.zip
	 */
	const ZIP_DIR = '/tmp/plugin-zipfiles';
	const SVN_URL = 'https://plugins.svn.wordpress.org';

	public $zip_file = '';
	public $md5_file = '';
	protected $tmp_build_file = '';
	protected $tmp_build_dir  = '';


	/**
	 * Generate a ZIP for a provided Plugin Version.
	 *
	 * @param string $plugin_slug The Plugin slug
	 * @param string $version     The version to build (tag, or trunk)
	 */
	public function __construct( $slug, $version ) {
		if ( ! is_dir( self::ZIP_DIR ) ) {
			mkdir( self::ZIP_DIR, 0777, true );
			chmod( self::ZIP_DIR, 0777 );
		}
		if ( ! is_dir( self::ZIP_DIR . '/' . $slug ) ) {
			mkdir( self::ZIP_DIR . '/' . $slug, 0777, true );
			chmod( self::ZIP_DIR . '/' . $slug, 0777 );
		}

		$this->slug = $slug;
		$this->version = $version;

		if ( 'trunk' == $this->version ) {
			$this->zip_file = self::ZIP_DIR . "/{$this->slug}/{$this->slug}.zip";
		} else {
			$this->zip_file = self::ZIP_DIR . "/{$this->slug}/{$this->slug}.{$this->version}.zip";
		}
		$this->md5_file = $this->zip_file . '.md5';

	}

	/**
	 * Generate a ZIP for the plugin + version 
	 */
	public function build() {
		try {
			$this->tmp_build_file = $this->generate_temporary_filename( dirname( $this->zip_file ), "tmp-{$this->slug}.{$this->version}", '.zip' );
			$this->tmp_build_dir  = $this->tmp_build_file . '-files';
			mkdir( $this->tmp_build_dir, 0777, true );

			$this->export_plugin();
			$this->fix_directory_dates();			
			$this->generate_zip();
			$this->move_into_place();
			$this->generate_md5();

			$this->cleanup();

			return true;
		} catch( Exception $e ) {
			$this->cleanup();
			throw $e;
		}/* finally { // PHP 5.5+, meta.svn is limited to PHP 5.4 code still.
			$this->cleanup();
		}*/
	}

	/**
	 * Generates a temporary unique file in a given directory
	 *
	 * Performs a similar job to `tempnam()` with an added suffix and doesn't
	 * cut off the $prefix at 60 characters.
	 * As with `tempnam()` the caller is responsible for removing the temorarily file.
	 *
	 * Note: `strlen( $prefix . $suffix )` shouldn't exceed 238 characters.
	 *
	 * @param string $dir The directory to create the file in.
	 * @param string $prefix The file prefix.
	 * @param string $suffix The file suffix, optional.
	 *
	 * @return string Filename of unique temporary file.
	 */
	protected function generate_temporary_filename( $dir, $prefix, $suffix = '' ) {
		$i = 0;
		do {
			$rand = uniqid();
			$filename = "{$dir}/{$prefix}-{$rand}{$i}{$suffix}";
		} while ( false === ($fp = @fopen( $filename, 'x' ) ) && $i++ < 50 );

		if ( $i >= 50 ) {
			throw new Exception( __METHOD__ . ': Could not find unique filename.' );
		}

		fclose( $fp );

		return $filename;
	}

	/**
	 * Creates an Export of the plugin and redies it for ZIP creation by removing invalid data.
	 */
	protected function export_plugin() {
		if ( 'trunk' == $this->version ) {
			$svn_url = self::SVN_URL . "/{$this->slug}/trunk/";
		} else {
			$svn_url = self::SVN_URL . "/{$this->slug}/tags/{$this->version}/";
		}
		$build_dir = "{$this->tmp_build_dir}/{$this->slug}/";

		$svn_params = array();
		// BudyPress is a special sister project, they have svn:externals.
		if ( 'buddypress' != $this->slug ) {
			$svn_params[] = 'ignore-externals';
		}

		$res = SVN::export( $svn_url, $build_dir, $svn_params );
		// Handle tags which we store as 0.blah but are in /tags/.blah
		if ( ! $res['result'] && '0.' == substr( $this->version, 0, 2 ) ) {
			$_version = substr( $this->version, 1 );
			$svn_url = self::SVN_URL . "/{$this->slug}/tags/{$_version}/";
			$res = SVN::export( $svn_url, $build_dir, $svn_params );
		}
		if ( ! $res['result'] ) {
			throw new Exception( __METHOD__ . ': ' . $res['errors'][0]['error_message'], 404 );
		}

		// Verify that the specified plugin zip will contain files.
		if ( ! array_diff( scandir( $this->tmp_build_dir ), array( '.', '..' ) ) ) {
			throw new Exception( ___METHOD__ . ': No files exist in the plugin directory', 404 );
		}

		// Cleanup any symlinks that shouldn't be there
		$this->exec( sprintf(
			'find %s -type l -print0 | xargs -r0 rm',
			escapeshellarg( $build_dir )
		) );

		return true;
	}

	/**
	 * Corrects the directory dates to match the latest modified file in the plugin export.
	 *
	 * When svn exports a directory, the file entries will reflect their last modified date
	 * however directories are created with their modified date set to the current date.
	 *
	 * This causes issues for ZIPs being built as we can't guarantee that two zips built
	 * from the same source files at different times will have the same checksums.
	 */
	protected function fix_directory_dates() {
		// Find all files, output their modified dates, sort reverse numerically, grab the timestamp from the first entry
		$latest_file_modified_timestamp = $this->exec( sprintf(
			"find %s -type f -printf '%%T@\n' | sort -nr | head -c 10",
			escapeshellarg( $this->tmp_build_dir )
		) );
		if ( ! $latest_file_modified_timestamp ) {
			throw new Exception( _METHOD__ . ': Unable to locate the latest modified files timestamp.', 503 );
		}

		$this->exec( sprintf(
			'find %s -type d -exec touch -m -t %s {} \;',
			escapeshellarg( $this->tmp_build_dir ),
			escapeshellarg( date( 'ymdHi.s', $latest_file_modified_timestamp ) )
		) );
	}

	/**
	 * Generates the actual ZIP file we've painstakingly created the files for.
	 */
	protected function generate_zip() {
		// We have to remove the temporary 0-byte file first as zip will complain about not being able to find the zip structures.
		unlink( $this->tmp_build_file );
		$this->exec( sprintf(
			'cd %s && find %s -print0 | sort -z | xargs -0 zip -Xu %s 2>&1',
			escapeshellarg( $this->tmp_build_dir ),
			escapeshellarg( $this->slug ),
			escapeshellarg( $this->tmp_build_file )
		), $zip_build_output, $return_value );

		if ( $return_value ) {
			throw new Exception( __METHOD__ . ': ZIP generation failed, return code: ' . $return_value, 503 );
		}
	}

	/**
	 * Moves the completed ZIP into it's real-life location.
	 */
	protected function move_into_place() {
		$this->exec( sprintf(
			'mv -f %s %s',
			escapeshellarg( $this->tmp_build_file ),
			escapeshellarg( $this->zip_file )
		), $output, $return_value );

		if ( $return_value ) {
			throw new Exception( __METHOD__ . ': Could not move ZIP into place.', 503 );
		}
	}

	/**
	 * Generates the MD5 for the ZIP file used for serving.
	 *
	 * This can also be used for generating a package signature in the future.
	 */
	protected function generate_md5() {
		$this->exec( sprintf(
			"md5sum %s | head -c 32 > %s",
			escapeshellarg( $this->zip_file ),
			escapeshellarg( $this->md5_file )
		), $output, $return_code );

		if ( $return_code ) {
			throw new Exception( __METHOD__ . ': Failed to create file checksum.', 503 );
		}
	}

	/**
	 * Cleans up any temporary directories created by the ZIP Builder.
	 */
	protected function cleanup() {
		if ( $this->tmp_build_file && file_exists( $this->tmp_build_file ) ) {
			unlink( $this->tmp_build_file );
		}
		if ( $this->tmp_build_dir ) {
			$this->exec( sprintf( 'rm -rf %s', escapeshellarg( $this->tmp_build_dir ) ) );
		}
	}

	/**
	 * Executes a command with 'proper' locale/language settings
	 * so that utf8 strings are handled correctly.
	 *
	 * WordPress.org uses the en_US.UTF-8 locale.
	 */
	protected function exec( $command, &$output = null, &$return_val = null ) {
		return exec( 'export LC_CTYPE="en_US.UTF-8" LANG="en_US.UTF-8"; ' . $command, $output, $return_val );
	}

}

