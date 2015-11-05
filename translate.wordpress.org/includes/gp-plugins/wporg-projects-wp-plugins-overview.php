<?php

class GP_WPorg_Route_WP_Plugins extends GP_Route {

	public function get_plugin_projects( $project_slug ) {
		global $gpdb;

		$project_path = 'wp-plugins/' . $project_slug;
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			return $this->die_with_404();
		}

		$rows = $gpdb->get_results( "
			SELECT
				path, locale, locale_slug,
				(100 * stats.current/stats.all) as percent_complete,
				stats.untranslated as waiting_strings
			FROM {$gpdb->prefix}project_translation_status stats
				LEFT JOIN {$gpdb->prefix}projects p ON stats.project_id = p.id
			WHERE
				p.parent_project_id = '{$project->id}'
		" );

		// Split out into $[Locale][Project] = %
		$translation_locale_statuses = array();
		foreach ( $rows as $set ) {

			// Find unique locale key.
			$locale_key = $set->locale;
			if ( 'default' != $set->locale_slug ) {
				$locale_key = $set->locale . '/' . $set->locale_slug;
			}
			$sub_project = str_replace( "$project_path/", '', $set->path );

			/*
			 * > 50% round down, so that a project with all strings except 1 translated shows 99%, instead of 100%.
			 * < 50% round up, so that a project with just a few strings shows 1%, instead of 0%.
			 */
			$percent_complete = (float) $set->percent_complete;
			$translation_locale_statuses[ $locale_key ][ $sub_project ] = ( $percent_complete > 50 ) ? floor( $percent_complete ) : ceil( $percent_complete );

			// Increment the amount of waiting strings.
			if ( ! isset( $translation_locale_statuses[ $locale_key ]['waiting'] ) ) {
				$translation_locale_statuses[ $locale_key ]['waiting'] = 0;
			}
			$translation_locale_statuses[ $locale_key ]['waiting'] += (int) $set->waiting_strings;

			ksort( $translation_locale_statuses[ $locale_key ], SORT_NATURAL );
		}
		unset( $project_path, $locale_key, $rows, $set, $sub_project );

		// Order by waiting strings, ascending.
		uksort( $translation_locale_statuses, function ( $a, $b ) use ( $translation_locale_statuses ) {
			if ( $translation_locale_statuses[ $a ]['waiting'] > $translation_locale_statuses[ $b ]['waiting'] ) {
				return 1;
			} elseif ( $translation_locale_statuses[ $a ]['waiting'] == $translation_locale_statuses[ $b ]['waiting'] ) {
				return strnatcmp( $a, $b );
			} else {
				return -1;
			}
		} );

		$project->icon = wporg_get_plugin_icon( $project->slug, 64 );

		$this->tmpl( 'projects-wp-plugins', get_defined_vars() );
	}
}
