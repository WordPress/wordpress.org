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

		// DB writes are delayed until shutdown to bulk-update the stats during imports.
		add_action( 'shutdown', array( $this, 'write_stats_to_database' ) );

		$wpdb->dotorg_translation_warnings = $gp_table_prefix . 'dotorg_translation_warnings';
	}

	public function translation_updated( $translation ) {
		if ( empty( $translation->warnings ) ) {
			return;
		}

		// We only want to trigger for strings which are live, or are for consideration.
		if ( ! in_array( $translation->status, array( 'current', 'waiting' ), true ) ) {
			return;
		}

		$original        = GP::$original->get( $translation->original_id );
		$translation_set = GP::$translation_set->get( $translation->translation_set_id );

		if ( ! $original || ! $translation_set ) {
			return;
		}

		$project = GP::$project->get( $original->project_id );
		if ( ! $project ) {
			return;
		}

		foreach ( $translation->warnings as $plural_index => $warnings ) {
			if ( ! is_array( $warnings ) ) {
				continue;
			}

			foreach ( $warnings as $warning_key => $warning ) {
				$dedupe_key = md5( "{$translation->user_id}|{$translation_set->locale}|{$translation_set->slug}|{$project->path}|{$translation->id}|{$plural_index}|{$warning_key}" );

				$this->warning_stats[ $dedupe_key ] = array(
					'user_id'        => (int) $translation->user_id,
					'locale'         => $translation_set->locale,
					'locale_slug'    => $translation_set->slug,
					'project_path'   => $project->path,
					'translation_id' => (int) $translation->id,
					'warning'        => $warning_key,
					'message'        => is_scalar( $warning ) ? (string) $warning : wp_json_encode( $warning ),
				);
			}
		}

		if ( count( $this->warning_stats ) >= 500 ) {
			$this->write_stats_to_database();
		}
	}

	public function write_stats_to_database() {
		global $wpdb;

		if ( empty( $this->warning_stats ) ) {
			return;
		}

		$now    = current_time( 'mysql', 1 );
		$chunks = array_chunk( $this->warning_stats, 50 );

		foreach ( $chunks as $chunk ) {
			$values = array();

			foreach ( $chunk as $entry ) {
				$values[] = $wpdb->prepare(
					'(%d, %s, %s, %s, %d, %s, %s, %s)',
					$entry['user_id'],
					$entry['locale'],
					$entry['locale_slug'],
					$entry['project_path'],
					$entry['translation_id'],
					$entry['warning'],
					$now,
					$entry['message']
				);
			}

			if ( ! empty( $values ) ) {
				$wpdb->query(
					"INSERT INTO {$wpdb->dotorg_translation_warnings}
					(`user_id`, `locale`, `locale_slug`, `project_path`, `translation_id`, `warning`, `timestamp`, `message`)
					VALUES " . implode( ', ', $values )
				);
			}
		}

		$this->warning_stats = array();
	}
}

/*
Table:

CREATE TABLE `translate_dotorg_translation_warnings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `locale` varchar(64) NOT NULL DEFAULT '',
  `locale_slug` varchar(255) NOT NULL DEFAULT '',
  `project_path` varchar(255) NOT NULL DEFAULT '',
  `translation_id` bigint(20) unsigned NOT NULL DEFAULT '0',
  `warning` varchar(64) NOT NULL DEFAULT '',
  `timestamp` datetime NOT NULL default '0000-00-00 00:00:00',
  `message` longtext,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `locale` (`locale`),
  KEY `warning` (`warning`),
  KEY `project_path` (`project_path`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/
