<?php

/**
 * wcpt_has_venues()
 *
 * The main Venue loop. WordPress makes this easy for us.
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 *
 * @global WP_Query $wcv_template
 * @param array $args Possible arguments to change returned Venues
 * @return object Multidimensional array of Venue information
 */
function wcpt_has_venues ( $args = '' ) {
	global $wcv_template;

	$default = array (
		// Narrow query down to Venue Post Type
		'post_type'        => WCV_POST_TYPE_ID,

		// No hierarchy
		'post_parent'      => '0',

		// 'author', 'date', 'title', 'modified', 'parent', rand',
		'orderby'          => isset( $_REQUEST['orderby'] ) && in_array( $_REQUEST['orderby'], array( 'author', 'date', 'title', 'modified', 'parent', 'rand' ) ) ? $_REQUEST['orderby'] : 'date',

		// 'ASC', 'DESC'
		'order'            => isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array( 'ASC', 'DESC' ) ) ? $_REQUEST['order'] : 'DESC',

		// Default is 15
		'posts_per_page'   => isset( $_REQUEST['posts'] ) ? absint( $_REQUEST['posts'] ) : 15,

		// Page Number
		'paged'            => isset( $_REQUEST['wcpage'] ) ? absint( $_REQUEST['wcpage'] ) : 1,

		// Topic Search
		's'                => empty( $_REQUEST['wcs'] ) ? '' : sanitize_text_field( $_REQUEST['wcs'] ),
	);

	// Set up variables
	$wcpt_q = wp_parse_args( $args, $default );
	$r      = extract( $wcpt_q );

	// Call the query
	$wcv_template = new WP_Query( $wcpt_q );

	// Add pagination values to query object
	$wcv_template->posts_per_page = $posts_per_page;
	$wcv_template->paged          = $paged;

	// Only add pagination if query returned results
	if ( (int)$wcv_template->found_posts && (int)$wcv_template->posts_per_page ) {

		// Pagination settings with filter
		$wcpt_pagination = apply_filters( 'wcpt_pagination', array (
			'base'      => add_query_arg( 'wvpage', '%#%' ),
			'format'    => '',
			'total'     => ceil( (int)$wcv_template->found_posts / (int)$posts_per_page ),
			'current'   => (int)$wcv_template->paged,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'mid_size'  => 1
		) );

		// Add pagination to query object
		$wcv_template->pagination_links = paginate_links ( $wcpt_pagination );
	}

	return apply_filters( 'wcpt_has_venues', $wcv_template->have_posts(), $wcv_template );
}

/**
 * wcpt_venues()
 *
 * Whether there are more Venues available in the loop
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 *
 * @global WP_Query $wcv_template
 * @return object Venue information
 */
function wcpt_venues () {
	global $wcv_template;
	return $wcv_template->have_posts();
}

/**
 * wcpt_the_venue()
 *
 * Loads up the current Venue in the loop
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 *
 * @global WP_Query $wcv_template
 * @return object Venue information
 */
function wcpt_the_venue () {
	global $wcv_template;
	return $wcv_template->the_post();
}

/**
 * wcpt_venue_id()
 *
 * Echo id from wcpt_venue_id()
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 *
 * @uses wcpt_get_venue_id()
 */
function wcpt_venue_id () {
	echo wcpt_get_venue_id();
}
	/**
	 * wcpt_get_venue_id()
	 *
	 * Get the id of the user in a Venue loop
	 *
	 * @package Venue Post Type
	 * @subpackage Template Tags
	 * @since Venue Post Type (0.1)
	 *
	 * @return string Venue id
	 */
	function wcpt_get_venue_id () {
		global $wcv_template;

		if ( isset( $wcv_template->post ) )
			$venue_id = $wcv_template->post->ID;
		else
			$venue_id = get_the_ID();

		return apply_filters( 'wcpt_get_venue_id', $venue_id );
	}

/**
 * wcpt_venue_permalink ()
 *
 * Output the link to the Venue in the Venue loop
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 *
 * @param int $venue_id optional
 * @uses wcpt_get_venue_permalink()
 */
function wcpt_venue_permalink ( $venue_id = 0 ) {
	echo wcpt_get_venue_permalink( $venue_id );
}
	/**
	 * wcpt_get_venue_permalink()
	 *
	 * Return the link to the Venue in the loop
	 *
	 * @package Venue Post Type
	 * @subpackage Template Tags
	 * @since Venue Post Type (0.1)
	 *
	 * @param int $venue_id optional
	 * @uses apply_filters
	 * @uses get_permalink
	 * @return string Permanent link to Venue
	 */
	function wcpt_get_venue_permalink ( $venue_id = 0 ) {
		if ( empty( $venue_id ) )
			$venue_id = wcpt_get_venue_id();

		return apply_filters( 'wcpt_get_venue_permalink', get_permalink( $venue_id ) );
	}

/**
 * wcpt_venue_title ()
 *
 * Output the title of the Venue in the loop
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 *
 * @param int $venue_id optional
 * @uses wcpt_get_venue_title()
 */
function wcpt_venue_title ( $venue_id = 0 ) {
	echo wcpt_get_venue_title( $venue_id );
}
	/**
	 * wcpt_get_venue_title ()
	 *
	 * Return the title of the Venue in the loop
	 *
	 * @package Venue Post Type
	 * @subpackage Template Tags
	 * @since Venue Post Type (0.1)
	 *
	 * @param int $venue_id optional
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @return string Title of Venue
	 *
	 */
	function wcpt_get_venue_title ( $venue_id = 0 ) {
		return apply_filters( 'wcpt_get_venue_title', get_the_title( $venue_id ) );
	}

/**
 * wcpt_venue_address ()
 *
 * Output the Venue Address
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 *
 * @uses wcpt_get_venue_date()
 * @param int $venue_id optional
 */
function wcpt_venue_address ( $venue_id = 0 ) {
	echo wcpt_get_venue_address( $venue_id );
}
	/**
	 * wcpt_get_venue_address ()
	 *
	 * Return the Venue Address
	 *
	 * @package Venue Post Type
	 * @subpackage Template Tags
	 * @since Venue Post Type (0.1)
	 *
	 * @return string
	 * @param int $venue_id optional
	 */
	function wcpt_get_venue_address ( $venue_id = 0 ) {
		if ( empty( $venue_id ) )
			$venue_id = wcpt_get_venue_id();

		return apply_filters( 'wcpt_get_venue_address', get_post_meta( $venue_id, 'Address', true ) );
	}

/**
 * wcpt_venue_maximum_capacity ()
 *
 * Output the Venue Capacity
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 *
 * @uses wcpt_get_venue_maximum_capacity()
 * @param int $venue_id optional
 */
function wcpt_venue_maximum_capacity ( $venue_id = 0 ) {
	echo wcpt_get_venue_maximum_capacity( $venue_id );
}
	/**
	 * wcpt_get_venue_maximum_capacity ()
	 *
	 * Return the Venue Capacity
	 *
	 * @package Venue Post Type
	 * @subpackage Template Tags
	 * @since Venue Post Type (0.1)
	 *
	 * @return string
	 * @param int $venue_id optional
	 */
	function wcpt_get_venue_maximum_capacity ( $venue_id = 0 ) {
		if ( empty( $venue_id ) )
			$venue_id = wcpt_get_venue_id();

		return apply_filters( 'wcpt_get_venue_maximum_capacity', get_post_meta( $venue_id, 'Maximum Capacity', true ) );
	}

/**
 * wcpt_venue_available_rooms ()
 *
 * Output the Venue Rooms
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 *
 * @uses wcpt_get_venue_url()
 * @param int $venue_id optional
 */
function wcpt_venue_available_rooms ( $venue_id = 0 ) {
	echo wcpt_get_venue_available_rooms( $venue_id );
}
	/**
	 * wcpt_get_venue_available_rooms ()
	 *
	 * Return the Venue Rooms
	 *
	 * @package Venue Post Type
	 * @subpackage Template Tags
	 * @since Venue Post Type (0.1)
	 *
	 * @return string
	 * @param int $venue_id optional
	 */
	function wcpt_get_venue_available_rooms ( $venue_id = 0 ) {
		if ( empty( $venue_id ) )
			$venue_id = wcpt_get_venue_id();

		return apply_filters( 'wcpt_get_venue_available_rooms', get_post_meta( $venue_id, 'Available Rooms', true ) );
	}

/**
 * wcpt_venue_available_rooms ()
 *
 * Output the Venue Website
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 *
 * @uses wcpt_get_venue_url()
 * @param int $venue_id optional
 */
function wcpt_venue_website ( $venue_id = 0 ) {
	echo wcpt_get_venue_website( $venue_id );
}
	/**
	 * wcpt_get_venue_website ()
	 *
	 * Return the Venue Website
	 *
	 * @package Venue Post Type
	 * @subpackage Template Tags
	 * @since Venue Post Type (0.1)
	 *
	 * @return string
	 * @param int $venue_id optional
	 */
	function wcpt_get_venue_website ( $venue_id = 0 ) {
		if ( empty( $venue_id ) )
			$venue_id = wcpt_get_venue_id();

		return apply_filters( 'wcpt_get_venue_website', get_post_meta( $venue_id, 'Website URL', true ) );
	}

/**
 * wcpt_venue_pagination_count ()
 *
 * Output the pagination count
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 *
 * @global WP_Query $wcv_template
 */
function wcpt_venue_pagination_count () {
	echo wcpt_get_venue_pagination_count();
}
	/**
	 * wcpt_get_venue_pagination_count ()
	 *
	 * Return the pagination count
	 *
	 * @package Venue Post Type
	 * @subpackage Template Tags
	 * @since Venue Post Type (0.1)
	 *
	 * @global WP_Query $wcv_template
	 * @return string
	 */
	function wcpt_get_venue_pagination_count () {
		global $wcv_template;

		// Set pagination values
		$start_num = intval( ( $wcv_template->paged - 1 ) * $wcv_template->posts_per_page ) + 1;
		$from_num  = wcpt_number_format( $start_num );
		$to_num    = wcpt_number_format( ( $start_num + ( $wcv_template->posts_per_page - 1 ) > $wcv_template->found_posts ) ? $wcv_template->found_posts : $start_num + ( $wcv_template->posts_per_page - 1 ) );
		$total     = wcpt_number_format( $wcv_template->found_posts );

		// Set return string
		if ( $total > 1 )
			$retstr = sprintf( __( 'Viewing %1$s to %2$s (of %3$s)', 'wcpt' ), $from_num, $to_num, $total );
		else
			$retstr = sprintf( __( 'Viewing %1$s Venue', 'wcpt' ), $total );

		// Filter and return
		return apply_filters( 'wcpt_get_venue_pagination_count', $retstr );
	}

/**
 * wcpt_venue_pagination_links ()
 *
 * Output pagination links
 *
 * @package Venue Post Type
 * @subpackage Template Tags
 * @since Venue Post Type (0.1)
 */
function wcpt_venue_pagination_links () {
	echo wcpt_get_venue_pagination_links();
}
	/**
	 * wcpt_get_venue_pagination_links ()
	 *
	 * Return pagination links
	 *
	 * @package Venue Post Type
	 * @subpackage Template Tags
	 * @since Venue Post Type (0.1)
	 *
	 * @global WP_Query $wcv_template
	 * @return string
	 */
	function wcpt_get_venue_pagination_links () {
		global $wcv_template;

		return apply_filters( 'wcpt_get_venue_pagination_links', $wcv_template->pagination_links );
	}
