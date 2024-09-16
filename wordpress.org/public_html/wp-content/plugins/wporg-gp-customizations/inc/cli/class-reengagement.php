<?php
/**
 * Plugin Name: WPORG GlotPress Customizations
 * Description: Customizations for GlotPress.
 *
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
class Reengagement extends WP_CLI_Command {
	/**
	 * The date for the check.
	 *
	 * @var string
	 */
	private string $date;

	/**
	 * The start date for the check.
	 *
	 * @var string
	 */
	private string $start_date;

	/**
	 * The end date for the check.
	 *
	 * @var string
	 */
	private string $end_date;

	/**
	 * The last id of the translations.
	 *
	 * @var int
	 */
	private int $highest_id_translation;

	/**
	 * If the command is in dry-run mode.
	 *
	 * @var bool
	 */
	private bool $dry_run;

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

		$this->set_dates( $assoc_args );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->highest_id_translation = $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->gp_translations}" );
		$this->dry_run                = isset( $assoc_args['dry-run'] );
		$translators                  = $this->get_translators_with_approved_translation_specific_day();
		WP_CLI::line( print_r( $translators, true ) );
		$first_time_translators = $this->get_translators_with_first_translation_specific_day( $translators );
		WP_CLI::line( print_r( $first_time_translators, true ) );
		$this->send_email_to_translators( $first_time_translators );
	}

	/**
	 * Set the dates for the check.
	 *
	 * @param array $assoc_args Associative arguments.
	 */
	private function set_dates( array $assoc_args ) {
		$yesterday  = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
		$this->date = $yesterday;

		if ( isset( $assoc_args['date'] ) ) {
			$input_date  = $assoc_args['date'];
			$date_format = 'Y-m-d';
			$start_date  = '2010-02-17';
			$end_date    = $yesterday;

			$d = DateTime::createFromFormat( $date_format, $input_date );
			if ( $d && $d->format( $date_format ) === $input_date ) {
				if ( $input_date >= $start_date && $input_date <= $end_date ) {
					$this->date = $input_date;
				}
			}
		}
		$this->start_date = $this->date . ' 00:00:00';
		$this->end_date   = $this->date . ' 23:59:59';
		WP_CLI::line( "Start date: {$this->start_date}" );
		WP_CLI::line( "End date: {$this->end_date}" );
	}

	/**
	 * Get translators' id with a translation approved on a specific day.
	 *
	 * @return array An array with the translators' id.
	 */
	private function get_translators_with_approved_translation_specific_day(): array {
		global $wpdb;

		$translators = array();
		$batch_size  = 10_000_000;
		// $first_id = 0;
		// Todo: Change this to 0
		$first_id = 120_000_000;

		do {
			$query = $wpdb->prepare(
				"SELECT DISTINCT user_id 
         FROM `{$wpdb->gp_translations}`  
         WHERE status = 'current' 
         AND date_modified BETWEEN %s AND %s
         AND id > %d
         AND user_id > 0
         ORDER BY id ASC
         LIMIT %d",
				$this->start_date,
				$this->end_date,
				$first_id,
				$batch_size
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,  WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$batch_translators = $wpdb->get_col( $query );
			$translators       = array_merge( $translators, $batch_translators );
			$first_id         += $batch_size;
		} while ( $first_id < $this->highest_id_translation );

		return $translators;
	}

	/**
	 * Get translators' id with the first translation approved on a specific day.
	 *
	 * @param array $translators An array with the translators' id.
	 *
	 * @return array
	 */
	private function get_translators_with_first_translation_specific_day( array $translators ): array {
		global $wpdb;

		$first_time_translators = array();

		foreach ( $translators as $translator_id ) {
			$first_translation_date = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MIN(date_modified) 
                 FROM `{$wpdb->gp_translations}` 
                 WHERE user_id = %d",
					$translator_id
				)
			);
			if ( $first_translation_date && gmdate( 'Y-m-d', strtotime( $first_translation_date ) ) === $this->date ) {
				$first_time_translators[] = $translator_id;
			}
		}

		return $first_time_translators;
	}

	/**
	 * Send one email to each translator, congratulating them on their first approved translation
	 *
	 * @param array $first_time_translators An array with the translators' id.
	 *
	 * @return void
	 */
	private function send_email_to_translators( array $first_time_translators ): void {
		foreach ( $first_time_translators as $translator_id ) {

			$user = get_user_by( 'id', $translator_id );
			if ( ! $user ) {
				continue;
			}

			$reengagement_options = get_user_meta( $translator_id, 'gp_reengagement', true );

			if ( ! is_array( $reengagement_options ) ) {
				$reengagement_options = array();
			}

			if ( ! empty( $reengagement_options['first_translation_approved_date'] ) ) {
				continue;
			}

			// translators: Email subject.
			$subject = __( 'Your first translation has been approved!', 'wporg' );
			$message = sprintf(
				// translators: Email body. %s: Display name.
				esc_html__(
					'Congratulations %s,
			
Your first translation at https://translate.wordpress.org has been approved. Keep up the great work!
			
Have a nice day
			
The Global Polyglots Team
			',
					'wporg'
				),
				$user->display_name
			);

			if ( ! $this->dry_run ) {
				WP_CLI::line( "Sending email to: {$user->user_email}" );
				wp_mail( $user->user_email, $subject, $message );
				$reengagement_options['first_translation_approved_date'] = current_time( 'mysql' );
				update_user_meta( $translator_id, 'gp_reengagement', $reengagement_options );
			} else {
				WP_CLI::line( "Skipping mailing to: {$user->user_email}" );
			}
		}
	}

}
