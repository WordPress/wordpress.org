<?php

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use GP;
use WP_CLI;
use WP_CLI_Command;

class Mass_Create_Sets extends WP_CLI_Command {

	/**
	 * Adds/deletes translation sets from one project to another.
	 *
	 * ## OPTIONS
	 *
	 * <src>
	 * : Project path to copy from.
	 *
	 * <dest>
	 * : Project path to copy to.
	 */
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;

		$src  = $args[0];
		$dest = $args[1];

		$src_project = GP::$project->by_path( $src );
		if ( ! $src_project ) {
			WP_CLI::error( sprintf( "There is no project for '%s'.", $src ) );
		}

		$dest_project = GP::$project->by_path( $dest );
		if ( ! $src_project ) {
			WP_CLI::error( sprintf( "There is no project for '%s'.", $dest ) );
		}

		$changes      = $dest_project->set_difference_from( $src_project );
		$add_count    = count( $changes['added'] );
		$remove_count = count( $changes['removed'] );

		WP_CLI::confirm(
			sprintf(
				"Source: %s\nDest: %s\nTo add: %s\nTo remove: %s\nDo you want to continue?",
				$src_project->path,
				$dest_project->path,
				$add_count,
				$remove_count
			)
		);

		foreach ( $changes['added'] as $to_add ) {
			$data = [
				'project_id' => $dest_project->id,
				'name'       => $to_add->name,
				'locale'     => $to_add->locale,
				'slug'       => $to_add->slug,
			];

			if ( ! GP::$translation_set->create( $data ) ) {
				WP_CLI::warning(
					sprintf(
						'%s/%s couldn\'t be added to %s.',
						$to_add->locale,
						$to_add->slug,
						$dest_project->path
					)
				);
			}
		}

		foreach ( $changes['removed'] as $to_remove ) {
			if ( ! $to_remove->delete() ) {
				WP_CLI::warning(
					sprintf(
						'%s/%s couldn\'t be deleted from %s.',
						$to_remove->locale,
						$to_remove->slug,
						$src_project->path
					)
				);
			}
		}

		WP_CLI::line( 'Done!' );
	}
}
