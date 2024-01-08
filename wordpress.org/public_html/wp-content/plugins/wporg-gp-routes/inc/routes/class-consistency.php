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

		$search = $set = $project = '';
		$search_case_sensitive = false;

		if ( isset( $_REQUEST['search'] ) && strlen( $_REQUEST['search'] ) ) {
			$search = wp_unslash( $_REQUEST['search'] );
		}

		if ( ! empty( $_REQUEST['set'] ) ) {
			$set = wp_unslash( $_REQUEST['set'] );
			if ( ! isset( $sets[ $set ] ) ) {
				$set = '';
			}
		}

		if ( ! empty( $_REQUEST['search_case_sensitive'] ) ) {
			$search_case_sensitive = true;
		}

		if ( ! empty( $_REQUEST['project'] ) && isset( self::PROJECTS[ $_REQUEST['project'] ] ) ) {
			$project = $_REQUEST['project'];
		}

		$locale        = '';
		$set_slug          = '';
		$locale_is_rtl = false;

		if ( $set ) {
			list( $locale, $set_slug ) = explode( '/', $set );
			$locale_is_rtl = 'rtl' === GP_Locales::by_slug( $locale )->text_direction;
		}

		$results = [];
		$performed_search = false;
		if ( strlen( $search ) && $locale && $set_slug ) {
			$performed_search = true;
			$results = $this->query( [
				'search'         => $search,
				'locale'         => $locale,
				'set_slug'       => $set_slug,
				'case_sensitive' => $search_case_sensitive,
				'project'        => $project,
			] );

			$translations               = wp_list_pluck( $results, 'translation', 'translation_id' );
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

		if ( false === $sets ) {
			$_sets = $wpdb->get_results( "SELECT name, locale, slug FROM {$wpdb->gp_translation_sets} GROUP BY locale, slug ORDER BY name" );

			$sets = array();
			foreach ( $_sets as $set ) {
				$sets[ "{$set->locale}/$set->slug" ] = $set->name;
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

		if ( $args['case_sensitive'] ) {
			$collation = 'BINARY';
		} else {
			$collation = '';
		}

		$search   = $wpdb->prepare( "= {$collation} %s", $args['search'] );
		$locale   = $wpdb->prepare( '%s', $args['locale'] );
		$set_slug = $wpdb->prepare( '%s', $args['set_slug'] );

		$project_where = '';
		if ( $args['project'] ) {
			$project = GP::$project->get( $args['project'] );
			$project_where = $wpdb->prepare( 'AND p.path LIKE %s', $wpdb->esc_like( $project->path ) . '/%' );
		}

		$results = $wpdb->get_results( "
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
				{$wpdb->gp_translation_sets} as ts on ts.id = t.translation_set_id
			WHERE
				p.active = 1
				AND t.status = 'current'
				AND o.status = '+active' AND o.singular {$search}
				AND ts.locale = {$locale} AND ts.slug = {$set_slug}
				{$project_where}
			LIMIT 0, 500
		" );

		if ( ! $results ) {
			return [];
		}

		// Group by translation and project path. Done in PHP because it's faster as in MySQL.
		usort( $results, [ $this, '_sort_callback' ] );

		return $results;
	}

	public function _sort_callback( $a, $b ) {
		$sort = strnatcmp( $a->translation . $a->original_context, $b->translation . $b->original_context );
		if ( 0 === $sort ) {
			$sort = strnatcmp( $a->project_path, $b->project_path );
		}

		return $sort;
	}
}
