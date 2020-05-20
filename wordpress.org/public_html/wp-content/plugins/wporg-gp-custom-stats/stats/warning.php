<?php

/**
 * This class records translation warnings.
 */
class WPorg_GP_Warning_Stats {

	private $warning_stats = array();

	public function __construct() {
		global $wpdb, $gp_table_prefix;

		add_action( 'gp_translation_created', array( $this, 'translation_updated' ) );
		add_action( 'gp_translation_saved', array( $this, 'translation_updated' ) );

		// DB Writes are delayed until shutdown to bulk-update the stats during imports.
		add_action( 'shutdown', array( $this, 'write_stats_to_database' ) );

		$wpdb->dotorg_translation_warnings = $gp_table_prefix . 'dotorg_translation_warnings';
	}

	public function translation_updated( $translation ) {
		if ( ! $translation->warnings ) {
			return;
		}

		// We only want to trigger for strings which are live, or are for consideration.
		if ( ! in_array( $translation->status, array( 'current', 'waiting' ) ) ) {
			return;
		}

		$original        = GP::$original->get( $translation->original_id );
		$project         = GP::$project->get( $original->project_id );
		$translation_set = GP::$translation_set->get( $translation->translation_set_id );

		foreach( $translation->warnings as $index => $warnings ) {
			foreach ( $warnings as $warning_key => $warning ) {
				$key = "{$translation->user_id},{$translation_set->locale},{$translation_set->slug},{$project->path},{$translation->id},{$warning_key}";

				$this->warning_stats[ $key ] = $warning;
			}
		}
	}


	public function write_stats_to_database() {
		global $wpdb;

		$now = current_time( 'mysql', 1 );

		$values = array();
		foreach ( $this->warning_stats as $key => $message ) {
			list( $user_id, $locale, $locale_slug, $project_path, $translation_id, $warning ) = explode( ',', $key );

			$values[] = $wpdb->prepare( '(%d, %s, %s, %s, %d, %s, %s, %s)',
				$user_id,
				$locale,
				$locale_slug,
				$project_path,
				$translation_id,
				$warning,
				$now,
				$message
			);

			// If we're processing a large batch, add them as we go to avoid query lengths & memory limits.
			if ( count( $values ) > 50 ) {
				$wpdb->query(
					"INSERT INTO {$wpdb->dotorg_translation_warnings} (`user_id`, `locale`, `locale_slug`, `project_path`, `translation_id`, `warning`, `timestamp`, `message`)
					VALUES " . implode( ', ', $values )
				);
				$values = array();
			}
		}

		if ( $values ) {
			$wpdb->query(
				"INSERT INTO {$wpdb->dotorg_translation_warnings} (`user_id`, `locale`, `locale_slug`, `project_path`, `translation_id`, `warning`, `timestamp`, `message`)
				VALUES " . implode( ', ', $values )
			);
		}
	}
}

/*
Table:

CREATE TABLE `translate_dotorg_translation_warnings` (
  `id` bigint(10) NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `locale` varchar(255) NOT NULL DEFAULT '',
  `locale_slug` varchar(255) NOT NULL DEFAULT '',
  `project_path` varchar(255) NOT NULL DEFAULT '',
  `translation_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `warning` varchar(20) NOT NULL DEFAULT '',
  `timestamp` datetime NOT NULL default '0000-00-00 00:00:00',
  `message` longtext
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
*/
