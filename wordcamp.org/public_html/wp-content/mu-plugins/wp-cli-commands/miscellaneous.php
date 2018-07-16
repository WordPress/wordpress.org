<?php

defined( 'WP_CLI' ) or die();

/**
 * WordCamp.org: Miscellaneous commands.
 */
class WordCamp_CLI_Miscellaneous extends WP_CLI_Command {
	/**
	 * Sets skip-feature flags on existing sites when new functionality is introduced
	 *
	 * See wcorg_skip_feature() for context.
	 *
	 * ## OPTIONS
	 *
	 * <flag_name>
	 * : The name of the flag that will be set
	 *
	 * [<max_site_id>]
	 * : The ID of the newest site that the flag will be set on. If empty,
	 * the flag will be applied to all sites.
	 *
	 * [--dry-run]
	 * : Show a report, but don't perform the changes.
	 *
	 * ## EXAMPLES
	 *
	 * wp wc-misc set-skip-feature-flag wcb_viewport_initial_scale
	 * wp wc-misc set-skip-feature-flag wcb_viewport_initial_scale 437
	 * wp wc-misc set-skip-feature-flag wcb_viewport_initial_scale 437 --dry-run
	 *
	 * @subcommand set-skip-feature-flag
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function set_skip_feature_flag( $args, $assoc_args ) {
		$flag_name   = $args[0];
		$max_site_id = empty( $args[1] ) ? false : absint( $args[1] );
		$dry_run     = isset( $assoc_args[ 'dry-run' ] );
		$sites       = get_sites( array( 'number' => 0 ) );
		$notify      = new \cli\progress\Bar( 'Applying flag', count( $sites ) );
		$results     = array();

		WP_CLI::line();

		foreach ( $sites as $site ) {
			switch_to_blog( $site['blog_id'] );

			$site_domain = parse_url( get_option( 'siteurl' ) );
			$site_domain = sprintf( '%s%s', $site_domain['host'], $site_domain['path'] );

			// Skip sites that are above the requested maximum site ID
			if ( $max_site_id && $site['blog_id'] > $max_site_id ) {
				$results[] = array( $site_domain, 'skipped' );
				continue;
			}

			// Apply the flag to the requested sites
			$flags = get_option( 'wordcamp_skip_features', array() );
			$flags[ $flag_name ] = true;

			if ( ! $dry_run ) {
				update_option( 'wordcamp_skip_features', $flags );
			}

			$results[] = array( $site_domain, 'applied' );

			restore_current_blog();
			$notify->tick();
		}

		$notify->finish();

		WP_CLI::line();
		$table = new \cli\Table();
		$table->setHeaders( array( 'Site', 'Action' ) );
		$table->setRows( $results );
		$table->display();

		WP_CLI::line();
		WP_CLI::success( sprintf(
			'%s has been applied to all sites%s.',
			$flag_name,
			$max_site_id ? ' up through ' . $max_site_id : ''
		) );

		if ( $dry_run ) {
			WP_CLI::warning( 'This was only a dry-run.' );
		}
	}

	/**
	 * Get or modify the state of a skip-feature flag on a single site.
	 *
	 * See wcorg_skip_feature() for context.
	 *
	 * ## OPTIONS
	 *
	 * <command>
	 * : The skip-feature command to execute on a site.
	 * ---
	 * options:
	 *   - get
	 *   - set
	 *   - unset
	 * ---
	 *
	 * <flag_name>
	 * : The name of the flag to get or modify.
	 *
	 * <blog_id>
	 * : The numeric ID of the site on which the skip-feature command will be executed.
	 *
	 * ## EXAMPLES
	 *
	 * wp wc-misc skip-feature-flag get wcb_viewport_initial_scale 437
	 * wp wc-misc skip-feature-flag set wcb_viewport_initial_scale 437
	 * wp wc-misc skip-feature-flag unset wcb_viewport_initial_scale 437
	 *
	 * @subcommand skip-feature-flag
	 *
	 * @param array $args
	 *
	 * @throws \WP_CLI\ExitException
	 */
	public function skip_feature_flag( $args ) {
		$command   = ( $args[0] ) ?: '';
		$flag_name = ( $args[1] ) ?: '';
		$blog_id   = ( $args[2] ) ? absint( $args[2] ) : 0;

		WP_CLI::line();

		$commands = array( 'get', 'set', 'unset' );

		if ( ! in_array( $command, $commands, true ) ) {
			WP_CLI::error( 'Invalid command. Use `get`, `set`, or `unset` for the first argument.' );
		}

		if ( ! $flag_name ) {
			WP_CLI::error( 'Invalid flag name.' );
		}

		if ( ! $blog_id ) {
			WP_CLI::error( 'Invalid blog ID.' );
		}

		switch_to_blog( $blog_id );

		$flags = get_option( 'wordcamp_skip_features', array() );

		switch ( $command ) {
			case 'get':
				if ( array_key_exists( $flag_name, $flags ) && true === $flags[ $flag_name ] ) {
					$message = sprintf(
						'The %s flag is SET on blog %d.',
						$flag_name,
						$blog_id
					);
				} else {
					$message = sprintf(
						'The %s flag is NOT SET on blog %d.',
						$flag_name,
						$blog_id
					);
				}
				break;
			case 'set':
				$flags[ $flag_name ] = true;
				update_option( 'wordcamp_skip_features', $flags );
				$message = sprintf(
					'The %s flag was successfully SET for blog %d.',
					$flag_name,
					$blog_id
				);
				break;
			case 'unset':
				unset( $flags[ $flag_name ] );
				update_option( 'wordcamp_skip_features', $flags );
				$message = sprintf(
					'The %s flag was successfully UNSET for blog %d.',
					$flag_name,
					$blog_id
				);
				break;
		}

		restore_current_blog();

		WP_CLI::line( $message );
	}

	/**
	 * Print a log with our custom entries formatted for humans
	 *
	 * ## OPTIONS
	 *
	 * <raw_log>
	 * : The raw log contents, or the filename of the raw log
	 *
	 * [--foreign=<action>]
	 * : Include foreign log entries from the output, or ignore them
	 * ---
	 * default: include
	 * options:
	 *   - include
	 *   - ignore
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 * wp wc-misc format-log /var/log/php-errors.log
	 * wp wc-misc format-log "$(grep 'foo' /var/log/php-errors.log -C 10)" |less -S
	 * wp wc-misc format-log "$(grep 'bar' /var/log/php-errors.log)" --foreign=ignore
	 *
	 * @todo Sometimes example passing entries as command line param fails because it passes the length limit.
	 *       Add an example of a good workaround for that.
	 *
	 * @subcommand format-log
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function format_log( $args, $assoc_args ) {
		list( $raw_log ) = $args;

		if ( is_file( $raw_log ) ) {
			$raw_log = file_get_contents( $raw_log );
		}

		$formatted_log = \WordCamp\Logger\format_log( $raw_log, $assoc_args['foreign'] );

		WP_CLI::line( "\n" . $formatted_log );
	}
}
