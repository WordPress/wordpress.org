<?php
/**
 * This WP-CLI command sends an email to translators who for the first time had a translation approved.
 *
 * @package wporg-gp-customizations
 */

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use DateTime;
use WP_CLI;
use WP_CLI_Command;

/**
 * Sends an email to translators who for the first time had a translation approved.
 */
class Reengagement_First_Translation_CLI extends WP_CLI_Command {
	/**
	 * Send an email to translators who for the first time today had a translation approved.
	 *
	 * ## OPTIONS
	 *
	 * [--date=<start_date>]
	 * : The date for the check.
	 *   If not provided, defaults to today.
	 *
	 * [--dry-run]
	 * : If set, the command will not send any emails and won't store the reengagement options.
	 *
	 * ## EXAMPLES
	 *
	 *     # Show the emails who should receive the notification, without sending them.
	 *     wp wporg-translate reengagement --dry-run --url=translate.wordpress.org
	 *
	 *     # Send an email for the users who have had their first translation approved on 2023-10-25.
	 *     wp wporg-translate reengagement --date=2024-09-13 --url=translate.wordpress.org
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function __invoke( array $args, array $assoc_args ) {
		global $wpdb;

		$date = $assoc_args['date'] ?? gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
		$dry_run = isset( $assoc_args['dry-run'] );

		$reengagement = new Reengagement_First_Translation();
		$reengagement( $date, $dry_run );
	}
}
