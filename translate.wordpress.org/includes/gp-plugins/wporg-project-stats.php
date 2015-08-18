<?php

/**
 * This plugin stores the Translation Count Status into a DB table for querying purposes.
 *
 * The data is pulled from GP_Translation_Set stat functions and updated in the DB whenever 
 * a new translation is submitted, or new originals are imported.
 * The datbase update is delayed until shutdown to bulk-update the database during imports.
 *
 * @author dd32
 */
class GP_WPorg_Project_Stats extends GP_Plugin {
	var $id = 'wporg-project-stats';

	private $projects_to_update = array();
	private $translation_sets_to_update = array();

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
		$this->translation_sets_to_update[ $translation->translation_set_id ] = true;
	}

	function translation_saved( $translation ) {
		$this->translation_sets_to_update[ $translation->translation_set_id ] = true;
	}

	function originals_imported( $project_id ) {
		$this->projects_to_update[ $project_id ] = true;
	}

	function shutdown() {
		global $gpdb;
		$values = array();

		// Convert projects to a list of sets
		foreach ( $this->projects_to_update as $project_id => $dummy ) {
			foreach ( GP::$translation_set->by_project_id( $project_id ) as $set ) {
				$this->translation_sets_to_update[ $set->id ] = true;
			}
			unset( $this->projects_to_update[ $project_id ] );
		}

		foreach ( $this->translation_sets_to_update as $set_id => $dummy ) {
			$set = GP::$translation_set->get( $set_id );

			unset( $this->translation_sets_to_update[ $set_id ] );

			if ( ! $set ) {
				continue;
			}

			$values[] = $gpdb->prepare( '(%d, %d, %d, %d, %d, %d, %d, %d)',
				$set->project_id,
				$set->id,
				$set->all_count(),
				$set->current_count(),
				$set->waiting_count(),
				$set->fuzzy_count(),
				$set->warnings_count(),
				// Untranslated is ( all - current ), we really want ( all - current - waiting - fuzzy ) which is (untranslated - waiting - fuzzy )
				$set->untranslated_count() - $set->waiting_count() - $set->fuzzy_count()
			);

			if ( count( $values ) > 50 ) {
				// If we're processing a large batch, add them as we go to avoid query lengths & memoryl imits
				$gpdb->query( "INSERT INTO {$gpdb->project_translation_status} (`project_id`, `translation_set_id`, `all`, `current`, `waiting`, `fuzzy`, `warnings`, `untranslated` ) VALUES " . implode( ', ', $values ) . " ON DUPLICATE KEY UPDATE `all`=VALUES(`all`), `current`=VALUES(`current`), `waiting`=VAlUES(`waiting`), `fuzzy`=VALUES(`fuzzy`), `warnings`=VALUES(`warnings`), `untranslated`=VALUES(`untranslated`)" );
				$values = array();
			}
		}

		if ( $values ) {
			$gpdb->query( "INSERT INTO {$gpdb->project_translation_status} (`project_id`, `translation_set_id`, `all`, `current`, `waiting`, `fuzzy`, `warnings`, `untranslated` ) VALUES " . implode( ', ', $values ) . " ON DUPLICATE KEY UPDATE `all`=VALUES(`all`), `current`=VALUES(`current`), `waiting`=VALUES(`waiting`), `fuzzy`=VALUES(`fuzzy`), `warnings`=VALUES(`warnings`), `untranslated`=VALUES(`untranslated`)" );
		}
	}

}
GP::$plugins->wporg_project_stats = new GP_WPorg_Project_Stats;

/*
Table:

CREATE TABLE `gp_project_translation_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(10) unsigned NOT NULL,
  `translation_set_id` int(10) unsigned NOT NULL,
  `all` int(10) unsigned NOT NULL DEFAULT '0',
  `current` int(10) unsigned NOT NULL DEFAULT '0',
  `waiting` int(10) unsigned NOT NULL DEFAULT '0',
  `fuzzy` int(10) unsigned NOT NULL DEFAULT '0',
  `warnings` int(10) unsigned NOT NULL DEFAULT '0',
  `untranslated` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_translation_set` (`project_id`,`translation_set_id`),
  KEY `all` (`all`),
  KEY `current` (`current`),
  KEY `waiting` (`waiting`),
  KEY `fuzzy` (`fuzzy`),
  KEY `warnings` (`warnings`),
  KEY `untranslated` (`untranslated`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

*/
