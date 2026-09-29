<?php
/**
 * Stores the Translation Count Status into a DB table for querying purposes.
 *
 * The data is pulled from GP_Translation_Set stat functions and updated in the DB whenever
 * a new translation is submitted, or new originals are imported.
 * The database update is delayed until shutdown to bulk-update the database during imports.
 *
 * NOTE: The counts include all sub-projects in the count, as that's more useful for querying
 * (top-level projects excluded).
 *
 * @author dd32
 */
class WPorg_GP_Project_Stats {

	/**
	 * Projects queue to update.
	 *
	 * @var array
	 */
	public $projects_to_update = array();

	/**
	 * Constructor. Registers hooks and tables.
	 */
	public function __construct() {
		global $wpdb, $gp_table_prefix;

		add_action( 'gp_translation_created', array( $this, 'translation_edited' ) );
		add_action( 'gp_translation_saved', array( $this, 'translation_edited' ) );
		add_action( 'gp_translation_deleted', array( $this, 'translation_edited' ) );
		add_action( 'gp_originals_imported', array( $this, 'originals_imported' ), 10, 5 );

		// DB writes are delayed until shutdown to bulk-update the stats during imports.
		add_action( 'shutdown', array( $this, 'shutdown' ) );

		// Cron task to cache the wp-themes/wp-plugins string counts.
		add_action( 'init', array( $this, 'register_crons' ) );

		add_action( 'wporg_gp_stats_cache_waiting_strings', array( $this, 'cache_wp_themes_wp_plugins_strings' ) );

		$wpdb->project_translation_status = $gp_table_prefix . 'project_translation_status';
	}

	public function register_crons(): void {
		if ( ! wp_next_scheduled( 'wporg_gp_stats_cache_waiting_strings' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'wporg_gp_stats_cache_waiting_strings' );
		}
	}

	/**
	 * Enqueues project and locale sets when a translation is created, modified or deleted.
	 *
	 * @param object $translation
	 */
	public function translation_edited( $translation ) {
		if ( empty( $translation->translation_set_id ) ) {
			return;
		}

		$set = GP::$translation_set->get( $translation->translation_set_id );
		if ( ! $set ) {
			return;
		}

		if ( isset( $this->projects_to_update[ $set->project_id ] ) && true === $this->projects_to_update[ $set->project_id ] ) {
			return;
		}

		$this->projects_to_update[ $set->project_id ][ $set->locale . '/' . $set->slug ] = true;
	}

	/**
	 * Enqueues project when original strings are imported.
	 *
	 * @param int $project_id
	 * @param int $originals_added
	 * @param int $originals_existing
	 * @param int $originals_obsoleted
	 * @param int $originals_fuzzied
	 */
	public function originals_imported( $project_id, $originals_added, $originals_existing, $originals_obsoleted, $originals_fuzzied ) {
		if ( $originals_added || $originals_existing || $originals_obsoleted || $originals_fuzzied ) {
			$this->projects_to_update[ $project_id ] = true;
		}
	}

	/**
	 * Counts up all the strings recursively for a project and all its sub-projects.
	 *
	 * @param int    $project_id
	 * @param string $locale
	 * @param string $locale_slug
	 * @param array  $counts
	 * @return array
	 */
	public function get_project_translation_counts( $project_id, $locale, $locale_slug, &$counts = array(), &$visited = array() ) {
		if ( isset( $visited[ $project_id ] ) ) {
			return $counts;
		}
		$visited[ $project_id ] = true;
		
		if ( empty( $counts ) ) {
			$counts = array(
				'all'          => 0,
				'current'      => 0,
				'waiting'      => 0,
				'fuzzy'        => 0,
				'warnings'     => 0,
				'untranslated' => 0,
			);
		}

		// Not all projects have translation sets directly attached.
		$set = GP::$translation_set->by_project_id_slug_and_locale( $project_id, $locale_slug, $locale );
		if ( $set ) {
			// Force a refresh of the translation set counts.
			wp_cache_delete( $set->id, 'translation_set_status_breakdown' );
			$counts['all']          += (int) $set->all_count();
			$counts['current']      += (int) $set->current_count();
			$counts['waiting']      += (int) $set->waiting_count();
			$counts['fuzzy']        += (int) $set->fuzzy_count();
			$counts['warnings']     += (int) $set->warnings_count();
			$counts['untranslated'] += (int) $set->untranslated_count();
		}

		// Fetch the strings from the sub projects too.
		$project = GP::$project->get( $project_id );
		if ( is_object( $project ) && method_exists( $project, 'sub_projects' ) ) {
			$sub_projects = $project->sub_projects();
			if ( is_array( $sub_projects ) ) {
				foreach ( $sub_projects as $sub_project ) {
					if ( empty( $sub_project->active ) ) {
						continue;
					}
					$this->get_project_translation_counts(
						$sub_project->id,
						$locale,
						$locale_slug,
						$counts,
						$visited
					);
				}
			}
		}

		return $counts;
	}

	/**
	 * Cron task to cache the string counts for the wp-themes and wp-plugins parent categories.
	 */
	public function cache_wp_themes_wp_plugins_strings() {
		global $wpdb;

		$cached_projects = array_filter(
			array(
				GP::$project->by_path( 'wp-plugins' ),
				GP::$project->by_path( 'wp-themes' ),
			)
		);

		if ( empty( $cached_projects ) ) {
			return;
		}

		$gp_projects_table = GP::$project->table ?? ( $wpdb->prefix . 'gp_projects' );

		// Store the counts for these parent projects as the sum of their children.
		$sql = "INSERT INTO {$wpdb->project_translation_status} (
					`project_id`, `locale`, `locale_slug`, `all`, `current`, `waiting`,
					`fuzzy`, `warnings`, `untranslated`, `has_pending`, `date_added`, `date_modified`
				)
				SELECT
					p.parent_project_id AS project_id,
					stats.locale,
					stats.locale_slug,
					SUM( stats.`all` ) AS `all`,
					SUM( stats.current ) AS `current`,
					SUM( stats.waiting ) AS `waiting`,
					SUM( stats.fuzzy ) AS `fuzzy`,
					SUM( stats.warnings ) AS `warnings`,
					SUM( stats.untranslated ) AS `untranslated`,
					IF( SUM( stats.waiting ) > 0 OR SUM( stats.fuzzy ) > 0, 1, 0 ) AS `has_pending`,
					UTC_TIMESTAMP() AS `date_added`,
					UTC_TIMESTAMP() AS `date_modified`
				FROM {$wpdb->project_translation_status} stats
				INNER JOIN {$gp_projects_table} p ON stats.project_id = p.id
				WHERE
					p.parent_project_id = %d
					AND p.active = 1
				GROUP BY
					stats.locale,
					stats.locale_slug
				ON DUPLICATE KEY UPDATE
					`all`           = VALUES(`all`),
					`current`       = VALUES(`current`),
					`waiting`       = VALUES(`waiting`),
					`fuzzy`         = VALUES(`fuzzy`),
					`warnings`      = VALUES(`warnings`),
					`untranslated`  = VALUES(`untranslated`),
					`has_pending`   = VALUES(`has_pending`),
					`date_modified` = VALUES(`date_modified`)";

		foreach ( $cached_projects as $project ) {
			if ( ! empty( $project->id ) ) {
				$wpdb->query( $wpdb->prepare( $sql, $project->id ) );
			}
		}
	}

	/**
	 * Writes queued stats to the database on shutdown.
	 */
	public function shutdown() {
		global $wpdb;

		if ( empty( $this->projects_to_update ) ) {
			return;
		}

		// If a project is `true` then fetch all translation sets for it.
		foreach ( $this->projects_to_update as $project_id => $set_data ) {
			if ( true === $set_data ) {
				$this->projects_to_update[ $project_id ] = array();
				$sets = GP::$translation_set->by_project_id( $project_id );
				if ( is_array( $sets ) ) {
					foreach ( $sets as $set ) {
						$this->projects_to_update[ $project_id ][ $set->locale . '/' . $set->slug ] = true;
					}
				}
			}
		}

		// Update parent projects (excluding root grouping parents).
		$projects = $this->projects_to_update;
		foreach ( $projects as $project_id => $data ) {
			if ( ! is_array( $data ) ) {
				continue;
			}

			$visited_parents = array();			

			$project = GP::$project->get( $project_id );
			while ( $project && is_object( $project ) && ! empty( $project->parent_project_id ) ) {
				if ( isset( $visited_parents[ $project->id ] ) ) {
					break;
				}
				$visited_parents[ $project->id ] = true;
				$parent = GP::$project->get( $project->parent_project_id );
				if ( $parent && is_object( $parent ) && ! empty( $parent->parent_project_id ) ) {
					if ( ! isset( $projects[ $parent->id ] ) || ! is_array( $projects[ $parent->id ] ) ) {
						$projects[ $parent->id ] = array();
					}

					$projects[ $parent->id ] = $data + ( $projects[ $parent->id ] ?? array() );
					$project                 = $parent;
				} else {
					break;
				}
			}
		}

		$this->projects_to_update = $projects;
		unset( $projects );

		$now    = current_time( 'mysql', 1 );
		$values = array();

		foreach ( $this->projects_to_update as $project_id => $locale_sets ) {
			if ( ! is_array( $locale_sets ) ) {
				continue;
			}

			foreach ( array_keys( $locale_sets ) as $set_key ) {
				$parts = explode( '/', $set_key );
				if ( count( $parts ) !== 2 ) {
					continue;
				}

				list( $locale, $locale_slug ) = $parts;
				$counts = $this->get_project_translation_counts( $project_id, $locale, $locale_slug );

				$values[] = $wpdb->prepare(
					'(%d, %s, %s, %d, %d, %d, %d, %d, %d, %d, %s, %s)',
					$project_id,
					$locale,
					$locale_slug,
					$counts['all'],
					$counts['current'],
					$counts['waiting'],
					$counts['fuzzy'],
					$counts['warnings'],
					$counts['untranslated'],
					( $counts['waiting'] > 0 || $counts['fuzzy'] > 0 ) ? 1 : 0,
					$now,
					$now
				);

				if ( count( $values ) >= 50 ) {
					$this->insert_batch( $values );
					$values = array();
				}
			}
		}

		$this->projects_to_update = array();

		if ( ! empty( $values ) ) {
			$this->insert_batch( $values );
		}
	}

	/**
	 * Helper function to run batch inserts with ON DUPLICATE KEY UPDATE.
	 *
	 * @param array $values Prepared SQL value tuples.
	 */
	private function insert_batch( array $values ) {
		global $wpdb;

		if ( empty( $values ) ) {
			return;
		}

		$sql = "INSERT INTO {$wpdb->project_translation_status} (
					`project_id`, `locale`, `locale_slug`,
					`all`, `current`, `waiting`, `fuzzy`, `warnings`, `untranslated`, `has_pending`,
					`date_added`, `date_modified`
				)
				VALUES " . implode( ', ', $values ) . "
				ON DUPLICATE KEY UPDATE
					`all`           = VALUES(`all`),
					`current`       = VALUES(`current`),
					`waiting`       = VALUES(`waiting`),
					`fuzzy`         = VALUES(`fuzzy`),
					`warnings`      = VALUES(`warnings`),
					`untranslated`  = VALUES(`untranslated`),
					`has_pending`   = VALUES(`has_pending`),
					`date_modified` = VALUES(`date_modified`)";

		$wpdb->query( $sql );
	}
}
