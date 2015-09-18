<?php

/**
 * This plugin stores the Translation Count Status into a DB table for querying purposes.
 *
 * The data is pulled from GP_Translation_Set stat functions and updated in the DB whenever
 * a new translation is submitted, or new originals are imported.
 * The datbase update is delayed until shutdown to bulk-update the database during imports.
 *
 * NOTE: The counts includes all sub-projects in the count, as that's more useful for querying (top-level projects excluded)
 * for example, wp-plugins won't exist, but wp-plugins/akismet will include wp-plugins/akismet/stable wp-plugins/akismet/stable-readme
 *
 * @author dd32
 */
class GP_WPorg_Project_Stats extends GP_Plugin {
	var $id = 'wporg-project-stats';

	public $projects_to_update = array();

	function __construct() {
		global $gpdb;
		parent::__construct();
		$this->add_action( 'translation_created' );
		$this->add_action( 'translation_saved' );
		$this->add_action( 'originals_imported' );

		// DB Writes are delayed until shutdown to bulk-update the stats during imports
		$this->add_action( 'shutdown' );

		$gpdb->project_translation_status = $gpdb->prefix . 'project_translation_status';
	}

	function translation_created( $translation ) {
		$set = GP::$translation_set->get( $translation->translation_set_id );
		$this->projects_to_update[ $set->project_id ][ $set->locale . '/' . $set->slug ] = true;
	}

	function translation_saved( $translation ) {
		$set = GP::$translation_set->get( $translation->translation_set_id );
		$this->projects_to_update[ $set->project_id ][ $set->locale . '/' . $set->slug ] = true;
	}

	function originals_imported( $project_id ) {
		$this->projects_to_update[ $project_id ] = true;
	}

	// Counts up all the
	function get_project_translation_counts( $project_id, $locale, $locale_slug, &$counts = array() ) {
		if ( ! $counts ) {
			$counts = array( 'all' => 0, 'current' => 0, 'waiting' => 0, 'fuzzy' => 0, 'warnings' => 0, 'untranslated' => 0 );
		}

		// Not all projects have translation sets
		$set = GP::$translation_set->by_project_id_slug_and_locale( $project_id, $locale_slug, $locale );
		if ( $set ) {
			// Force a refresh of the translation set counts
			wp_cache_delete( $set->id, 'translation_set_status_breakdown' );

			$counts['all'] += $set->all_count();
			$counts['current'] += $set->current_count();
			$counts['waiting'] += $set->waiting_count();
			$counts['fuzzy'] += $set->fuzzy_count();
			$counts['warnings'] += $set->warnings_count();
			$counts['untranslated'] += $set->untranslated_count();
		}

		// Fetch the strings from the sub projects too
		foreach ( GP::$project->get( $project_id )->sub_projects() as $project ) {
			if ( ! $project->active ) {
				continue;
			}
			$this->get_project_translation_counts( $project->id, $locale, $locale_slug, $counts );
		}

		return $counts;
	}

	function shutdown() {
		global $gpdb;
		$values = array();

		// If a project is `true` then we need to fetch all translation sets for it.
		foreach ( $this->projects_to_update as $project_id => $set_data ) {
			if ( true === $set_data ) {
				$this->projects_to_update[ $project_id ] = array();
				foreach ( GP::$translation_set->by_project_id( $project_id ) as $set ) {
					$this->projects_to_update[ $project_id ][ $set->locale . '/' . $set->slug ] = true;
				}
			}

		}

		// Update all parent projects as well.
		// This does NOT update a root parent (ie. ! $parent_project_id) as we use those as grouping categories.
		$projects = $this->projects_to_update;
		foreach ( $projects as $project_id => $data ) {
			// Do all parents
			$project = GP::$project->get( $project_id );
			while ( $project ) {
				$project = GP::$project->get( $project->parent_project_id );
				if ( $project->parent_project_id ) {
					$projects[ $project->id ] = $data;
				} else {
					break;
				}
			}
		}
		$this->projects_to_update += $projects;
		unset( $projects );

		foreach ( $this->projects_to_update as $project_id => $locale_sets ) {
			$locale_sets = array_keys( $locale_sets );
			$locale_sets = array_map( function( $set ) { return explode( '/', $set ); }, $locale_sets );

			foreach ( $locale_sets as $locale_set ) {
				list( $locale, $locale_slug ) = $locale_set;
				$counts = $this->get_project_translation_counts( $project_id, $locale, $locale_slug );

				$values[] = $gpdb->prepare( '(%d, %s, %s, %d, %d, %d, %d, %d, %d)',
					$project_id,
					$locale,
					$locale_slug,
					$counts['all'],
					$counts['current'],
					$counts['waiting'],
					$counts['fuzzy'],
					$counts['warning'],
					$counts['untranslated']
				);
			}

			// If we're processing a large batch, add them as we go to avoid query lengths & memory limits
			if ( count( $values ) > 50 ) {
				$gpdb->query( "INSERT INTO {$gpdb->project_translation_status} (`project_id`, `locale`, `locale_slug`, `all`, `current`, `waiting`, `fuzzy`, `warnings`, `untranslated` ) VALUES " . implode( ', ', $values ) . " ON DUPLICATE KEY UPDATE `all`=VALUES(`all`), `current`=VALUES(`current`), `waiting`=VAlUES(`waiting`), `fuzzy`=VALUES(`fuzzy`), `warnings`=VALUES(`warnings`), `untranslated`=VALUES(`untranslated`)" );
				$values = array();
			}
		}
		$this->projects_to_update = array();

		if ( $values ) {
			$gpdb->query( "INSERT INTO {$gpdb->project_translation_status} (`project_id`, `locale`, `locale_slug`, `all`, `current`, `waiting`, `fuzzy`, `warnings`, `untranslated` ) VALUES " . implode( ', ', $values ) . " ON DUPLICATE KEY UPDATE `all`=VALUES(`all`), `current`=VALUES(`current`), `waiting`=VALUES(`waiting`), `fuzzy`=VALUES(`fuzzy`), `warnings`=VALUES(`warnings`), `untranslated`=VALUES(`untranslated`)" );
		}
	}

}
GP::$plugins->wporg_project_stats = new GP_WPorg_Project_Stats;

/*
Table:

CREATE TABLE `gp_project_translation_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `locale` varchar(10) NOT NULL,
  `locale_slug` varchar(255) NOT NULL,
  `all` int(10) unsigned NOT NULL DEFAULT '0',
  `current` int(10) unsigned NOT NULL DEFAULT '0',
  `waiting` int(10) unsigned NOT NULL DEFAULT '0',
  `fuzzy` int(10) unsigned NOT NULL DEFAULT '0',
  `warnings` int(10) unsigned NOT NULL DEFAULT '0',
  `untranslated` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_locale` (`project_id`,`locale`,`locale_slug`),
  KEY `all` (`all`),
  KEY `current` (`current`),
  KEY `waiting` (`waiting`),
  KEY `fuzzy` (`fuzzy`),
  KEY `warnings` (`warnings`),
  KEY `untranslated` (`untranslated`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

*/

