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

		$all_project_ids = $sql_cases = array();
		foreach ( $projects as $slug => &$project ) {
			$project_ids = array();
			if ( ! $project ) {
				$project = GP::$project->by_path( $slug );
			}
			$project_ids[] = $project->id;

			foreach ( $project->inclusive_sub_projects() as $sub ) {
				$project_ids[] = $sub->id;
			}
			unset( $sub );

			$project_id_list = implode( ', ', array_map( 'intval', $project_ids ) );
			$sql_cases[] = $gpdb->prepare( "WHEN ts.project_id IN( $project_id_list ) THEN %s", $slug );

			$all_project_ids = array_merge( $all_project_ids, $project_ids );
		}
		unset( $slug, $project_ids, $subs, $project );

		$sql_cases = implode( "\n", $sql_cases );
		$all_project_ids = implode( ', ', array_map( 'intval', $all_project_ids ) );

		$sql = "SELECT
				CASE
					$sql_cases
				END as project_slug,
				s.locale as locale,
				s.slug as locale_slug,
				(100 * SUM(ts.current)/SUM(ts.all)) as percent_complete
			FROM translate_project_translation_status ts
				LEFT JOIN translate_translation_sets s ON ts.project_id = s.project_id AND ts.translation_set_id = s.id
			WHERE
				ts.project_id IN ( $all_project_ids )
			GROUP BY project_slug, s.locale, s.slug";

		$rows = $gpdb->get_results( $sql );

		// Split out into $[Locale][Project] = %
		$translation_locale_statuses = array();
		foreach ( $rows as $set ) {
			$locale_key = $set->locale;
			if ( 'default' != $set->locale_slug ) {
				$locale_key = $set->locale . '/' . $set->locale_slug;
			}
			$translation_locale_statuses[ $locale_key ][ $set->project_slug ] = floor( (float) $set->percent_complete );
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
