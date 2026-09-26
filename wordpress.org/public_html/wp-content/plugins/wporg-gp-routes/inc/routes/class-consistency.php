<?php

namespace WordPressdotorg\GlotPress\Routes\Routes;

use GP;
use GP_Locales;
use GP_Route;

/**
 * Consistency Route Class.
 *
 * Provides the route for translate.wordpress.org/consistency.
 */
class Consistency extends GP_Route {

	private $cache_group = 'wporg-translate';

	const PROJECTS = array(
		1      => 'WordPress',
		523    => 'Themes',
		17     => 'Plugins',
		487    => 'Meta',
		281    => 'Apps',
		473698 => 'Patterns',
	);

	/**
	 * Prints a search form and the search results for a consistency view.
	 */
	public function get_search_form() {
		$sets = $this->get_translation_sets();

		$search                = '';
		$set                   = '';
		$project               = 0;
		$search_case_sensitive = false;

		if ( isset( $_REQUEST['search'] ) && is_string( $_REQUEST['search'] ) && '' !== $_REQUEST['search'] ) {
			$search = wp_unslash( $_REQUEST['search'] );
		}

		if ( isset( $_REQUEST['set'] ) && is_string( $_REQUEST['set'] ) ) {
			$raw_set = wp_unslash( $_REQUEST['set'] );
			if ( isset( $sets[ $raw_set ] ) ) {
				$set = $raw_set;
			}
		}

		if ( ! empty( $_REQUEST['search_case_sensitive'] ) ) {
			$search_case_sensitive = true;
		}

		if ( ! empty( $_REQUEST['project'] ) && isset( self::PROJECTS[ (int) $_REQUEST['project'] ] ) ) {
			$project = (int) $_REQUEST['project'];
		}

		$locale        = '';
		$set_slug      = '';
		$locale_is_rtl = false;

		if ( $set && str_contains( $set, '/' ) ) {
			list( $locale, $set_slug ) = explode( '/', $set, 2 );
			$gp_locale                 = GP_Locales::by_slug( $locale );
			$locale_is_rtl             = $gp_locale && 'rtl' === $gp_locale->text_direction;
		}

		$results                    = [];
		$performed_search           = false;
		$translations               = [];
		$translations_unique        = [];
		$translations_unique_counts = [];

		if ( '' !== $search && $locale && $set_slug ) {
			$performed_search = true;
			$results          = $this->query( [
				'search'         => $search,
				'locale'         => $locale,
				'set_slug'       => $set_slug,
				'case_sensitive' => $search_case_sensitive,
				'project'        => $project,
			] );

			$translations               = wp_list_pluck( $results, 'translation', 'translation_id' );
			$translations               = array_map( 'strval', $translations );
			$translations_unique        = array_values( array_unique( $translations ) );
			$translations_unique_counts = array_count_values( $translations );

			// Sort the unique translations by highest count first.
			arsort( $translations_unique_counts );
		}

		$projects = self::PROJECTS;

		$this->tmpl( 'consistency', get_defined_vars() );
	}

	/**
	 * Retrieves a list of unique translation sets.
	 *
	 * @return array Array of sets.
	 */
	private function get_translation_sets() {
		global $wpdb;

		$sets = wp_cache_get( 'translation-sets', $this->cache_group );

		if ( empty( $sets ) ) {
			$_sets = $wpdb->get_results(
				"SELECT MIN(name) AS name, locale, slug
				 FROM {$wpdb->gp_translation_sets}
				 GROUP BY locale, slug
				 ORDER BY name ASC"
			);

			$sets = array();
			if ( $_sets ) {
				foreach ( $_sets as $set ) {
					$sets[ "{$set->locale}/{$set->slug}" ] = $set->name;
				}
			}

			wp_cache_set( 'translation-sets', $sets, $this->cache_group, DAY_IN_SECONDS );
		}

		return $sets;
	}

	/**
	 * Performs the search query.
	 *
	 * @param array $args Query arguments.
	 *
	 * @return array The search results.
	 */
	private function query( $args ) {
		global $wpdb;

		$collation     = $args['case_sensitive'] ? 'BINARY' : '';
		$project_where = '';
		$query_params  = [];

		if ( ! empty( $args['project'] ) ) {
			$project = GP::$project->get( (int) $args['project'] );
			if ( $project && ! empty( $project->path ) ) {
				$project_where  = 'AND ( p.path = %s OR p.path LIKE %s )';
				$query_params[] = $project->path;
				$query_params[] = $wpdb->esc_like( $project->path ) . '/%';
			}
		}

		array_unshift( $query_params, $args['search'], $args['locale'], $args['set_slug'] );

		$query = "
			SELECT
				p.name AS project_name,
				p.id AS project_id,
				p.path AS project_path,
				p.parent_project_id AS project_parent_id,
				p.active AS active,
				o.singular AS original_singular,
				o.plural AS original_plural,
				o.context AS original_context,
				o.id AS original_id,
				t.translation_0 AS translation,
				t.date_added AS translation_added,
				t.id AS translation_id
			FROM {$wpdb->gp_originals} AS o
			JOIN
				{$wpdb->gp_projects} AS p ON p.id = o.project_id
			JOIN
				{$wpdb->gp_translations} AS t ON o.id = t.original_id
			JOIN
				{$wpdb->gp_translation_sets} AS ts ON ts.id = t.translation_set_id
			WHERE
				p.active = 1
				AND t.status = 'current'
				AND o.status = '+active'
				AND o.singular = {$collation} %s
				AND ts.locale = %s
				AND ts.slug = %s
				{$project_where}
			LIMIT 0, 500
		";

		$results = $wpdb->get_results( $wpdb->prepare( $query, $query_params ) );

		if ( ! $results ) {
			return [];
		}

		// Group by translation and project path. Done in PHP because it's faster than in MySQL.
		usort( $results, [ $this, '_sort_callback' ] );

		return $results;
	}

	public function _sort_callback( $a, $b ) {
		$sort = strnatcmp( (string) $a->translation, (string) $b->translation );
		if ( 0 === $sort ) {
			$sort = strnatcmp( (string) $a->original_context, (string) $b->original_context );
		}
		if ( 0 === $sort ) {
			$sort = strnatcmp( (string) $a->project_path, (string) $b->project_path );
		}

		return $sort;
	}
}
