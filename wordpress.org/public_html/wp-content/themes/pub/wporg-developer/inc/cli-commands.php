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
	 * <path>
	 * : The path to a copy of WordPress to be parsed. Should not be code used in
	 * an active install.
	 *
	 * <user-id>
	 * : ID of user to attribute all parsed posts to.
	 *
	 * ## EXAMPLES
	 *
	 *     # Parse WP 4.9.5 into DevHub.
	 *     $ wp devhub /home/wporgdev/developer/wp495 3606
	 *
	 * @when after_wp_load
	 */
	public function parse( $args, $assoc_args ) {
		list( $path, $user_id ) = $args;

		if ( ! is_numeric( $user_id ) ) {
			WP_CLI::error( 'Invalid user_id provided.' );
		} else {
			$user_id = (int) $user_id;
		}
		$user = get_user_by( 'id', $user_id );
		if ( ! $user ) {
			WP_CLI::error( 'Invalid user_id provided.' );
		}
		WP_CLI::log( "Importing as user ID $user_id ({$user->user_nicename})." );

		$plugins = [
			'phpdoc-parser'  => 'phpdoc-parser/plugin.php',
			'posts-to-posts' => 'posts-to-posts/posts-to-posts.php',
		];

		// Verify path is a file or directory.
		if ( ! file_exists( $path ) ) {
			WP_CLI::error( 'Path for WordPress source to parse does not exist.' );
		}

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

		// Verify that the PHP-Parser plugin is available locally.
		$all_plugins = get_plugins();
		if ( ! in_array( $plugins['phpdoc-parser'], array_keys( $all_plugins ) ) ) {
			// TODO: Attempt to install the plugin automatically.
			WP_CLI::error( 'The PHP-Parser plugin (from https://github.com/WordPress/phpdoc-parser) is not installed locally in ' . WP_PLUGIN_DIR . '/.' );
		}

		// Confirm the parsing.
		WP_CLI::confirm( "Are you sure you want to parse the source code for WP {$version} (and that you've run a backup of the existing data)?" );

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

		// Done.
		WP_CLI::success( "Parsing of WP $version is complete." );
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
