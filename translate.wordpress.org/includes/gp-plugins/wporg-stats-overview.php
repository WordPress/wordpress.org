<?php

/**
 * This plugin offers up the /stats route which provides a overview of our current translation efforts.
 *
 * @author dd32
 */
class GP_WPorg_Stats_Overview extends GP_Plugin {
	var $id = 'wporg-stats-overview';

	function __construct() {
		parent::__construct();
		$this->add_action( 'init' );

	}

	function init() {
		GP::$router->prepend( "/stats/?", array( 'GP_WPorg_Route_Stats', 'get_stats_overview' ) );
	}
}

GP::$plugins->wporg_stats_overview = new GP_WPorg_Stats_Overview;

class GP_WPorg_Route_Stats extends GP_Route {

	public function get_stats_overview() {
		global $gpdb;

		$projects = array(
			'meta/rosetta' => false,
			'meta/browsehappy' => false,
			'meta/themes' => false,
			'meta/plugins' => false,
			'apps/android' => false,
			'apps/ios' => false
		);

		// I'm sure there's somewhere to fetch these from statically defined
		$wp_project = GP::$project->by_path('wp');
		foreach ( GP::$project->find_many( array( 'parent_project_id' => $wp_project->id, 'active' => 1 ) ) as $wp_sub_project ) {
			// Prefix the WordPress projects...
			$wp_sub_project->name = $wp_project->name . ' ' . $wp_sub_project->name;
			$projects = array_merge( array( $wp_sub_project->path => $wp_sub_project ), $projects );
		}

		// Load the projects for each display item
		array_walk( $projects, function( &$project, $project_slug ) {
			if ( ! $project ) {
				$project = GP::$project->by_path( $project_slug );
			}
		} );

		$all_project_paths_sql = '"' . implode( '", "', array_keys( $projects ) ) . '"';
		$sql = "SELECT
				path, locale, locale_slug,
				(100 * stats.current/stats.all) as percent_complete
			FROM {$gpdb->prefix}project_translation_status stats
				LEFT JOIN {$gpdb->prefix}projects p ON stats.project_id = p.id
			WHERE
				p.path IN ( $all_project_paths_sql )";

		$rows = $gpdb->get_results( $sql );

		// Split out into $[Locale][Project] = %
		$translation_locale_statuses = array();
		foreach ( $rows as $set ) {
			$locale_key = $set->locale;
			if ( 'default' != $set->locale_slug ) {
				$locale_key = $set->locale . '/' . $set->locale_slug;
			}
			$translation_locale_statuses[ $locale_key ][ $set->path ] = floor( (float) $set->percent_complete );
		}
		unset( $rows, $locale_key, $set );

		// Calculate a list of [Locale] = % subtotals
		$translation_locale_complete = array();
		foreach ( $translation_locale_statuses as $locale => $sets ) {
			$translation_locale_complete[ $locale ] = round( array_sum( $sets ) / count( $sets ), 3 );
		}
		unset( $locale, $sets );
		
		// Sort by Percent Complete, secondly by Slug
		uksort( $translation_locale_complete, function ( $a, $b ) use ( $translation_locale_complete ) {
			if ( $translation_locale_complete[ $a ] < $translation_locale_complete[ $b ] ) {
				return 1;
			} elseif ( $translation_locale_complete[ $a ] == $translation_locale_complete[ $b ] ) {
				return strnatcmp( $a, $b );
			} else {
				return -1;
			}
		} );

		$this->tmpl( 'stats-overview', get_defined_vars() );
	}

}
