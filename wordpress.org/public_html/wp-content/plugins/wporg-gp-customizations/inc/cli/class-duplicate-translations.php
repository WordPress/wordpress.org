<?php
/**
 * This class detects duplicate translations in the database in "current" status and updates them.
 *
 * @package WordPressdotorg\GlotPress\Customizations\CLI
 */

namespace WordPressdotorg\GlotPress\Customizations\CLI;

/**
 * Class Duplicate_Translations
 */
class Duplicate_Translations {

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
	 * Duplicate_Translations constructor.
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
			echo "Error in the SQL query. Last query:\n" . $wpdb->last_query . "\nLast error: " . $wpdb->last_error . "\n";
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
				echo "Error in the SQL query. Last query:\n" . $wpdb->last_query . "\nLast error: " . $wpdb->last_error . "\n";
				$this->last_sql_error = $wpdb->last_error;
				break;
			}

			$duplicate_entries = array_merge( $duplicate_entries, $results );

			if ( $this->print_sql ) {
				foreach ( $results as $result ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo "- Translation_set_id: {$result['translation_set_id']}, Original_id: {$result['original_id']} -> ";
					$prepared_query = $wpdb->prepare("SELECT * FROM `{$wpdb->gp_translations}` WHERE `original_id` = %d AND `translation_set_id` = %d AND `status`='current';",
						$result['original_id'],
						$result['translation_set_id'],
					);
					echo $prepared_query . "\n";
					// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
					$duplicated = $wpdb->get_results( $prepared_query, ARRAY_A );
					foreach ( $duplicated as $entry ) {
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo "  - translation_id: {$entry['id']}, date_added: {$entry['date_added']}, date_modified: {$entry['date_modified']}, user_id: {$entry['user_id']}, user_id_last_modified: {$entry['user_id_last_modified']}, translation: {$entry['translation_0']}\n";
					}
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
			$id_to_not_update = $wpdb->get_var(
				$wpdb->prepare("
					SELECT id
					FROM {$wpdb->gp_translations}
					WHERE original_id = %d
					AND translation_set_id = %d
					AND status = 'current'
					ORDER BY date_modified DESC, id DESC
					LIMIT 1
					",
					$duplicate['original_id'],
					$duplicate['translation_set_id']
				)
			);

			$update_query = $wpdb->prepare("
				UPDATE {$wpdb->gp_translations}
				SET status = 'fuzzy',
				user_id_last_modified = NULL,
				date_modified = NOW()
				WHERE original_id = %d
				AND translation_set_id = %d
				AND id != %d
				AND status = 'current';
			",
				$duplicate['original_id'],
				$duplicate['translation_set_id'],
				$id_to_not_update
			);

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query( $update_query );

			if ( $wpdb->last_error ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo "Error in the SQL query. Last query:\n" . $wpdb->last_query . "\nLast error: " . $wpdb->last_error . "\n";
				$this->last_sql_error = $wpdb->last_error;
				break;
			}

			if ( $this->print_sql ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo "- Translation_set_id: {$duplicate['translation_set_id']}, Original_id: {$duplicate['original_id']} -> ";
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo $update_query . "\n";
			}
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
		$message     = '';
		$matrix_room = 'polyglots-duplicated-translations';
		$send_result = false;

		if ( $this->last_sql_error ) {
			$mysql_error = "The last MySQL error was: " . $this->last_sql_error . "\n";
		}

		if ( 0 == count( $this->duplicates ) ) {
			$message = "There are no duplicate translations in the database with *current* status.\n\n";
		} else {
			$message  = "There are " . count( $this->duplicates ) . " duplicate translations in the database with *current* status.\n\n";
			$message .= "Execute `wp wporg-translate duplicate-translations --url=translate.wordpress.org --verbose` to show the duplicate entries.\n\n";
			$message .= "Execute `wp wporg-translate duplicate-translations --url=translate.wordpress.org --fix` to update the duplicate entries, solving the problem.\n\n";
		}
		$message .= $mysql_error;

		require_once '/home/api/public_html/dotorg/matrix/poster.php';
		$send_result = \DotOrg\Matrix\Poster::force_send( $matrix_room, $message );

		if ( $this->verbose ) {
			if ( $send_result ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo "Message sent to the " . $matrix_room . ' room: ' . $message . "\n";
			} else {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo "Message not sent to the " . $matrix_room . ' room: ' . $message . "\n";
			}
		}

	}
}
