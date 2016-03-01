<?php

class WPorg_GP_Route_WP_Plugins extends GP_Route {

	/**
	 * Prints stats about sub-project of a specific project.
	 *
	 * @param string $project_slug Slug of a project.
	 */
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
			FROM {$wpdb->project_translation_status} stats
				LEFT JOIN {$wpdb->gp_projects} p ON stats.project_id = p.id
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

	/**
	 * Prints stats about contributors of a specific project.
	 *
	 * @param string $project_slug Slug of a project.
	 */
	public function get_plugin_contributors( $project_slug ) {
		global $wpdb;

		$project_path = 'wp-plugins/' . $project_slug;
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			return $this->die_with_404();
		}

		if ( function_exists( 'wporg_get_plugin_icon' ) ) {
			$project->icon = wporg_get_plugin_icon( $project->slug, 64 );
		} else {
			$project->icon = '<div class="default-icon"><span class="dashicons dashicons-admin-plugins"></span></div>';
		}

		$contributors_by_locale = array();
		$default_value = array(
			'count' => 0,
			'editors' => array(),
			'contributors' => array(),
		);

		$translation_editors = $wpdb->get_results( $wpdb->prepare( "
			SELECT
				`user_id`, `locale`
			FROM {$wpdb->wporg_translation_editors}
			WHERE `project_id` = %d
		", $project->id ), OBJECT );

		foreach ( $translation_editors as $translation_editor ) {
			if ( ! isset( $contributors_by_locale[ $translation_editor->locale ] ) ) {
				$contributors_by_locale[ $translation_editor->locale ] = $default_value;
			}

			$user = get_user_by( 'id', $translation_editor->user_id );
			if ( ! $user ) {
				continue;
			}

			$contributors_by_locale[ $translation_editor->locale ]['editors'][ $translation_editor->user_id ] = (object) array(
				'nicename'     => $user->user_nicename,
				'display_name' => $this->_encode( $user->display_name ),
			);

			$contributors_by_locale[ $translation_editor->locale ]['count']++;
		}

		unset( $translation_editors );

		$sub_projects = $wpdb->get_col( $wpdb->prepare( "
			SELECT id
			FROM {$wpdb->gp_projects}
			WHERE parent_project_id = %d
		", $project->id ) );

		foreach ( $sub_projects as $sub_project ) {
			foreach( $this->get_translation_contributors_by_locale( $sub_project ) as $row ) {
				if ( ! isset( $contributors_by_locale[ $row->locale ] ) ) {
					$contributors_by_locale[ $row->locale ] = $default_value;
				}

				if ( isset( $contributors_by_locale[ $row->locale ]['editors'][ $row->user_id ] ) ) {
					continue;
				}

				if ( isset( $contributors_by_locale[ $row->locale ]['contributors'][ $row->user_id ] ) ) {
					continue;
				}

				$user = get_user_by( 'id', $row->user_id );
				if ( ! $user ) {
					continue;
				}

				$contributors_by_locale[ $row->locale ]['contributors'][ $row->user_id ] = (object) array(
					'nicename'     => $user->user_nicename,
					'display_name' => $this->_encode( $user->display_name ),
				);

				$contributors_by_locale[ $row->locale ]['count']++;
			}
		}

		$chart_data = $this->get_plugin_contributors_chart_data( $project->id, $sub_projects );

		$this->tmpl( 'projects-wp-plugins-contributors', get_defined_vars() );
	}

	/**
	 * Generates the chart data for contributors activity.
	 *
	 * @param  int    $project_id   The ID of a project. Used to store data as meta.
	 * @param  array $sub_projects Optional. IDs of sub-projects.
	 * @return array The data to build a chart via Chartist.js.
	 */
	private function get_plugin_contributors_chart_data( $project_id, $sub_projects = null ) {
		$chart_data = gp_get_meta( 'wp-plugins', $project_id, 'contributors-chart-data' );
		if ( $chart_data ) {
			if ( $chart_data['last_updated'] + DAY_IN_SECONDS > time() ) {
				return $chart_data;
			}
		}

		global $wpdb;

		if ( ! $sub_projects ) {
			$sub_projects = $wpdb->get_col( $wpdb->prepare( "
				SELECT id
				FROM {$wpdb->gp_projects}
				WHERE parent_project_id = %d
			", $$project_id ) );
		}

		$translation_set_ids = $wpdb->get_col( "
			SELECT `id` FROM {$wpdb->gp_translation_sets} WHERE `project_id` IN (" . implode( ',', $sub_projects ) . ")
		" );

		if ( ! $translation_set_ids ) {
			return array();
		}

		$date_begin = new DateTime( '-6 day' );
		$date_end = new DateTime( 'NOW' );
		$date_interval = new DateInterval( 'P1D' );
		$date_range = new DatePeriod( $date_begin, $date_interval, $date_end );

		$days = array();
		foreach( $date_range as $date ) {
			$days[] = $date->format( 'Y-m-d' );
		}
		$days[] = $date_end->format( 'Y-m-d' );

		$counts = $wpdb->get_results( "
			SELECT
				DATE(date_modified) AS `day`, COUNT(*) AS `count`, `status`
			FROM {$wpdb->gp_translations}
			WHERE
				`translation_set_id` IN (" . implode( ',', $translation_set_ids ) . ")
				AND date_modified >= ( CURDATE() - INTERVAL 7 DAY )
			GROUP BY `status`, `day`
			ORDER BY `day` DESC
		" );

		$status = array( 'current', 'waiting', 'rejected' );
		$data = [];
		foreach ( $days as $day ) {
			$data[ $day ] = array_fill_keys( $status, 0 );
			foreach ( $counts as $count ) {
				if ( $count->day !== $day || ! in_array( $count->status, $status ) ) {
					continue;
				}

				$data[ $day ][ $count->status ] = (int) $count->count;
			}
		}

		$labels = array_keys( $data );
		array_pop( $labels );
		$labels[] = ''; // Don't show a label for today

		$series = array();
		$series_data = array_values( $data );
		foreach ( $status as $stati ) {
			$series[] = (object) array(
				'name' => $stati,
				'data' => wp_list_pluck( $series_data, $stati ),
			);
		}

		$last_updated = time();
		$chart_data = compact( 'labels', 'series', 'last_updated' );

		gp_update_meta( $project_id, 'contributors-chart-data', $chart_data, 'wp-plugins' );

		return $chart_data;
	}

	/**
	 * Prints stats about language packs of a specific project.
	 *
	 * @param string $project_slug Slug of a project.
	 */
	public function get_plugin_language_packs( $project_slug ) {
		$project_path = 'wp-plugins/' . $project_slug;
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			return $this->die_with_404();
		}

		if ( function_exists( 'wporg_get_plugin_icon' ) ) {
			$project->icon = wporg_get_plugin_icon( $project->slug, 64 );
		} else {
			$project->icon = '<div class="default-icon"><span class="dashicons dashicons-admin-plugins"></span></div>';
		}

		$http_context = stream_context_create( array(
			'http' => array(
				'user_agent' => 'WordPress.org Translate',
			),
		) );
		$json = file_get_contents( "https://api.wordpress.org/translations/plugins/1.0/?slug={$project_slug}", null, $http_context );
		$language_packs = $json && '{' == $json[0] ? json_decode( $json ) : null;

		$this->tmpl( 'projects-wp-plugins-language-packs', get_defined_vars() );
	}

	/**
	 * Retrieves translators of a specific project.
	 *
	 * @param int $project_id Project ID.
	 * @return object Translators of the project.
	 */
	private function get_translation_contributors_by_locale( $project_id ) {
		global $wpdb;

		$sql = $wpdb->prepare( "
			SELECT ts.`locale`, ts.`slug` AS `locale_slug`, t.`user_id`
			FROM `{$wpdb->gp_translations}` t, `{$wpdb->gp_translation_sets}` ts
			WHERE t.`translation_set_id` = ts.`id`
			    AND t.`user_id` IS NOT NULL AND t.`user_id` != 0
			    AND t.`date_modified` > %s
			    AND ts.`project_id` = %d
			    AND t.`status` <> 'rejected'
			GROUP BY ts.`locale`, ts.`slug`, t.`user_id`
		", date( 'Y-m-d', time() - YEAR_IN_SECONDS ), $project_id );

		return $wpdb->get_results( $sql );
	}

	private function _encode( $raw ) {
		$raw = mb_convert_encoding( $raw, 'UTF-8', 'ASCII, JIS, UTF-8, Windows-1252, ISO-8859-1' );
		return ent2ncr( htmlspecialchars_decode( htmlentities( $raw, ENT_NOQUOTES, 'UTF-8' ), ENT_NOQUOTES ) );
	}
}
