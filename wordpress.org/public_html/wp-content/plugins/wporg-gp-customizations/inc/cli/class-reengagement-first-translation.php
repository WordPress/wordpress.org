<?php
/**
 * This class sends an email to translators who for the first time had a translation approved.
 *
 * @package wporg-gp-customizations
 */

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use DateTime;
use GP;
use GP_Locale;
use WP_CLI;
use WP_Query;

/**
 * Sends an email to translators who for the first time had a translation approved.
 */
class Reengagement_First_Translation {
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
	 * @param string $date    The date for the check.
	 * @param bool   $dry_run If set, the command will not send any emails and won't store the reengagement options.
	 *
	 * @return void
	 */
	public function __invoke( string $date = null, bool $dry_run = false ) {
		global $wpdb;

		$this->set_dates( $date );
		$this->dry_run = $dry_run;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->highest_id_translation = $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->gp_translations}" );
		$translators                  = $this->get_translators_with_approved_translation_specific_day();
		$first_time_translators       = $this->get_translators_with_first_translation_specific_day( $translators );
		$this->send_email_to_translators( $first_time_translators );
	}

	/**
	 * Set the dates for the check.
	 *
	 * @param string|null $date The date for the check.
	 */
	private function set_dates( ?string $date ) {
		$yesterday  = gmdate( 'Y-m-d', strtotime( 'yesterday' ) );
		$this->date = $yesterday;

		if ( isset( $date ) ) {
			;
			$date_format = 'Y-m-d';
			$start_date  = '2010-02-17';
			$end_date    = $yesterday;

			$d = DateTime::createFromFormat( $date_format, $date );
			if ( $d && $d->format( $date_format ) === $date ) {
				if ( $date >= $start_date && $date <= $end_date ) {
					$this->date = $date;
				}
			}
		}
		$this->start_date = $this->date . ' 00:00:00';
		$this->end_date   = $this->date . ' 23:59:59';
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
		// Split the query in batches to avoid timeouts.
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
	 * Get translators' id with the first translation approved on a specific day and the id of the first translation.
	 *
	 * @param array $translators An array with the translators' id.
	 *
	 * @return array An array with the translators' id as key and the id of the first translation.
	 */
	private function get_translators_with_first_translation_specific_day( array $translators ): array {
		global $wpdb;

		$first_time_translators = array();

		foreach ( $translators as $translator_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT MIN(date_modified) as first_translation_date, id
						FROM `{$wpdb->gp_translations}`
						WHERE user_id = %d",
					$translator_id
				)
			);
			if ( $result && gmdate( 'Y-m-d', strtotime( $result->first_translation_date ) ) === $this->date ) {
				$first_time_translators[ $translator_id ] = $result->id;
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
		foreach ( $first_time_translators as $translator_id => $translation_id ) {

			$user = get_user_by( 'id', $translator_id );
			if ( ! $user ) {
				continue;
			}

			$translation = GP::$translation->get( $translation_id );
			if ( ! $translation ) {
				continue;
			}

			$original = GP::$original->get( $translation->original_id );
			if ( ! $original ) {
				continue;
			}

			$project = GP::$project->get( $original->project_id );
			if ( ! $project ) {
				continue;
			}

			$translation_set = GP::$translation_set->get( $translation->translation_set_id );

			$reengagement_options = get_user_meta( $translator_id, 'gp_reengagement', true );

			if ( ! is_array( $reengagement_options ) ) {
				$reengagement_options = array();
			}

			if ( ! empty( $reengagement_options['first_translation_approved_date'] ) ) {
				continue;
			}

			$translation_url = gp_url_join( gp_url_public_root(), 'projects', $project->path, $translation_set->locale, '/', $translation_set->slug ) . '?filters%5Bstatus%5D=either&filters%5Boriginal_id%5D=' . $original->id . '&filters%5Btranslation_id%5D=' . $translation->id;
			$project_url     = gp_url_join( gp_url_public_root(), 'projects', $project->path, $translation_set->locale, '/', $translation_set->slug );

			// translators: Email subject.
			$subject = __( 'Your first translation has been approved!', 'wporg' );
			$message = sprintf(
			// translators: Email body. %1$s: Display name. %2$s: Translation URL. %3$s: Project URL.
				'
Congratulations %1$s,
<br><br>
Your <a href="%2$s" target="_blank">first translation</a> in <a href="%3$s" target="_blank">this project</a> has been approved. Keep up the great work!
<br><br>
Have a nice day
<br><br>
The Global Polyglots Team
',
				$user->display_name,
				$translation_url,
				$project_url
			);

			$allowed_html = array(
				'a'  => array(
					'href'   => array(),
					'target' => array(),
				),
				'br' => array(),
			);

			$message = wp_kses( $message, $allowed_html );

			$headers = array(
				'Content-Type: text/html; charset=UTF-8',
				'From: Translating WordPress.org <no-reply@wordpress.org>',
			);

			if ( ! $this->dry_run ) {
				WP_CLI::line( "Sending email to: {$user->user_email}" );
				wp_mail( $user->user_email, $subject, $message, $headers );
				$reengagement_options['first_translation_approved_date'] = current_time( 'mysql' );
				update_user_meta( $translator_id, 'gp_reengagement', $reengagement_options );
			} else {
				WP_CLI::line( "Skipping mailing to: {$user->user_email}" );
			}
		}
	}
}
