<?php

class WPorg_GP_Route_WP_Themes extends WPorg_GP_Route_WP_Directory {

	/**
	 * Prints stats about sub-project of a specific project.
	 *
	 * @param string $project_slug Slug of a project.
	 */
	public function get_theme_projects( $project_slug ) {
		global $wpdb;

		$project_path = 'wp-themes/' . $project_slug;
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
			FROM {$wpdb->project_translation_status} stats
				LEFT JOIN {$wpdb->gp_projects} p ON stats.project_id = p.id
			WHERE
				p.id = '{$project->id}'
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

			/*
			 * > 50% round down, so that a project with all strings except 1 translated shows 99%, instead of 100%.
			 * < 50% round up, so that a project with just a few strings shows 1%, instead of 0%.
			 */
			$percent_complete = (float) $set->percent_complete;
			$translation_locale_statuses[ $locale_key ]['stable'] = ( $percent_complete > 50 ) ? floor( $percent_complete ) : ceil( $percent_complete );

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

		$project->icon = $this->get_theme_icon( $project, 64 );

		$this->tmpl( 'projects-wp-themes', get_defined_vars() );
	}

	/**
	 * Prints stats about contributors of a specific project.
	 *
	 * @param string $project_slug Slug of a project.
	 */
	public function get_theme_contributors( $project_slug ) {
		global $wpdb;

		$project_path = 'wp-themes/' . $project_slug;
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			return $this->die_with_404();
		}

		$project->icon = $this->get_theme_icon( $project, 64 );

		$contributors_by_locale = gp_get_meta( 'wp-themes', $project->id, 'contributors-by-locale' );
		if ( ! $contributors_by_locale || $contributors_by_locale['last_updated'] + HOUR_IN_SECONDS < time() ) {
			$contributors_by_locale = $this->get_contributors( $project );
			$contributors_by_locale['last_updated'] = time();
			gp_update_meta( $project->id, 'contributors-by-locale', $contributors_by_locale, 'wp-themes' );
		}

		$chart_data = gp_get_meta( 'wp-themes', $project->id, 'contributors-chart-data' );
		if ( ! $chart_data || $chart_data['last_updated'] + DAY_IN_SECONDS < time() ) {
			$chart_data = $this->get_contributors_chart_data( $project );
			$chart_data['last_updated'] = time();
			gp_update_meta( $project->id, 'contributors-chart-data', $chart_data, 'wp-themes' );
		}

		unset( $contributors_by_locale['last_updated'], $chart_data['last_updated'] );

		$this->tmpl( 'projects-wp-themes-contributors', get_defined_vars() );
	}

	/**
	 * Prints stats about language packs of a specific project.
	 *
	 * @param string $project_slug Slug of a project.
	 */
	public function get_theme_language_packs( $project_slug ) {
		$project_path = 'wp-themes/' . $project_slug;
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			return $this->die_with_404();
		}

		$project->icon = $this->get_theme_icon( $project, 64 );

		$language_packs = $this->get_language_packs( 'theme', $project_slug );

		$this->tmpl( 'projects-wp-themes-language-packs', get_defined_vars() );
	}

	/**
	 * Retrieves the icon of a theme.
	 *
	 * @param GP_Project $project The theme project.
	 * @param int        $size    Optional. The size of the icon. Default 64.
	 * @return string HTML markup for the icon.
	 */
	private function get_theme_icon( $project, $size = 64 ) {
		$default = '<div class="default-icon"><span class="dashicons dashicons-admin-themes"></span></div>';

		$screenshot = gp_get_meta( 'wp-themes', $project->id, 'screenshot' );
		if ( $screenshot ) {
			return sprintf(
				'<div class="icon"><img src="%s" alt="" width="%d" height="%d"></div>',
				esc_url( 'https://i0.wp.com/' . $screenshot . '?w=' . $size * 2 . '&strip=all' ),
				$size,
				$size
			);
		}

		return $default;
	}
}
