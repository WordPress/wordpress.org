<?php
/**
 * Implements devhub commands.
 */
class DevHub_Command extends WP_CLI_Command {

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

}

WP_CLI::add_command( 'devhub', 'DevHub_Command' );
