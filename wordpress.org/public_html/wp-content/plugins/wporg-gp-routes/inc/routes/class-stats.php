<?php

namespace WordPressdotorg\GlotPress\Routes\Routes;

use GP;
use GP_Route;

/**
 * Stats Route Class.
 *
 * Provides the route for translate.wordpress.org/stats.
 */
class Stats extends GP_Route {

	public function get_stats_overview() {
		global $wpdb;

		$projects = array(
			'meta/rosetta' => false,
			'meta/browsehappy' => false,
			'meta/themes' => false,
			'meta/plugins' => false,
			'meta/forums' => false,
			'apps/android' => false,
			'apps/ios' => false,
			'waiting' => false,
		);

		// I'm sure there's somewhere to fetch these from statically defined
		$wp_project = GP::$project->by_path( 'wp' );
		foreach ( GP::$project->find_many( array( 'parent_project_id' => $wp_project->id, 'active' => 1 ), 'name ASC' ) as $wp_sub_project ) {
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
				(100 * stats.current/stats.all) as percent_complete,
				stats.waiting+stats.fuzzy as waiting_strings
			FROM {$wpdb->project_translation_status} stats
				LEFT JOIN {$wpdb->gp_projects} p ON stats.project_id = p.id
			WHERE
				p.path IN ( $all_project_paths_sql )
				AND p.active = 1";

		$rows = $wpdb->get_results( $sql );

		// Split out into $[Locale][Project] = %
		$translation_locale_statuses = array();
		foreach ( $rows as $set ) {
			$locale_key = $set->locale;
			if ( 'default' != $set->locale_slug ) {
				$locale_key = $set->locale . '/' . $set->locale_slug;
			}

			/*
			 * > 50% round down, so that a project with all strings except 1 translated shows 99%, instead of 100%.
			 * < 50% round up, so that a project with just a few strings shows 1%, instead of 0%.
			 */
			$percent_complete = (float) $set->percent_complete;
			$percent_complete = ( $percent_complete > 50 ) ? floor( $percent_complete ) : ceil( $percent_complete );
			$translation_locale_statuses[ $locale_key ][ $set->path ] = $percent_complete;

			if ( ! isset( $translation_locale_statuses[ $locale_key ]['waiting'] ) ) {
				$translation_locale_statuses[ $locale_key ]['waiting'] = 0;
			}
			$translation_locale_statuses[ $locale_key ]['waiting'] += (int) $set->waiting_strings;
		}
		unset( $rows, $locale_key, $set );

		// Append the Plugins/Themes waiting strings
		$parent_project_ids = implode(',', array(
			GP::$project->by_path( 'wp-plugins' )->id,
			GP::$project->by_path( 'wp-themes' )->id,
		) );
		$sql = "SELECT
				locale, locale_slug,
				SUM( stats.waiting ) + SUM( stats.fuzzy ) as waiting_strings
			FROM {$wpdb->project_translation_status} stats
				LEFT JOIN {$wpdb->gp_projects} p ON stats.project_id = p.id
			WHERE
				p.parent_project_id IN ( $parent_project_ids )
				AND p.active = 1
			GROUP BY locale, locale_slug";

		$rows = $wpdb->get_results( $sql );
		foreach ( $rows as $set ) {
			$locale_key = $set->locale;
			if ( 'default' != $set->locale_slug ) {
				$locale_key = $set->locale . '/' . $set->locale_slug;
			}

			$translation_locale_statuses[ $locale_key ]['waiting'] += (int) $set->waiting_strings;
		}

		// Calculate a list of [Locale] = % subtotals
		$translation_locale_complete = array();
		foreach ( $translation_locale_statuses as $locale => $sets ) {
			unset( $sets['waiting'] );
			$sets_count = count( $sets );
			if ( $sets_count ) {
				$translation_locale_complete[ $locale ] = round( array_sum( $sets ) / $sets_count, 3 );
			} else {
				$translation_locale_complete[ $locale ] = 0;
			}
		}
		unset( $locale, $sets );

		// Sort by translation completeness, least number of waiting strings, and locale slug.
		uksort( $translation_locale_complete, function ( $a, $b ) use ( $translation_locale_complete, $translation_locale_statuses ) {
			if ( $translation_locale_complete[ $a ] < $translation_locale_complete[ $b ] ) {
				return 1;
			} elseif ( $translation_locale_complete[ $a ] == $translation_locale_complete[ $b ] ) {
				if ( $translation_locale_statuses[ $a ]['waiting'] != $translation_locale_statuses[ $b ]['waiting'] ) {
					return strnatcmp( $translation_locale_statuses[ $a ]['waiting'], $translation_locale_statuses[ $b ]['waiting'] );
				} else {
					return strnatcmp( $a, $b );
				}
			} else {
				return -1;
			}
		} );

		$this->tmpl( 'stats-overview', get_defined_vars() );
	}

}
