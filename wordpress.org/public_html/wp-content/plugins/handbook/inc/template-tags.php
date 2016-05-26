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
		$is_handbook     = ! $handbook || ( $handbook === $post_type );
		$single_handbook = false;

		if ( is_singular() ) {
			$queried_obj = get_queried_object();

			if ( $queried_obj ) {
				$single_handbook = is_singular( $post_type );
			} else {
				// Queried object is not set, use the post type query var.		
				$qv_post_type = get_query_var( 'post_type' );

				if ( is_array( $qv_post_type ) ) {
					$qv_post_type = reset( $qv_post_type );
				}

				$single_handbook = ( $post_type === $qv_post_type );
			}
		}

		$handbook_query = $single_handbook || is_post_type_archive( $post_type );

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

/**
 * Returns the home URL for the current handbook post type.
 *
 * @return string|false URL on success, false on failure.
 */
function wporg_get_current_handbook_home_url() {
	$handbook = wporg_get_current_handbook();
	$url      = false;

	if ( $handbook ) {
		$page = get_page_by_path( $handbook, OBJECT, $handbook );
		if ( $page ) {
			$url = get_permalink( $page );
		} else {
			$url = get_post_type_archive_link( $handbook );
		}
	}

	return $url;
}
