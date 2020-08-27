<?php

/**
 * This plugin records the submitted/accepted counts of translations offered by users.
 *
 * Only translations with an attached user_id are counted.
 *
 * @author dd32
 */
class WPorg_GP_User_Stats {

	private $user_stats = [];

	private $user_project_stats = [];

	public function __construct() {
		global $wpdb, $gp_table_prefix;

		add_action( 'gp_translation_created', array( $this, 'translation_updated' ) );
		add_action( 'gp_translation_saved', array( $this, 'translation_updated' ) );

		// DB Writes are delayed until shutdown to bulk-update the stats during imports.
		add_action( 'shutdown', array( $this, 'write_stats_to_database' ) );

		$wpdb->user_translations_count = $gp_table_prefix . 'user_translations_count';
		$wpdb->user_projects           = $gp_table_prefix . 'user_projects';
	}

	public function translation_updated( $translation ) {
		if ( ! $translation->user_id ) {
			return;
		}

		$translation_set = GP::$translation_set->get( $translation->translation_set_id );

		if ( 'waiting' === $translation->status ) {
			// New translation suggested
			$this->bump_user_stat( $translation->user_id, $translation_set->locale, $translation_set->slug, 1, 0 );

		} elseif ( 'current' === $translation->status && 'gp_translation_created' === current_filter() ) {
			// New translation suggested & approved
			$this->bump_user_stat( $translation->user_id, $translation_set->locale, $translation_set->slug, 1, 1 );

		} elseif ( 'current' === $translation->status ) {
			// Translation approved
			$this->bump_user_stat( $translation->user_id, $translation_set->locale, $translation_set->slug, 0, 1 );

		}

		// Record the last time the user submitted a translation for a project/locale.
		if ( 'gp_translation_created' == current_filter() ) {
			$project = GP::$project->get( $translation_set->project_id );

			// Find the "root" project ID.
			// For projects with sub-projects, we only want to store the "parent" project.
			// ie. wp-plugins/plugin-name, wp/dev, or apps/android
			$project_path_we_want = implode( '/', array_slice( explode( '/', $project->path ), 0, 2 ) );
			if ( $project_path_we_want != $project->path ) {
				$project = GP::$project->by_path( $project_path_we_want );
			}

			$key = "{$translation->user_id},{$project->id},{$translation_set->locale},{$translation_set->slug}";
			if ( ! isset( $this->user_project_stats[ $key ] ) ) {
				$this->user_project_stats[ $key ] = 0;
			}
			$this->user_project_stats[ $key ]++;
		}
	}

	private function bump_user_stat( $user_id, $locale, $locale_slug, $suggested = 0, $accepted = 0 ) {
		$key = "$user_id,$locale,$locale_slug";

		if ( isset( $this->user_stats[ $key ] ) ) {
			$this->user_stats[ $key ]->suggested += $suggested;
			$this->user_stats[ $key ]->accepted  += $accepted;
		} else {
			$this->user_stats[ $key ] = (object) array(
				'suggested' => $suggested,
				'accepted'  => $accepted,
			);
		}
	}

	public function write_stats_to_database() {
		global $wpdb;

		$now = current_time( 'mysql', 1 );

		$values = [];
		foreach ( $this->user_stats as $key => $stats ) {
			list( $user_id, $locale, $locale_slug ) = explode( ',', $key );

			$values[] = $wpdb->prepare( '(%d, %s, %s, %d, %d, %s, %s)',
				$user_id,
				$locale,
				$locale_slug,
				$stats->suggested,
				$stats->accepted,
				$now,
				$now
			);

			// If we're processing a large batch, add them as we go to avoid query lengths & memory limits.
			if ( count( $values ) > 50 ) {
				$wpdb->query(
					"INSERT INTO {$wpdb->user_translations_count} (`user_id`, `locale`, `locale_slug`, `suggested`, `accepted`, `date_added`, `date_modified`)
					VALUES " . implode( ', ', $values ) . "
					ON DUPLICATE KEY UPDATE `suggested`=`suggested` + VALUES(`suggested`), `accepted`=`accepted` + VALUES(`accepted`), `date_modified`=VALUES(`date_modified`)"
				);
				$values = [];
			}
		}

		if ( $values ) {
			$wpdb->query(
				"INSERT INTO {$wpdb->user_translations_count} (`user_id`, `locale`, `locale_slug`, `suggested`, `accepted`, `date_added`, `date_modified`)
				VALUES " . implode( ', ', $values ) . "
				ON DUPLICATE KEY UPDATE `suggested`=`suggested` + VALUES(`suggested`), `accepted`=`accepted` + VALUES(`accepted`), `date_modified`=VALUES(`date_modified`)"
			);
			$values = [];
		}

		// Process the user project stats too.
		$values = [];
		foreach ( $this->user_project_stats as $key => $string_count ) {
			list( $user_id, $project_id, $locale, $locale_slug ) = explode( ',', $key );

			// Step 1 - Does this user already have the project listed? Just Bump the date.
			if ( $id = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM {$wpdb->user_projects}
				WHERE user_id = %d AND project_id = %d AND locale = %s AND locale_slug = %s",
				$user_id, $project_id, $locale, $locale_slug
			) ) ) {
				$wpdb->update(
					$wpdb->user_projects,
					[ 'last_modified' => $now ],
					[ 'id' => $id ]
				);
				continue;
			}

			// Step 2 - More than 5 strings? Import Maybe? Just insert.
			if ( $string_count >= 5 ) {
				$wpdb->insert(
					$wpdb->user_projects,
					[
						'user_id'       => $user_id,
						'project_id'    => $project_id,
						'locale'        => $locale,
						'locale_slug'   => $locale_slug,
						'last_modified' => $now
					]
				);
				continue;
			}

			// Step 3 - If not, find all the sub-project IDs, then all the translation sets, count strings by user.
			$this_project        = GP::$project->get( $project_id );
			$translation_set_ids = [];

			if ( ! $this_project->active ) {
				continue;
			}

			if ( $set = GP::$translation_set->by_project_id_slug_and_locale( $project_id, $locale_slug, $locale ) ) {
				$translation_set_ids[] = (int) $set->id;
			}

			// Fetch the strings from the sub projects too
			foreach ( $this_project->sub_projects() as $project ) {
				if ( ! $project->active ) {
					continue;
				}
				if ( $set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $locale_slug, $locale ) ) {
					$translation_set_ids[] = (int) $set->id;
				}
			}

			$user_translations = GP::$translation->find_many_no_map( [
				'user_id'            => $user_id,
				'translation_set_id' => $translation_set_ids
			] );

			if ( $user_translations && count( $user_translations ) >= 5 ) {
				$wpdb->insert(
					$wpdb->user_projects,
					[
						'user_id'       => $user_id,
						'project_id'    => $project_id,
						'locale'        => $locale,
						'locale_slug'   => $locale_slug,
						'last_modified' => $now
					]
				);
				continue;
			}
		}
	}
}

/*
Tables:

Note: WordPress uses BIGINT(20) for user_id; GlotPress uses int(10)

CREATE TABLE `gp_user_translations_count` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `locale` varchar(255) NOT NULL DEFAULT '',
  `locale_slug` varchar(255) NOT NULL DEFAULT '',
  `suggested` int(10) unsigned NOT NULL DEFAULT '0',
  `accepted` int(10) unsigned NOT NULL DEFAULT '0',
  `date_added` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `date_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`locale`,`locale_slug`),
  KEY `locale` (`locale`,`locale_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `gp_user_projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `project_id` int(11) unsigned NOT NULL,
  `locale` varchar(255) NOT NULL,
  `locale_slug` varchar(255) NOT NULL DEFAULT 'default',
  `last_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_project_locale` (`user_id`,`project_id`,`locale`,`locale_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
*/
