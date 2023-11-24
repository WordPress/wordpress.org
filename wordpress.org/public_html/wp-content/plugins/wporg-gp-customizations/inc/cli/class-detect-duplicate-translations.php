<?php
/**
 * This class detects duplicate translations in the database in "current" status and updates them.
 *
 * @package WordPressdotorg\GlotPress\Customizations\CLI
 */

namespace WordPressdotorg\GlotPress\Customizations\CLI;

/**
 * Class Detect_Duplicate_Translations
 */
class Detect_Duplicate_Translations {

	/**
	 * The lower limit for translation_set_id.
	 *
	 * @var int
	 */
	private int $low_limit = 0;

	/**
	 * The upper limit for translation_set_id.
	 *
	 * Note: It must be greater than the highest translation set id available in the database.
	 *
	 * @var int
	 */
	private int $high_limit;

	/**
	 * The step size for iteration.
	 *
	 * With steps of 10,000 we sometimes have timeouts in the database.
	 *
	 * @var int
	 */
	private int $step = 1000;

	/**
	 * The duplicated entries.
	 *
	 * @var array
	 */
	private array $duplicates = array();

	/**
	 * If true, show the results in the CLI.
	 *
	 * @var bool
	 */
	private bool $verbose = false;

	/**
	 * If true, show the SQL queries to show and to update the duplicate entries.
	 *
	 * @var bool
	 */
	private bool $print_sql = false;

	/**
	 * Here we store the last MySQL error.
	 *
	 * @var string
	 */
	private string $last_sql_error = '';

	/**
	 * Detect_Duplicate_Translations constructor.
	 *
	 * @param bool $update_values If true, update the duplicate entries.
	 * @param bool $notify        If true, notify the duplicate entries in Slack.
	 * @param bool $verbose       If true, show the results in the CLI.
	 * @param bool $print_sql     If true, show the SQL queries to show and to update the duplicate entries.
	 *
	 * @return void
	 */
	public function __invoke( bool $update_values = false, bool $notify = false, bool $verbose = false, bool $print_sql = false ) {
		global $wpdb;
		$this->verbose    = $verbose;
		$this->print_sql  = $print_sql;
		$this->high_limit = $this->get_high_limit();
		$this->duplicates = $this->get_duplicates();
		if ( $update_values ) {
			$this->update_duplicates();
		}
		if ( $notify ) {
			$this->notify_duplicates();
		}
	}

	/**
	 * Get the highest translation_set_id in the translations table.
	 *
	 * Round up the highest translation_set_id to the next multiple of $this->step.
	 *
	 * @return int
	 */
	private function get_high_limit() {
		global $wpdb;
		$max_translation_set_id = $wpdb->get_var( "SELECT MAX(translation_set_id) FROM {$wpdb->gp_translations}" );
		if ( $this->verbose ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'Highest translation_set_id: ' . number_format_i18n( $max_translation_set_id ) . "\n";
		}

		if ( $wpdb->last_error ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'Error in the SQL query: ' . $wpdb->last_error;
			$this->last_sql_error = $wpdb->last_error;
		}

		return ceil( $max_translation_set_id / $this->step ) * $this->step;
	}

	/**
	 * Get the duplicate entries from the translations table.
	 *
	 * @return array The duplicated entries.
	 */
	private function get_duplicates(): array {
		global $wpdb;
		$duplicate_entries = array();

		for ( $limit = $this->low_limit; $limit <= $this->high_limit; $limit += $this->step ) {
			$upper_limit    = $limit + $this->step - 1;
			$prepared_query = $wpdb->prepare(
				"SELECT original_id, translation_set_id
				FROM {$wpdb->gp_translations}
				WHERE translation_set_id BETWEEN %d AND %d
				AND status = 'current'
				GROUP BY original_id, translation_set_id
				HAVING COUNT(*) > 1;",
				$limit,
				$upper_limit
			);
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$results = $wpdb->get_results( $prepared_query, ARRAY_A );

			if ( $wpdb->last_error ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo 'Error in the SQL query: ' . $wpdb->last_error;
				$this->last_sql_error = $wpdb->last_error;
				break;
			}

			$duplicate_entries = array_merge( $duplicate_entries, $results );

			if ( $this->print_sql ) {
				foreach ( $results as $result ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo "- Translation_set_id: {$result['translation_set_id']}, Original_id: {$result['original_id']} -> ";
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo "SELECT * FROM `{$wpdb->gp_translations}` WHERE `original_id` = {$result['original_id']} AND `translation_set_id` = {$result['translation_set_id']}\n";
				}
			}
		}
		if ( $this->verbose ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'Number of duplicate entries: ' . number_format_i18n( count( $duplicate_entries ) ) . "\n";
		}
		return $duplicate_entries;
	}

	/**
	 * Update the duplicated entries.
	 *
	 * Set all translations to old except the last one, updating the date_modified and user_id_last_modified fields.
	 *
	 * @return void
	 */
	private function update_duplicates() {
		global $wpdb;

		foreach ( $this->duplicates as $duplicate ) {
			$update_query = $wpdb->prepare(
				"UPDATE {$wpdb->gp_translations}
				SET status = CASE
					WHEN id = (
						SELECT id
						FROM (
							SELECT id
							FROM {$wpdb->gp_translations}
							WHERE original_id = %d
							AND translation_set_id = %d
							AND status = 'current'
							ORDER BY date_modified DESC, id DESC
							LIMIT 1
						) AS subquery
					) THEN 'current'
					ELSE 'old'
				END,
				user_id_last_modified = CASE
					WHEN id = (
						SELECT id
						FROM (
							SELECT id
							FROM {$wpdb->gp_translations}
							WHERE original_id = %d
							AND translation_set_id = %d
							AND status = 'current'
							ORDER BY date_modified DESC, id DESC
							LIMIT 1
						) AS subquery
					) THEN user_id_last_modified
					ELSE NULL
				END,
				date_modified = CASE
					WHEN id = (
						SELECT id
						FROM (
							SELECT id
							FROM {$wpdb->gp_translations}
							WHERE original_id = %d
							AND translation_set_id = %d
							AND status = 'current'
							ORDER BY date_modified DESC, id DESC
							LIMIT 1
						) AS subquery
					) THEN date_modified
					ELSE NOW()
				END
				WHERE original_id = %d
				AND translation_set_id = %d
				AND status = 'current';
			",
				$duplicate['original_id'],
				$duplicate['translation_set_id'],
				$duplicate['original_id'],
				$duplicate['translation_set_id'],
				$duplicate['original_id'],
				$duplicate['translation_set_id'],
				$duplicate['original_id'],
				$duplicate['translation_set_id']
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $update_query );

			if ( $wpdb->last_error ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo 'Error in the SQL query: ' . $wpdb->last_error;
				$this->last_sql_error = $wpdb->last_error;
				break;
			}

			if ( $this->print_sql ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo "- Translation_set_id: {$duplicate['translation_set_id']}, Original_id: {$duplicate['original_id']} -> ";
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $update_query . "\n";
			}
			break; // todo: remove this break to update all the duplicates.
		}

		if ( $this->verbose ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'Number of translations updated: ' . number_format_i18n( count( $this->duplicates ) ) . "\n";
		}
	}

	/**
	 * Notify the duplicates in Slack.
	 *
	 * @return void
	 */
	private function notify_duplicates() {
		$mysql_error = '';
		if ( $this->last_sql_error ) {
			$mysql_error = "The last MySQL error was: " . $this->last_sql_error . "\n";
		}

		if ( 0 == count( $this->duplicates ) ) {
			$message = ":white_check_mark: There are no duplicate translations in the database with *current* status.\n";
			$message .= $mysql_error;
		} else {
			$message  = ":warning: There are " . count( $this->duplicates ) . " duplicate translations in the database with *current* status.\n\n";
			$message .= "Execute *'wp wporg-translate detect-duplicate-translations --url=translate.wordpress.org --verbose'* to show the duplicate entries.\n";
			$message .= "Execute *'wp wporg-translate detect-duplicate-translations --url=translate.wordpress.org --update-values'* to update the duplicate entries, solving the problem.\n";
			$message .= $mysql_error;
			$message .= "\ncc @akirk @Tosin @amieiro";
		}

		$slack_channel = WPORG_SANDBOXED ? null : 'dotorg';
		function_exists( 'slack_dm' ) && slack_dm( $message, $slack_channel );
	}
}
