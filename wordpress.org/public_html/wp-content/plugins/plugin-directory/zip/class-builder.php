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

	const TMP_DIR     = '/tmp/plugin-zip-builder';
	const SVN_URL     = 'https://plugins.svn.wordpress.org';

	protected $zip_file       = '';
	protected $checksum_file  = '';
	protected $signature_file = '';
	protected $tmp_build_dir  = '';
	protected $tmp_dir        = '';

	protected $slug       = '';
	protected $version    = '';
	protected $context    = '';
	protected $stable_tag = '';

	// The SVN url of the plugin version being packaged.
	protected $plugin_version_svn_url = '';

	/**
	 * Generate a ZIP for a provided Plugin tags.
	 *
	 * @param string $slug     The plugin slug.
	 * @param array  $versions The versions of the plugin to build ZIPs for.
	 * @param string $context  The context of this Builder instance (commit #, etc)
	 */
	public function build( $slug, $versions, $context = '', $stable_tag = '' ) {
		// Bail when in an unconfigured environment.
		if ( ! defined( 'PLUGIN_ZIP_SVN_URL' ) ) {
			return false;
		}

		$this->slug       = $slug;
		$this->versions   = $versions;
		$this->context    = $context;
		$this->stable_tag = $stable_tag;

		// General TMP directory
		if ( ! is_dir( self::TMP_DIR ) ) {
			mkdir( self::TMP_DIR, 0777, true );
			chmod( self::TMP_DIR, 0777 );
		}

		// Temp Directory for this instance of the Builder class.
		$this->tmp_dir = $this->generate_temporary_directory( self::TMP_DIR, $slug );

		// Create a checkout of the ZIP SVN
		$res_checkout = SVN::checkout(
			PLUGIN_ZIP_SVN_URL,
			$this->tmp_dir,
			array(
				'depth'    => 'empty',
				'username' => PLUGIN_ZIP_SVN_USER,
				'password' => PLUGIN_ZIP_SVN_PASS,
			)
		);

		if ( $res_checkout['result'] ) {

			// Ensure the plugins folder exists within svn
			$plugin_folder = "{$this->tmp_dir}/{$this->slug}/";

			$res = SVN::up(
				$plugin_folder,
				array(
					'depth' => 'empty',
				)
			);
			if ( ! is_dir( $plugin_folder ) ) {
				mkdir( $plugin_folder, 0777, true );
				$res = SVN::add( $plugin_folder );
			}
			if ( ! $res['result'] ) {
				throw new Exception( __METHOD__ . ": Failed to create {$plugin_folder}." );
			}
		} else {
			throw new Exception( __METHOD__ . ': Failed to create checkout of ' . PLUGIN_ZIP_SVN_URL . '.' );
		}

		// Build the requested ZIPs
		foreach ( $versions as $version ) {
			// Incase .1 was passed, treat it as 0.1
			if ( '.' == substr( $version, 0, 1 ) ) {
				$version = "0{$version}";
			}
			$this->version = $version;

			if ( 'trunk' == $version ) {
				$this->zip_file = "{$this->tmp_dir}/{$this->slug}/{$this->slug}.zip";
			} else {
				$this->zip_file = "{$this->tmp_dir}/{$this->slug}/{$this->slug}.{$version}.zip";
			}

			// Pull the ZIP file down we're going to modify, which may not already exist.
			SVN::up( $this->zip_file );
			// This is done within the checksum generation function due to us not knowing the checksum filename until export_plugin().
			// SVN::up( $this->checksum_file );
			try {

				$this->tmp_build_dir = $this->zip_file . '-files';
				mkdir( $this->tmp_build_dir, 0777, true );

				$this->export_plugin();
				$this->fix_directory_dates();

				$this->generate_zip();

				$this->generate_zip_signatures();

				$this->generate_checksums();

				$this->cleanup_plugin_tmp();

			} catch ( Exception $e ) {
				// In event of error, skip this file this time.
				$this->cleanup_plugin_tmp();

				// Perform an SVN up to revert any changes made.
				SVN::up( $this->zip_file );
				if ( $this->checksum_file ) {
					SVN::up( $this->checksum_file );
				}
				if ( $this->signature_file ) {
					SVN::up( $this->signature_file );
				}
				continue;
			}

			// Add the ZIP file to SVN - This is only really needed for new files which don't exist in SVN.
			SVN::add( $this->zip_file );
			if ( $this->checksum_file ) {
				SVN::add( $this->checksum_file );
			}
			if ( $this->signature_file ) {
				SVN::add( $this->signature_file );
			}
		}

		$res = SVN::commit(
			$this->tmp_dir,
			$this->context ? $this->context : "Updated ZIPs for {$this->slug}.",
			array(
				'username' => PLUGIN_ZIP_SVN_USER,
				'password' => PLUGIN_ZIP_SVN_PASS,
			)
		);

		$this->invalidate_zip_caches( $versions );

		$this->cleanup();

		if ( ! $res['result'] ) {
			if ( $res['errors'] ) {
				throw new Exception( __METHOD__ . ': Failed to commit the new ZIPs: ' . $res['errors'][0]['error_message'] );
			} else {
				throw new Exception( __METHOD__ . ': Commit failed without error, maybe there were no modified files?' );
			}
		}

		return true;
	}

	/**
	 * Generates a JSON file containing the checksums of the files within the ZIP.
	 *
	 * In the event that a previous ZIP for this version exists, checksums for all versions of the file will be included.
	 */
	function generate_checksums() {
		// Don't create checksums for trunk.
		if ( ! $this->stable_tag || ( 'trunk' == $this->version && 'trunk' != $this->stable_tag && '' != $this->stable_tag ) ) {
			return;
		}

		// Fetch the plugin headers
		$plugin_data = false;
		foreach ( glob( $this->tmp_build_dir . '/' . $this->slug . '/*.php' ) as $filename ) {
			$plugin_data = get_plugin_data( $filename, false, false );

			if ( $plugin_data['Name'] && '' !== $plugin_data['Version'] ) {
				break;
			}
		}

		if ( ! $plugin_data || '' === $plugin_data['Version'] ) {
			return;
		}

		$plugin_version = $plugin_data['Version'];
		// Catch malformed version strings.
		if ( basename( $plugin_version ) != $plugin_version ) {
			return;
		}

		$this->checksum_file = "{$this->tmp_dir}/{$this->slug}/{$this->slug}.{$plugin_version}.checksums.json";

		// Checkout the Checksum file for this plugin version
		SVN::up( $this->checksum_file );

		// Existing checksums?
		$existing_json_checksum_file = file_exists( $this->checksum_file );

		$skip_bad_files = array();
		$checksums      = array();
		foreach ( array(
			'md5'    => 'md5sum',
			'sha256' => 'sha256sum',
		) as $checksum_type => $checksum_bin ) {
			$checksum_output = array();
			$this->exec( sprintf(
				'cd %s && find . -type f -print0 | sort -z | xargs -0 ' . $checksum_bin . ' 2>&1',
				escapeshellarg( $this->tmp_build_dir . '/' . $this->slug )
			), $checksum_output, $return_value );

			if ( $return_value ) {
				// throw new Exception( __METHOD__ . ': Checksum generation failed, return code: ' . $return_value, 503 );
				// TODO For now, just silently keep going.
				continue;
			}

			foreach ( $checksum_output as $line ) {
				list( $checksum, $filename ) = preg_split( '!\s+!', $line, 2 );

				$filename = trim( preg_replace( '!^./!', '', $filename ) );
				$checksum = trim( $checksum );

				// See https://meta.trac.wordpress.org/ticket/3335 - Filenames like 'Testing Test' truncated to 'Testing'
				if ( preg_match( '!^(\S+)\s+\S!', $filename, $m ) ) {
					$skip_bad_files[ $m[1] ] = true;
				}

				if ( ! isset( $checksums[ $filename ] ) ) {
					$checksums[ $filename ] = array(
						'md5'    => array(),
						'sha256' => array(),
					);
				}

				$checksums[ $filename ][ $checksum_type ] = $checksum;
			}
		}

		$json_checksum_file = (object) array(
			'plugin'  => $this->slug,
			'version' => $plugin_version,
			'source'  => $this->plugin_version_svn_url,
			'zip'     => 'https://downloads.wordpress.org/plugins/' . basename( $this->zip_file ),
			'files'   => $checksums,
		);

		// If the checksum file exists already, merge it into this one.
		if ( $existing_json_checksum_file ) {
			$existing_json_checksum_file = json_decode( file_get_contents( $this->checksum_file ) );

			// Sometimes plugin versions exist in multiple tags/zips, include all the SVN urls & ZIP urls
			foreach ( array( 'source', 'zip' ) as $maybe_different ) {
				if ( ! empty( $existing_json_checksum_file->{$maybe_different} ) &&
					$existing_json_checksum_file->{$maybe_different} != $json_checksum_file->{$maybe_different}
				) {
					$json_checksum_file->{$maybe_different} = array_unique( array_merge(
						(array) $existing_json_checksum_file->{$maybe_different},
						(array) $json_checksum_file->{$maybe_different}
					) );

					// Reduce single arrays back to a string when possible.
					if ( 1 == count( $json_checksum_file->{$maybe_different} ) ) {
						$json_checksum_file->{$maybe_different} = array_shift( $json_checksum_file->{$maybe_different} );
					}
				}
			}

			// Combine Checksums from existing files and the new files
			foreach ( $existing_json_checksum_file->files as $file => $checksums ) {

				if ( ! isset( $json_checksum_file->files[ $file ] ) ) {
					if ( isset( $skip_bad_files[ $file ] ) ) {
						// See https://meta.trac.wordpress.org/ticket/3335
						// This is a partial filename, which shouldn't have been in the checksums.
						continue;
					}

					// Deleted file, use existing checksums.
					$json_checksum_file->files[ $file ] = $checksums;

				} elseif ( $checksums !== $json_checksum_file->files[ $file ] ) {
					// Checksum has changed, include both in the resulting json file.
					foreach ( array( 'md5', 'sha256' ) as $checksum_type ) {
						$json_checksum_file->files[ $file ][ $checksum_type ] = array_unique( array_merge(
							(array) $checksums->{$checksum_type}, // May already be an array
							(array) $json_checksum_file->files[ $file ][ $checksum_type ]
						) );

						// Reduce single arrays back to a string when possible.
						if ( 1 == count( $json_checksum_file->files[ $file ][ $checksum_type ] ) ) {
							$json_checksum_file->files[ $file ][ $checksum_type ] = array_shift( $json_checksum_file->files[ $file ][ $checksum_type ] );
						}
					}
				}
			}
		}

		ksort( $json_checksum_file->files );

		file_put_contents( $this->checksum_file, wp_json_encode( $json_checksum_file ) );
	}

	/**
	 * Generates a temporary unique directory in a given directory
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
	 * @return string Path of unique temporary directory.
	 */
	protected function generate_temporary_directory( $dir, $prefix, $suffix = '' ) {
		$i = 0;
		do {
			$rand     = uniqid();
			$filename = "{$dir}/{$prefix}-{$rand}{$i}{$suffix}";
		} while ( false === ( $fp = @fopen( $filename, 'x' ) ) && $i++ < 50 );

		if ( $i >= 50 ) {
			throw new Exception( __METHOD__ . ': Could not find unique filename.' );
		}

		fclose( $fp );

		// Convert file to directory.
		unlink( $filename );
		if ( ! mkdir( $filename, 0777, true ) ) {
			throw new Exception( __METHOD__ . ': Could not convert temporary filename to directory.' );
		}
		chmod( $filename, 0777 );

		return $filename;
	}

	/**
	 * Creates an Export of the plugin and redies it for ZIP creation by removing invalid data.
	 */
	protected function export_plugin() {
		if ( 'trunk' == $this->version ) {
			$this->plugin_version_svn_url = self::SVN_URL . "/{$this->slug}/trunk/";
		} else {
			$this->plugin_version_svn_url = self::SVN_URL . "/{$this->slug}/tags/{$this->version}/";
		}

		$build_dir = "{$this->tmp_build_dir}/{$this->slug}/";

		$svn_params = array();
		// BudyPress is a special sister project, they have svn:externals.
		if ( 'buddypress' != $this->slug ) {
			$svn_params[] = 'ignore-externals';
		}

		$res = SVN::export( $this->plugin_version_svn_url, $build_dir, $svn_params );
		// Handle tags which we store as 0.blah but are in /tags/.blah
		if ( ! $res['result'] && '0.' == substr( $this->version, 0, 2 ) ) {
			$_version                     = substr( $this->version, 1 );
			$this->plugin_version_svn_url = self::SVN_URL . "/{$this->slug}/tags/{$_version}/";
			$res                          = SVN::export( $this->plugin_version_svn_url, $build_dir, $svn_params );
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
			throw new Exception( __METHOD__ . ': Unable to locate the latest modified files timestamp.', 503 );
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
		// If we're building an existing zip, remove the existing file first.
		if ( file_exists( $this->zip_file ) ) {
			unlink( $this->zip_file );
		}
		$this->exec( sprintf(
			'cd %s && find %s -print0 | sort -z | xargs -0 zip -Xuy %s 2>&1',
			escapeshellarg( $this->tmp_build_dir ),
			escapeshellarg( $this->slug ),
			escapeshellarg( $this->zip_file )
		), $zip_build_output, $return_value );

		if ( $return_value ) {
			throw new Exception( __METHOD__ . ': ZIP generation failed, return code: ' . $return_value, 503 );
		}
	}

	/**
	 * Generate the signature for a ZIP file.
	 */
	protected function generate_zip_signatures() {

		// TODO: Currently disabled, enable when ready.
		return false;

		if ( ! function_exists( 'wporg_sign_file' ) ) {
			return false;
		}

		$signatures = wporg_sign_file( $this->zip_file, 'plugin' );
		if ( $signatures ) {
			$this->signature_file = $this->zip_file . '.sig';

			// Fetch any existing signatures if needed.
			SVN::up( $this->signature_file );

			// If this file was previously signed, keep the previous version.
			// This would only occur if a ZIP file was replaced in the few moments between ZIP download starting, and fetching the signature for verification.
			if ( file_exists( $this->signature_file ) ) {
				$existing_signatures = file( $this->signature_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
				$signatures = array_unique( array_merge( $signatures, $existing_signatures ) );
			}

			file_put_contents( $this->signature_file, implode( "\n", $signatures ) );
		}
	}

	/**
	 * Purge ZIP caches after ZIP building.
	 *
	 * @param array $versions The list of plugin versions of modified zips.
	 * @return bool
	 */
	public function invalidate_zip_caches( $versions ) {
		// TODO: Implement PURGE
		return true;
		if ( ! defined( 'PLUGIN_ZIP_X_ACCEL_REDIRECT_LOCATION' ) ) {
			return true;
		}

		foreach ( $versions as $version ) {
			if ( 'trunk' == $version ) {
				$zip = "{$this->slug}/{$this->slug}.zip";
			} else {
				$zip = "{$this->slug}/{$this->slug}.{$version}.zip";
			}

			foreach ( $plugins_downloads_load_balancer /* TODO */ as $lb ) {
				$url = 'http://' . $lb . PLUGIN_ZIP_X_ACCEL_REDIRECT_LOCATION . $zip;

				wp_remote_request( $url, array(
					'method' => 'PURGE',
				) );
			}
		}
	}

	/**
	 * Cleans up any temporary directories created by the ZIP Builder.
	 */
	protected function cleanup() {
		if ( $this->tmp_dir ) {
			$this->exec( sprintf( 'rm -rf %s', escapeshellarg( $this->tmp_dir ) ) );
		}
	}

	/**
	 * Cleans up any temporary directories created by the ZIP builder for a specific build.
	 */
	protected function cleanup_plugin_tmp() {
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

