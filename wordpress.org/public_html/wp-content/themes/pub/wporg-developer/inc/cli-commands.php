<?php
/**
 * Implements devhub commands.
 */
class DevHub_Command extends WP_CLI_Command {

	/**
	 * Parses WP code.
	 *
	 * The source code for the version of WordPress to be parsed needs to be
	 * obtained and unpackaged locally. You should not be code used in an
	 * active install.
	 *
	 * ## OPTIONS
	 *
	 * [--src_path=<src_path>]
	 * : The path to a copy of WordPress to be parsed. Should not be code used in
	 * an active install. If not defined, then the latest version of WordPress will
	 * be downloaded to a temp directory and parsed.
	 *
	 * [--user_id=<user_id>]
	 * : ID of user to attribute all parsed posts to. Default is 5911429, the ID for wordpressdotorg.
	 *
	 * [--wp_ver=<wp_ver>]
	 * : Version of WordPress to install. Only taken into account if --src_path is
	 * not defined. Default is the latest release (or whatever version is present
	 * in --src_path if that is defined).
	 *
	 * ## EXAMPLES
	 *
	 *     # Parse latest WP.
	 *     $ wp devhub parse
	 *
	 *     # Parse specific copy of WP.
	 *     $ wp devhub parse --src_path=/path/to/wordpress
	 *
	 *     # Parse a particular version of WP.
	 *     $ wp devhub parse --wp_ver=5.5.2
	 *
	 * @when after_wp_load
	 */
	public function parse( $args, $assoc_args ) {
		$path = $assoc_args['src_path'] ?? null;
		$user_id = $assoc_args['user_id'] ?? 5911429; // 5911429 = ID for wordpressdotorg
		$wp_ver = $assoc_args['wp_ver'] ?? null;

		// Verify path is a file or directory.
		if ( $path ) {
			if ( file_exists( $path ) ) {
				WP_CLI::log( 'Parsing WordPress source from specified directory: ' . $path );
			} else {
				WP_CLI::error( 'Provided path for WordPress source to parse does not exist.' );
			}
		}

		// If no path provided, use a temporary path.
		if ( ! $path ) {
			$path = WP_CLI\Utils\get_temp_dir() . 'devhub_' . time();

			// @todo Attempt to reuse an existing temp dir.
			if ( mkdir( $path ) ) {
				if ( $wp_ver ) {
					WP_CLI::log( "Installing WordPress {$wp_ver} into temporary directory ({$path})..." );
				} else {
					WP_CLI::log( "Installing latest WordPress into temporary directory ({$path})..." );
				}
				$cmd = "core download --path={$path}";
				if ( $wp_ver ) {
					$cmd .= " --version={$wp_ver}";
				}
				// Install WP into the temp directory.
				WP_CLI::runcommand( $cmd, [] );
			} else {
				$path = null;
			}
		}

		if ( ! $path ) {
			WP_CLI::error( 'Unable to create temporary directory for downloading WordPress. If retrying fails, consider obtaining the files manually and supplying that path via --src_path argument.' );
		}

		// Determine importing user's ID. 
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			WP_CLI::error( 'Invalid user_id provided.' );
		}
		WP_CLI::log( "Importing as user ID $user_id ({$user->user_nicename})." );

		$plugins = [
			'phpdoc-parser'  => 'phpdoc-parser/plugin.php',
			'posts-to-posts' => 'posts-to-posts/posts-to-posts.php',
		];

		// Verify path is not a file.
		if ( is_file( $path ) ) {
			WP_CLI::error( 'Path provided for WordPress source to parse does not appear to be a directory.' );
		}

		// Verify path looks like WP.
		if ( ! file_exists ( $path . '/wp-includes/version.php' ) ) {
			WP_CLI::error( 'Path provided for WordPress source to parse does not contain WordPress files.' );
		}

		// Get WP version of files to be parsed.
		$version_file = file_get_contents( $path . '/wp-includes/version.php' );
		preg_match( '/\$wp_version = \'([^\']+)\'/', $version_file, $matches );
		$version = $matches[1];

		// Get WP version last parsed (if any) and confirm if reparsing that version.
		$last_parsed_wp_ver = get_option( 'wp_parser_imported_wp_version' );
		if ( $last_parsed_wp_ver && $last_parsed_wp_ver == $version ) {
			$last_parsed_date = get_option( 'wp_parser_last_import' );
			WP_CLI::confirm( "Looks like WP $version was already parsed on " . date_i18n( 'Y-m-d H:i', $last_parsed_date ) . ". Proceed anyway?" );
		}

		// Get the phpdoc-parser plugin if not installed.
		$all_plugins = get_plugins();
		if ( ! in_array( $plugins['phpdoc-parser'], array_keys( $all_plugins ) ) ) {
			WP_CLI::log( "Installing phpdoc-parser plugin..." );
			WP_CLI::runcommand( 'plugin install https://github.com/WordPress/phpdoc-parser/archive/master.zip' );
			touch( WP_PLUGIN_DIR . '/phpdoc-parser/.devhub-parser-installed' );
		}

		// Verify the phpdoc-parser plugin is available locally.
		wp_cache_set( 'plugins', [], 'plugins' ); // Reset cache of plugins.
		$all_plugins = get_plugins();
		if ( ! in_array( $plugins['phpdoc-parser'], array_keys( $all_plugins ) ) ) {
			WP_CLI::error( 'The PHPDoc-Parser plugin (from https://github.com/WordPress/phpdoc-parser) could not be automatically installed locally in ' . WP_PLUGIN_DIR . '/. Please do so manually.' );
		}

		// Install phpdoc-parser plugin dependencies if not installed.
		$plugin_dir = WP_PLUGIN_DIR . '/phpdoc-parser/';
		if ( ! file_exists( $plugin_dir . 'vendor' ) ) {
			WP_CLI::log( "About to install plugin dependencies for phpdoc-parser..." );
			// Install Composer if necessary.
			if ( ! file_exists( $plugin_dir . "composer.phar" ) ) {
				WP_CLI::log( "About to install Composer..." );
				if ( ! copy( 'https://getcomposer.org/installer', $plugin_dir . 'composer-setup.php' ) ) {
					WP_CLI::error( 'Unable to obtain the Composer setup script while attempting to install dependencies for phpdoc-parser.' );
				}
				WP_CLI::launch( "cd {$plugin_dir} && COMPOSER_HOME={$plugin_dir} php composer-setup.php" );
				unlink( $plugin_dir . 'composer-setup.php' );
			}
			// Check if Composer is actually available.
			$res = WP_CLI::launch( "cd {$plugin_dir} && COMPOSER_HOME={$plugin_dir} php composer.phar about", false, true );
			if ( $res && ! empty( $res->stderr ) ) {
				// Remove file that indicates plugin was automatically installed since
				// user needs to intervene to build dependencies and we don't want to
				// inconvenience them for every parsing.
				// @todo Consider using git checkout
				unlink( $plugin_dir . '.devhub-parser-installed' );

				WP_CLI::log( 'Looks like phpdoc-parser plugin dependencies must be built manually:' );
				WP_CLI::log( "\tcd {$plugin_dir}" );
				WP_CLI::log( "\tphp composer.phar install -n" );
				WP_CLI::log( "Afterwards, rerun the parse command." );
				WP_CLI::error( 'Unable to run composer installer: ' . trim( $res->stderr ) );
			}

		}

		// Confirm the parsing.
		WP_CLI::confirm( "Are you sure you want to parse the source code for WP {$version} (and that you've run a backup of the existing data)?" );

		/**
		 * Fires just before actual parsing process takes place.
		 *
		 * @param string  $path    Path to the directory containing the WP files to parse.
		 * @param string  $version Versin of WP being parsed.
		 * @param WP_User $user    User to be treated as post author for everything created.  
		 */
		do_action( 'wporg_devhub_cli_before_parsing', $path, $version, $user );

		// 1. Deactivate posts-to-posts plugin.
		if ( is_plugin_active( $plugins['posts-to-posts'] ) ) {
			WP_CLI::log( 'Deactivating posts-to-posts plugin...' );
			WP_CLI::runcommand( 'plugin deactivate ' . $plugins['posts-to-posts'] );
		} else {
			WP_CLI::log( 'Warning: plugin posts-to-posts already deactivated.' );
		}

		// 2. Activate phpdoc-parser plugin.
		if ( is_plugin_active( $plugins['phpdoc-parser'] ) ) {
			WP_CLI::log( 'Warning: plugin phpdoc-parser already activated.' );
		} else {
			WP_CLI::log( 'Activating phpdoc-parser plugin...' );
			WP_CLI::runcommand( 'plugin activate ' . $plugins['phpdoc-parser'] );
		}

		// 3. Run the parser.
		WP_CLI::log( 'Running the parser (this will take awhile)...' );
		WP_CLI::runcommand( "parser create {$path} --user={$user_id}" );

		// 4. Deactivate phpdoc-parser plugin.
		WP_CLI::log( 'Deactivating phpdoc-parser plugin...' );
		WP_CLI::runcommand( 'plugin deactivate ' . $plugins['phpdoc-parser'] );

		// 5. Activate posts-to-posts plugin.
		WP_CLI::log( 'Activating posts-to-posts plugin...' );
		WP_CLI::runcommand( 'plugin activate ' . $plugins['posts-to-posts'] );

		// 6. Pre-cache source code.
		WP_CLI::runcommand( 'devhub pre-cache-source' );

		// 7. Clean up after itself.
		WP_CLI::runcommand( 'devhub clean' );

		// Done.
		WP_CLI::success( "Parsing of WP $version is complete." );

		/**
		 * Fires after parsing process completes.
		 *
		 * @param string  $path    Path to the directory containing the WP files to parse.
		 * @param string  $version Versin of WP being parsed.
		 * @param WP_User $user    User to be treated as post author for everything created.  
		 */
		do_action( 'wporg_devhub_cli_after_parsing', $path, $version, $user );
	}

	/**
	 * Pre-caches source for parsed post types that support showing source code.
	 *
	 * By default, source code shown for post types that have source code is read
	 * from the parsed file on page load if not already cached. This pre-caches all
	 * the source code and updates source code that has already been cached.
	 *
	 * ## EXAMPLES
	 *
	 *     wp devhub pre-cache-source
	 *
	 * @when after_wp_load
	 * @subcommand pre-cache-source
	 */
	public function pre_cache_source() {
		WP_CLI::log( 'Pre-caching source code...' );

		$success = DevHub_Parser::cache_source_code();

		if ( $success ) {
			WP_CLI::success( 'Pre-caching of source code is complete.' );
		} else {
			WP_CLI::error( 'Unable to pre-cache source codde.' );
		}
	}

	/**
	 * Cleans up temporary files created for, and used only, in parsing.
	 *
	 * Cleans:
	 * - Temp directory created to install the version of WP to parse
	 * - Removes the phpdoc-parser plugin (only if it was fully installed and
	 *   configured as part of the parsing process)
	 *
	 * Note: This automatically gets called at the end of a successful `parse`
	 * invocation, so it would only need to be directly called if parsing
	 * didn't complete successfully.
	 *
	 * ## EXAMPLES
	 *
	 *     wp devhub clean
	 *
	 * @when after_wp_load
	 */
	public function clean() {
		WP_CLI::log( 'Cleaning up after the parser...' );

		$tmp_dirs = glob( WP_CLI\Utils\get_temp_dir() . 'devhub_*' );

		if ( count( $tmp_dirs ) > 1 ) {
			WP_CLI::log( "Multiple temporary directories were detected. This can be the case if earlier parsings were aborted or cancelled without completing." );
		}

		foreach ( $tmp_dirs as $tmp_dir ) {
			//WP_CLI::log( "Deleting temporary directory $tmp_dir...");
			$cmd = "rm -rf {$tmp_dir}";
			WP_CLI::confirm( "About to delete temporary directory. Does this look proper? `$cmd`" );
			WP_CLI::launch( $cmd, false, true );
		}

		// Remove the phpdoc-parser plugin.
		// @todo Add argument to facilitate disabling this step, or do a git checkout and only delete if no local changes.
		$plugin = 'phpdoc-parser';
		$all_plugins = get_plugins();
		if ( in_array( $plugin . '/plugin.php', array_keys( $all_plugins ) ) ) {
			// Only delete the plugin if it was automatically installed via parse command.
			if ( file_exists( WP_PLUGIN_DIR . '/phpdoc-parser/.devhub-parser-installed' ) ) {
				WP_CLI::log( "Deleting plugin {$plugin}..." );
				WP_CLI::runcommand( "plugin delete {$plugin}" );
			} else {
				WP_CLI::log( "Plugin {$plugin} present but was manually installed or looks to have customizations so it must be deleted manually, if so desired." );
			}
		} else {
			WP_CLI::log( "Plugin {$plugin} not present so it can't be deleted." );
		}

		WP_CLI::success( 'Clean-up is complete.' );
	}

	/**
	 * Returns information pertaining to the last parsing.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : The information from the last parsing to obtain. One of: 'date', 'import-dir', 'version'.
	 *
	 * ## EXAMPLES
	 *
	 *     wp devhub last-parsed version
	 *
	 * @when after_wp_load
	 * @subcommand last-parsed
	 */
	public function last_parsed( $args, $assoc_args ) {
		list( $key ) = $args;

		$valid_values = array(
			'date'       => 'wp_parser_last_import',
			'import-dir' => 'wp_parser_root_import_dir',
			'version'    => 'wp_parser_imported_wp_version',
		);

		if ( empty( $valid_values[ $key ] ) ) {
			WP_CLI::error( 'Invalid value provided. Must be one of: ' . implode( ', ', array_keys( $valid_values ) ) );
		}

		$option = $valid_values[ $key ];

		$value = get_option( $option );

		if ( 'date' === $key ) {
			$value = date_i18n( 'Y-m-d H:i', $value );
		}

		if ( ! $value ) {
			WP_CLI::error( 'No value from previous parsing of WordPress source was detected.' );
		} else {
			WP_CLI::log( $value );
		}
	}

}

WP_CLI::add_command( 'devhub', 'DevHub_Command' );
