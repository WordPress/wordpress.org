<?php

namespace WordPressdotorg\GlotPress\Routes\Routes;

use DateInterval;
use DatePeriod;
use DateTime;
use GP_Route;

class WP_Directory extends GP_Route {

	/**
	 * Prints stats about contributors of a specific project.
	 *
	 * @param GP_Project $project The project.
	 * @return array|false False if project not found, otherwise array with contributors.
	 */
	public function get_contributors( $project ) {
		global $wpdb;

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

		foreach ( $this->get_translation_contributors_by_locale( $project->id ) as $row ) {
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

		$sub_projects = $wpdb->get_col( $wpdb->prepare( "
			SELECT id
			FROM {$wpdb->gp_projects}
			WHERE parent_project_id = %d
		", $project->id ) );

		foreach ( $sub_projects as $sub_project ) {
			foreach ( $this->get_translation_contributors_by_locale( $sub_project ) as $row ) {
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

		return $contributors_by_locale;
	}

	/**
	 * Generates the chart data for contributors activity.
	 *
	 * @param \GP_Project $project The project.
	 * @return array The data to build a chart via Chartist.js.
	 */
	protected function get_contributors_chart_data( $project ) {
		global $wpdb;

		$sub_projects = $wpdb->get_col( $wpdb->prepare( "
			SELECT id
			FROM {$wpdb->gp_projects}
			WHERE parent_project_id = %d
		", $project->id ) );

		$project_ids = array_merge( array( $project->id ), $sub_projects );
		$translation_set_ids = $wpdb->get_col( "
			SELECT `id` FROM {$wpdb->gp_translation_sets} WHERE `project_id` IN ( " . implode( ',', $project_ids ) . ")
		" );

		if ( ! $translation_set_ids ) {
			return array();
		}

		$date_begin = new DateTime( '-6 day' );
		$date_end = new DateTime( 'NOW' );
		$date_interval = new DateInterval( 'P1D' );
		$date_range = new DatePeriod( $date_begin, $date_interval, $date_end );

		$days = array();
		foreach ( $date_range as $date ) {
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

		$chart_data = compact( 'labels', 'series' );

		return $chart_data;
	}

	/**
	 * Prints stats about language packs of a specific project.
	 *
	 * @param string $type Type of the language pack, plugin or theme.
	 * @param string $slug Slug of a project.
	 */
	public function get_language_packs( $type, $slug ) {
		$http_context = stream_context_create( array(
			'http' => array(
				'user_agent' => 'WordPress.org Translate',
			),
		) );
		if ( 'plugin' === $type ) {
			$type = 'plugins';
		} else {
			$type = 'themes';
		}
		$json = file_get_contents( "https://api.wordpress.org/translations/$type/1.0/?slug={$slug}", null, $http_context );
		$language_packs = $json && '{' == $json[0] ? json_decode( $json ) : null;

		return $language_packs;
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
