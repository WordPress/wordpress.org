<?php
/**
 * This WP-CLI command updates project statistics for a project and all its subprojects.
 *
 * The command updates the translate_project_translation_status table for the specified
 * project and all its subprojects recursively.
 *
 * To execute this command, use:
 *
 * wp wporg-translate update-project-stats --project-path=wp-plugins/woocommerce --url=translate.wordpress.org
 *
 * @package WordPressdotorg\GlotPress\Customizations\CLI
 */

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use GP;
use WP_CLI;
use WP_CLI_Command;

/**
 * Class Update_Project_Stats
 */
class Update_Project_Stats extends WP_CLI_Command {
	/**
	 * Update project statistics for a project and all its subprojects.
	 *
	 * This command updates the translate_project_translation_status table for the specified
	 * project and all its subprojects recursively.
	 *
	 * ## OPTIONS
	 *
	 * --project-path=<path>
	 * : The path of the project to update statistics for (e.g., 'wp-plugins/woocommerce', 'wp/dev').
	 * This is a required parameter.
	 *
	 * [--verbose]
	 * : Output detailed information about the update process.
	 * ---
	 * default: false
	 * ---
	 *
	 * [--dry-run]
	 * : Simulate the update process without making any changes.
	 * ---
	 * default: false
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Update statistics for a specific project and all its subprojects
	 *     wp wporg-translate update-project-stats --project-path=wp-plugins/woocommerce --url=translate.wordpress.org
	 *
	 *     # Preview what would be updated (dry run) with verbose output
	 *     wp wporg-translate update-project-stats --project-path=wp-plugins/woocommerce --dry-run --verbose --url=translate.wordpress.org
	 *
	 *     # Update statistics for WordPress core
	 *     wp wporg-translate update-project-stats --project-path=wp/dev --url=translate.wordpress.org
	 *
	 *     # Update statistics with verbose output
	 *     wp wporg-translate update-project-stats --project-path=wp-plugins/akismet --verbose --url=translate.wordpress.org
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       The arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		// Validate required parameters
		if ( empty( $assoc_args['project-path'] ) ) {
			WP_CLI::error( 'The --project-path parameter is required.' );
			return;
		}

		$project_path = $assoc_args['project-path'];
		$verbose      = isset( $assoc_args['verbose'] ) ? (bool) $assoc_args['verbose'] : false;
		$dry_run      = isset( $assoc_args['dry-run'] ) ? (bool) $assoc_args['dry-run'] : false;

		if ( $verbose ) {
			WP_CLI::log( "Verbose mode: enabled" );
			WP_CLI::log( "Dry run mode: " . ( $dry_run ? 'enabled' : 'disabled' ) );
			WP_CLI::log( "Project path: " . $project_path );
		}

		// Get the project
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			WP_CLI::error( sprintf( 'Project not found: %s', $project_path ) );
			return;
		}

		if ( $verbose ) {
			WP_CLI::log( sprintf( 'Found project: %s (ID: %d)', $project->name, $project->id ) );
		}

		// Get all project IDs including the parent and all subprojects
		$project_ids = $this->get_project_and_subproject_ids( $project );

		WP_CLI::log( sprintf( 'Updating statistics for %d project(s) (including subprojects)', count( $project_ids ) ) );

		$total_updated = 0;

		// Update statistics for each project
		foreach ( $project_ids as $project_id ) {
			$current_project = GP::$project->get( $project_id );
			
			if ( ! $current_project ) {
				if ( $verbose ) {
					WP_CLI::warning( sprintf( 'Could not load project with ID: %d', $project_id ) );
				}
				continue;
			}

			if ( $verbose ) {
				WP_CLI::log( sprintf( 'Processing project: %s (ID: %d)', $current_project->name, $current_project->id ) );
			}

			$result = $this->update_project_statistics( $current_project, $verbose, $dry_run );
			if ( $result ) {
				$total_updated++;
			}
		}

		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Dry run completed. Would have updated statistics for %d project(s).', $total_updated ) );
		} else {
			WP_CLI::success( sprintf( 'Successfully updated statistics for %d project(s).', $total_updated ) );
		}
	}

	/**
	 * Get project ID and all subproject IDs recursively.
	 *
	 * @param GP_Project $project The parent project.
	 * @return array Array of project IDs.
	 */
	private function get_project_and_subproject_ids( $project ) {
		$project_ids = array( $project->id );

		// Find all subprojects
		$subprojects = GP::$project->find_many(
			array(
				'parent_project_id' => $project->id,
			)
		);

		// Recursively get IDs from subprojects
		foreach ( $subprojects as $subproject ) {
			$subproject_ids = $this->get_project_and_subproject_ids( $subproject );
			$project_ids = array_merge( $project_ids, $subproject_ids );
		}

		return $project_ids;
	}

	/**
	 * Update statistics for a single project.
	 *
	 * This method updates the translate_project_translation_status table
	 * for the given project.
	 *
	 * @param GP_Project $project The project to update statistics for.
	 * @param bool       $verbose Whether to output verbose logging.
	 * @param bool       $dry_run Whether to simulate without making changes.
	 * @return bool True on success, false on failure.
	 */
	private function update_project_statistics( $project, $verbose, $dry_run ) {
		global $wpdb;

		if ( $verbose ) {
			WP_CLI::log( sprintf( '  %sUpdating translate_project_translation_status for project ID: %d', $dry_run ? '[DRY RUN] ' : '', $project->id ) );
		}

		// Get all translation sets for this project
		$translation_sets = GP::$translation_set->by_project_id( $project->id );

		if ( empty( $translation_sets ) ) {
			if ( $verbose ) {
				WP_CLI::log( '  No translation sets found for this project.' );
			}
			return true;
		}

		$sets_updated = 0;

		foreach ( $translation_sets as $set ) {
			// Calculate statistics for this translation set
			$stats = $this->calculate_set_statistics( $set );

			if ( $stats ) {
				// Update or insert statistics in the table
				$result = $this->update_statistics( $project->id, $set, $stats, $verbose, $dry_run );
				if ( $result ) {
					$sets_updated++;
				}
			}
		}

		if ( $verbose ) {
			WP_CLI::log( sprintf( '  %s%d translation set(s).', $dry_run ? 'Would update statistics for ' : 'Updated statistics for ', $sets_updated ) );
		}

		return true;
	}

	/**
	 * Calculate statistics for a single translation set.
	 *
	 * @param GP_Translation_Set $set The translation set.
	 * @return array Statistics array.
	 */
	private function calculate_set_statistics( $set ) {
        // Clear cached status breakdown to ensure fresh counts.
        wp_cache_delete( $set->id, 'translation_set_status_breakdown' );
		// Get translation counts from the translation set object.
		return array(
			'all'          => (int) $set->all_count(),
			'current'      => (int) $set->current_count(),
			'waiting'      => (int) $set->waiting_count(),
			'fuzzy'        => (int) $set->fuzzy_count(),
			'warnings'     => (int) $set->warnings_count(),
			'untranslated' => (int) $set->untranslated_count(),
		);
	}	/**
	 * Update or insert statistics into the translate_project_translation_status table.
	 *
	 * @param int                 $project_id The project ID.
	 * @param GP_Translation_Set  $set        The translation set.
	 * @param array               $stats      The statistics to insert/update.
	 * @param bool                $verbose    Whether to output verbose logging.
	 * @param bool                $dry_run    Whether to simulate without making changes.
	 * @return bool True on success, false on failure.
	 */
	private function update_statistics( $project_id, $set, $stats, $verbose, $dry_run ) {
		global $wpdb;
		
        if ( ! isset( $wpdb->project_translation_status ) ) {
			return;
		}
		
        if ( $verbose ) {
			WP_CLI::log( sprintf(
				'    %sLocale: %s, Slug: %s - All: %d, Current: %d, Waiting: %d, Fuzzy: %d, Untranslated: %d, Warnings: %d',
				$dry_run ? '[DRY RUN] ' : '',
				$set->locale,
				$set->slug,
				$stats['current'] + $stats['untranslated'] + $stats['waiting'] + $stats['fuzzy'],
				$stats['current'],
				$stats['waiting'],
				$stats['fuzzy'],
				$stats['untranslated'],
				isset( $stats['warnings'] ) ? $stats['warnings'] : 0
			) );
		}

		// If dry run, skip database operations
		if ( $dry_run ) {
			return true;
		}

		// Calculate the 'all' field (total number of strings)
		$all = $stats['current'] + $stats['untranslated'] + $stats['waiting'] + $stats['fuzzy'];

		// Prepare data for insert/update
		$data = array(
			'project_id'    => $project_id,
			'locale'        => $set->locale,
			'locale_slug'   => $set->slug,
			'all'           => $all,
			'current'       => $stats['current'],
			'waiting'       => $stats['waiting'],
			'fuzzy'         => $stats['fuzzy'],
			'warnings'      => isset( $stats['warnings'] ) ? $stats['warnings'] : 0,
			'untranslated'  => $stats['untranslated'],
			'has_pending'   => ( $stats['waiting'] > 0 || $stats['fuzzy'] > 0 ) ? 1 : 0,
			'date_modified' => current_time( 'mysql' ),
		);

		// Check if a record already exists
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->project_translation_status} 
				WHERE project_id = %d AND locale = %s AND locale_slug = %s",
				$project_id,
				$set->locale,
				$set->slug
			)
		);

        $result = false;
		if ( $existing ) {
			// Update existing record
			$result = $wpdb->update(
				"{$wpdb->project_translation_status}",
				$data,
				array(
					'id' => $existing->id,
				),
				array(
					'%d', // project_id
					'%s', // locale
					'%s', // locale_slug
					'%d', // all
					'%d', // current
					'%d', // waiting
					'%d', // fuzzy
					'%d', // warnings
					'%d', // untranslated
					'%d', // has_pending
					'%s', // date_modified
				),
				array( '%d' )
			);
		}
        // else {
		// 	// Insert new record
		// 	$data['date_added'] = current_time( 'mysql' );

		// 	$result = $wpdb->insert(
		// 		"{$wpdb->project_translation_status}",
		// 		$data,
		// 		array(
		// 			'%d', // project_id
		// 			'%s', // locale
		// 			'%s', // locale_slug
		// 			'%d', // all
		// 			'%d', // current
		// 			'%d', // waiting
		// 			'%d', // fuzzy
		// 			'%d', // warnings
		// 			'%d', // untranslated
		// 			'%d', // has_pending
		// 			'%s', // date_added
		// 			'%s', // date_modified
		// 		)
		// 	);
		// }

		if ( false === $result ) {
			if ( $verbose ) {
				WP_CLI::warning( sprintf( 'Failed to update statistics for locale: %s, slug: %s', $set->locale, $set->slug ) );
			}
			return false;
		}

		return true;
	}
}
