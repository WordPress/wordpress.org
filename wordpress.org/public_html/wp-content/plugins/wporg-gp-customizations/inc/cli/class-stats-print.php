<?php

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use DateTime;
use WP_CLI;
use WP_CLI_Command;

class Stats_Print extends WP_CLI_Command  {

	/**
	 * Prints statistics starting from a specified date.
	 *
	 * ## OPTIONS
	 *
	 * [--start_date=<start_date>]
	 * : The start date for the statistics in 'Y-m-d' format.
	 *   If not provided, defaults to one week before the current date.
	 *
	 * ## EXAMPLES
	 *
	 *     # Print statistics starting from one week before the current date.
	 *     wp wporg-translate show-stats --url=translate.wordpress.org
	 *
	 *     # Print statistics starting from 2023-10-25.
	 *     wp wporg-translate show-stats --start_date=2023-10-25 --url=translate.wordpress.org
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( !isset( $assoc_args['start_date'] ) ) {
			$start_date = date( 'Y-m-d', strtotime( '-1 week' ) );
		} else {
			$start_date     = $assoc_args['start_date'];
			$start_datetime = DateTime::createFromFormat( 'Y-m-d', $start_date ) ;
			$is_valid_date  = $start_datetime && $start_datetime->format( 'Y-m-d' ) === $start_date;
			if ( ! $is_valid_date ) {
				WP_CLI::error( 'The --start_date parameter must be in the format Y-m-d. E.g.: 2023-10-24' );
				return;
			}
		}
		$stats = new Stats();
		$stats( true, $start_date );
	}
}