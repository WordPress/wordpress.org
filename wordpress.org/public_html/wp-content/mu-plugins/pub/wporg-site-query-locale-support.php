<?php
/**
 * Plugin Name: Site Query Locale Support
 * Description: Allows to query sites by a WordPress locale.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * @package WordPressdotorg\ExtendedSiteQuery
 */

namespace WordPressdotorg\ExtendedSiteQuery;

const LOCALES_TABLES = 'wporg_locales';

/**
 * Adds WHERE and JOIN clauses if the 'locale', 'locale__in' or 'locale__not_in' query var is set.
 *
 * @param array          $clauses Site query clauses.
 * @param \WP_Site_Query $query   The site query.
 * @return array Site query clauses.
 */
function extend_sites_clauses( $clauses, $query ) {
	global $wpdb;

	if ( empty( $query->query_vars['locale'] ) && empty( $query->query_vars['locale__in'] ) && empty( $query->query_vars['locale__not_in'] ) ) {
		return $clauses;
	}

	$clauses['join'] = 'LEFT JOIN ' . LOCALES_TABLES . " ON ($wpdb->blogs.lang_id = " . LOCALES_TABLES . '.locale_id)';

	$where = [];

	if ( ! empty( $query->query_vars['locale'] ) ) {
		$where[] = $wpdb->prepare( LOCALES_TABLES . '.locale = %s', $query->query_vars['locale'] );
	}

	if ( ! empty( $query->query_vars['locale__in'] ) ) {
		$sql     = LOCALES_TABLES . '.locale IN (' . implode( ',', array_fill( 0, count( $query->query_vars['locale__in'] ), '%s' ) ) . ')';
		$where[] = $wpdb->prepare( $sql, $query->query_vars['locale__in'] );
	}

	if ( ! empty( $query->query_vars['locale__not_in'] ) ) {
		$sql     = LOCALES_TABLES . '.locale NOT IN (' . implode( ',', array_fill( 0, count( $query->query_vars['locale__not_in'] ), '%s' ) ) . ')';
		$where[] = $wpdb->prepare( $sql, $query->query_vars['locale__not_in'] );
	}

	$where = implode( ' AND ', $where );

	if ( empty( $clauses['where'] ) ) {
		$clauses['where'] = $where;
	} else {
		$clauses['where'] .= " AND $where";
	}

	return $clauses;
}
add_filter( 'sites_clauses', __NAMESPACE__ . '\extend_sites_clauses', 10, 2 );

/**
 * Adds 'locale', 'locale__in' and 'locale__not_in' to the default query vars.
 *
 * @param \WP_Site_Query $query The site query.
 */
function add_locale_to_default_query_vars( $query ) {
	$query->query_var_defaults = array_merge( $query->query_var_defaults, [
		'locale'         => '',
		'locale__in'     => '',
		'locale__not_in' => '',
	] );
}
add_action( 'parse_site_query', __NAMESPACE__ . '\add_locale_to_default_query_vars' );

/**
 * Adds 'locale' field to site details.
 *
 * @param \stdClass $details The site details.
 * @return \stdClass Site details.
 */
function add_locale_to_site_details( $details ) {
	if ( ! $details->lang_id ) {
		$details->locale = 'en_US';
		return $details;
	}

	wp_cache_add_global_groups( [ 'locale-associations' ] );

	$locales = wp_cache_get( 'id-locale', 'locale-associations' );
	if ( false === $locales ) {
		global $wpdb;
		$locales = $wpdb->get_results( 'SELECT locale_id, locale FROM ' . LOCALES_TABLES, OBJECT_K );
		wp_cache_set( 'id-locale', $locales, 'locale-associations' );
	}

	if ( isset( $locales[ $details->lang_id ] ) ) {
		$details->locale = $locales[ $details->lang_id ]->locale;
	} else {
		$details->locale = 'en_US';
	}

	return $details;
}
add_filter( 'site_details', __NAMESPACE__ . '\add_locale_to_site_details', 5 );
