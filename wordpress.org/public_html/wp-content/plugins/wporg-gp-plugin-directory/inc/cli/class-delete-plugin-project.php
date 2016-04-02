<?php

namespace WordPressdotorg\GlotPress\Plugin_Directory\CLI;

use GP;
use WP_CLI;
use WP_CLI_Command;

class Delete_Plugin_Project extends WP_CLI_Command {

	/**
	 * Holds the path of the master project.
	 *
	 * @var string
	 */
	private $master_project_path = 'wp-plugins';

	/**
	 * Delete a plugin project and its translations.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Slug of a plugin
	 *
	 * [--force]
	 * : If set, the command will delete the plugin, without prompting
	 * for confirmation.
	 */
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;

		$project_path = sprintf( '%s/%s', $this->master_project_path, $args[0] );

		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			WP_CLI::error( sprintf( "There is no plugin project for '%s'.", $args[0] ) );
		}

		$sub_projects = $project->sub_projects();

		if ( ! isset( $assoc_args['force'] ) ) {
			WP_CLI::confirm( sprintf( "Do you want to delete %s with %d sub-projects?", $project->name, ( $sub_projects ? count( $sub_projects ) : 0 ) ) );
		}

		// Handle sub-projects.
		if ( $sub_projects ) {
			foreach ( $sub_projects as $sub_project ) {
				// Get translation sets...
				$sets = GP::$translation_set->by_project_id( $sub_project->id );

				if ( $sets ) {
					foreach ( $sets as $set ) {
						// ... and delete its translations.
						$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->gp_translations} WHERE translation_set_id = %d", $set->id ) );
						WP_CLI::line( sprintf( "%d translations for %s/%s deleted.", $result, $set->locale, $set->slug ) );

						$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->gp_translation_sets} WHERE id = %d", $set->id ) );
						WP_CLI::line( sprintf( "%d translation sets for %s/%s deleted.", $result, $set->locale, $set->slug ) );

						gp_clean_translation_set_cache( $set->id );
					}
				}

				// Delete originals.
				$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->gp_originals} WHERE project_id = %d", $sub_project->id ) );
				WP_CLI::line( sprintf( "%d originals for %s deleted.", $result, $sub_project->path ) );

				// Delete sub project.
				$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->gp_projects} WHERE id = %d", $sub_project->id ) );
				WP_CLI::line( sprintf( "%d sub project %s deleted.", $result, $sub_project->path ) );
			}
		} else {
			// Get translation sets...
			$sets = GP::$translation_set->by_project_id( $project->id );

			if ( $sets ) {
				foreach ( $sets as $set ) {
					// ... and delete its translations.
					$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->gp_translations} WHERE translation_set_id = %d", $set->id ) );
					WP_CLI::line( sprintf( "%d translations for %s/%s deleted.", $result, $set->locale, $set->slug ) );

					$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->gp_translation_sets} WHERE id = %d", $set->id ) );
					WP_CLI::line( sprintf( "%d translation sets for %s/%s deleted.", $result, $set->locale, $set->slug ) );

					gp_clean_translation_set_cache( $set->id );
				}
			}

			// Delete originals.
			$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->gp_originals} WHERE project_id = %d", $project->id ) );
			WP_CLI::line( sprintf( "%d originals for %s deleted.", $result, $project->path ) );
		}

		// Delete project.
		$result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->gp_projects} WHERE id = %d", $project->id ) );
		WP_CLI::line( sprintf( "%d project %s deleted.", $result, $project->path ) );
	}
}
