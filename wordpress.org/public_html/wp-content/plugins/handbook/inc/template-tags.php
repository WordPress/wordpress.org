<?php

/**
 * Gets a list of all registered hand book post types.
 *
 * Wrapper function for WPorg_Handbook_Init::get_post_types().
 *
 * @return array Array with full handbook post type names {post-type}-handbook.
 */
function wporg_get_handbook_post_types() {
	if ( ! class_exists( 'WPorg_Handbook_Init' ) ) {
		return array();
	}

	$post_types = WPorg_Handbook_Init::get_post_types();

	foreach ( $post_types as $key => $post_type ) {
		if ( 'handbook' !== $post_type ) {
			$post_types[ $key ] = $post_type . '-handbook';
		}
	}

	return $post_types;
}

/**
 * Is the query for an existing handbook page?
 *
 * @param string  $handbook Handbook post type.
 * @return bool             Whether the query is for an existing handbook page. Returns true on handbook pages.
 */
function wporg_is_handbook( $handbook = '' ) {
	$post_types = wporg_get_handbook_post_types();

	if ( is_admin() || ! $post_types ) {
		return false;
	}

	foreach ( $post_types as $post_type ) {
		$is_handbook    = ! $handbook || ( $handbook === $post_type );
		$handbook_query = is_singular( $post_type ) || is_post_type_archive( $post_type );

		if ( $is_handbook && $handbook_query ) {
			return true;
		}
	}

	return false;
}

/**
 * Is the current (or specified) post_type a handbook post type?
 *
 * @param string  $post_type Optional. The post_type to check for being a handbook post type. Default '' (the current post type).
 * @return bool
 */
function wporg_is_handbook_post_type( $post_type = '' ) {
	if ( ! $post_type ) {
		$post_type = get_post_type();
	}

	return in_array( $post_type, wporg_get_handbook_post_types() );
}

/**
 * Returns the current handbook post type.
 *
 * @return string|false Post type on success, false on failure.
 */
function wporg_get_current_handbook() {
	$handbooks = wporg_get_handbook_post_types();

	foreach ( $handbooks as $handbook ) {
		if ( wporg_is_handbook( $handbook ) ) {
			return $handbook;
		}
	}

	return false;
}
