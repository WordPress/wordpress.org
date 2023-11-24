<?php
/**
 * This WP-CLI command detects duplicate translations in the database in "current" status.
 *
 * To execute this command, you need to use this text in the CLI:
 *
 * wp wporg-translate detect-duplicate-translations --url=translate.wordpress.org
 *
 * @package WordPressdotorg\GlotPress\Customizations\CLI
 */

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use WP_CLI_Command;

/**
 * Class Detect_Duplicate_Translations_CLI
 */
class Detect_Duplicate_Translations_CLI extends WP_CLI_Command {
	/**
	 * Detect duplicate translations in the database in "current" status and update them.
	 *
	 * ## OPTIONS
	 * [--<update>]
	 *       Update the duplicates, setting all translations to old except the last one.
	 *
	 * [--<notify>]
	 *       Notify the duplicates in Slack.
	 *
	 * [--<verbose>]
	 *       Show the results in the CLI.
	 *
	 * [--<print-sql>]
	 *       Show the SQL queries to show and to update the duplicate entries.
	 *
	 * ## EXAMPLES
	 *
	 * wp wporg-translate detect-duplicate-translations --url=translate.wordpress.org
	 * wp wporg-translate detect-duplicate-translations --url=translate.wordpress.org --update
	 * wp wporg-translate detect-duplicate-translations --url=translate.wordpress.org --notify
	 * wp wporg-translate detect-duplicate-translations --url=translate.wordpress.org --update --verbose --print-sql
	 *
	 * @param array $args       The arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$update_values = false;
		$notify        = false;
		$verbose       = false;
		$print_sql     = false;
		if ( array_key_exists( 'update', $assoc_args ) ) {
			$update_values = true;
		}
		if ( array_key_exists( 'notify', $assoc_args ) ) {
			$notify = true;
		}
		if ( array_key_exists( 'verbose', $assoc_args ) ) {
			$verbose = true;
		}
		if ( array_key_exists( 'print-sql', $assoc_args ) ) {
			$print_sql = true;
		}

		$detect_duplicates = new Detect_Duplicate_Translations();
		$detect_duplicates( $update_values, $notify, $verbose, $print_sql );
	}
}
