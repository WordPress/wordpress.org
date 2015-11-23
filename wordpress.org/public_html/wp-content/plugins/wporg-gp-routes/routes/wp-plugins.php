<?php

class WPorg_GP_Route_WP_Plugins extends GP_Route {

	public function get_plugin_projects( $project_slug ) {
		global $wpdb;

		$project_path = 'wp-plugins/' . $project_slug;
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			return $this->die_with_404();
		}

		$rows = $wpdb->get_results( "
			SELECT
				path, locale, locale_slug,
				(100 * stats.current/stats.all) as percent_complete,
				stats.waiting+stats.fuzzy as waiting_strings,
				stats.untranslated as untranslated
			FROM {$wpdb->prefix}project_translation_status stats
				LEFT JOIN {$wpdb->prefix}projects p ON stats.project_id = p.id
			WHERE
				p.parent_project_id = '{$project->id}'
		" );

		// Split out into $[Locale][Project] = %
		$translation_locale_statuses = array();
		$sub_projects = array();
		foreach ( $rows as $set ) {

			// Find unique locale key.
			$locale_key = $set->locale;
			if ( 'default' != $set->locale_slug ) {
				$locale_key = $set->locale . '/' . $set->locale_slug;
			}
			$sub_project = str_replace( "$project_path/", '', $set->path );
			$sub_projects[ $sub_project ] = true;

			/*
			 * > 50% round down, so that a project with all strings except 1 translated shows 99%, instead of 100%.
			 * < 50% round up, so that a project with just a few strings shows 1%, instead of 0%.
			 */
			$percent_complete = (float) $set->percent_complete;
			$translation_locale_statuses[ $locale_key ][ $sub_project ] = ( $percent_complete > 50 ) ? floor( $percent_complete ) : ceil( $percent_complete );

			// Increment the amount of waiting and untranslated strings.
			if ( ! isset( $translation_locale_statuses[ $locale_key ]['waiting'] ) ) {
				$translation_locale_statuses[ $locale_key ]['waiting'] = 0;
			}
			if ( ! isset( $translation_locale_statuses[ $locale_key ]['untranslated'] ) ) {
				$translation_locale_statuses[ $locale_key ]['untranslated'] = 0;
			}
			$translation_locale_statuses[ $locale_key ]['waiting'] += (int) $set->waiting_strings;
			$translation_locale_statuses[ $locale_key ]['untranslated'] += (int) $set->untranslated;


			ksort( $translation_locale_statuses[ $locale_key ], SORT_NATURAL );
		}

		// Check if the plugin has at least one code project. These won't be created if a plugin
		// has no text domain defined.
		$sub_projects = array_keys( $sub_projects );
		$has_error = ( ! in_array( 'dev', $sub_projects ) && ! in_array( 'stable', $sub_projects ) );

		unset( $project_path, $locale_key, $rows, $set, $sub_project, $sub_projects );

		// Calculate a list of [Locale] = % subtotals
		$translation_locale_complete = array();
		foreach ( $translation_locale_statuses as $locale => $sets ) {
			unset( $sets['waiting'], $sets['untranslated'] );
			$translation_locale_complete[ $locale ] = round( array_sum( $sets ) / count( $sets ), 3 );
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

		if ( function_exists( 'wporg_get_plugin_icon' ) ) {
			$project->icon = wporg_get_plugin_icon( $project->slug, 64 );
		} else {
			$project->icon = '<div class="default-icon"><span class="dashicons dashicons-admin-plugins"></span></div>';
		}

		$this->tmpl( 'projects-wp-plugins', get_defined_vars() );
	}
}
