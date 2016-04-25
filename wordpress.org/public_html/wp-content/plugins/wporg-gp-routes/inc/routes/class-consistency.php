<?php

namespace WordPressdotorg\GlotPress\Routes\Routes;

use GP_Locales;
use GP_Route;

/**
 * Consistency Route Class.
 *
 * Provides the route for translate.wordpress.org/consistency.
 */
class Consistency extends GP_Route {

	private $cache_group = 'wporg-translate';

	/**
	 * Prints a search form and the search results for a consistency view.
	 */
	public function get_search_form() {
		$sets = $this->get_translation_sets();

		$search = $set = '';
		$search_fuzzy = false;
		$search_case_sensitive = true;

		if ( ! empty( $_REQUEST['search'] ) ) {
			$search = wp_unslash( $_REQUEST['search'] );
		}

		if ( ! empty( $_REQUEST['set'] ) ) {
			$set = wp_unslash( $_REQUEST['set'] );
			if ( ! isset( $sets[ $set ] ) ) {
				$set = '';
			}
		}

		if ( ! empty( $_REQUEST ) && empty( $_REQUEST['search_case_sensitive'] ) ) {
			$search_case_sensitive = false;
		}

		if ( ! empty( $_REQUEST['search_fuzzy'] ) ) {
			$search_fuzzy = true;
		}

		$locale_is_rtl = false;
		if ( $set ) {
			list( $locale, $slug ) = explode( '/', $set );
			$locale_is_rtl = 'rtl' === GP_Locales::by_slug( $locale )->text_direction;
		}

		$results = [];
		$performed_search = false;
		if ( $search && $set ) {
			$performed_search = true;
			$results = $this->query( [
				'search'         => $search,
				'set'            => $set,
				'fuzzy'          => $search_fuzzy,
				'case_sensitive' => $search_case_sensitive,
			] );

			$translations = wp_list_pluck( $results, 'translation', 'translation_id' );
			$translations_unique = array_unique( $translations );
		}

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

		list( $locale, $slug ) = explode( '/', $args['set'] );

		if ( $args['case_sensitive'] ) {
			$collation = 'BINARY';
		} else {
			$collation = '';
		}

		if ( $args['fuzzy'] ) {
			$search = $wpdb->prepare( "LIKE {$collation} %s", $wpdb->esc_like( $args['search'] ) . '%%' );
		} else {
			$search = $wpdb->prepare( "= {$collation} %s", $args['search'] );
		}

		$locale = $wpdb->prepare( '%s', $locale );
		$slug = $wpdb->prepare( '%s', $slug );

		$results = $wpdb->get_results( "
			SELECT
				p.name AS project_name,
				p.id AS project_id,
				p.path AS project_path,
				p.parent_project_id AS project_parent_id,
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
				AND ts.locale = {$locale} AND ts.slug = {$slug}
			LIMIT 0, 500
		" );

		if ( ! $results ) {
			return [];
		}

		// Group by translation. Done in PHP because it's faster as in MySQL.
		usort( $results, [ $this, '_sort_callback' ] );

		return $results;
	}

	public function _sort_callback( $a, $b ) {
		return strnatcmp( $a->translation, $b->translation );
	}
}
