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
		$sites       = wp_get_sites( array( 'limit' => false ) );
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
}
