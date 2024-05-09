<?php

namespace Wporg\TranslationEvents\Project;

use GP;

class Project_Repository {
	/**
	 * Get projects for an event.
	 */
	public function get_for_event( int $event_id ): array {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		// phpcs thinks we're doing a schema change but we aren't.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
				select
					o.project_id as project,
					group_concat( distinct e.locale ) as locales,
					sum(action = 'create') as created,
					count(*) as total,
					count(distinct user_id) as users
				from {$gp_table_prefix}event_actions e, {$gp_table_prefix}originals o
				where e.event_id = %d and e.original_id = o.id
				group by o.project_id
			",
				array(
					$event_id,
				)
			)
		);
		// phpcs:enable

		$projects = array();
		foreach ( $rows as $row ) {
			$row->project      = GP::$project->get( $row->project );
			$project_name      = $row->project->name;
			$parent_project_id = $row->project->parent_project_id;
			while ( $parent_project_id ) {
				$parent_project    = GP::$project->get( $parent_project_id );
				$parent_project_id = $parent_project->parent_project_id;
				$project_name      = substr( htmlspecialchars_decode( $parent_project->name ), 0, 35 ) . ' - ' . $project_name;
			}
			$projects[ $project_name ] = $row;
		}

		ksort( $projects );

		return $projects;
	}
}
