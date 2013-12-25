<?php

/**
 * wcpt_has_organizers()
 *
 * The main Organizer loop. WordPress makes this easy for us.
 *
 * @package Organizer Post Type
 * @subpackage Template Tags
 * @since Organizer Post Type (0.1)
 *
 * @global WP_Query $wcpt_template
 * @param array $args Possible arguments to change returned Organizers
 * @return object Multidimensional array of Organizer information
 */
function wcpt_has_organizers ( $args = '' ) {
	global $wcpt_template;

	$default = array (
		// Narrow query down to Organizer Post Type
		'post_type'        => WCO_POST_TYPE_ID,

		// No hierarchy
		'post_parent'      => '0',

		// 'author', 'date', 'title', 'modified', 'parent', rand',
		'orderby'          => isset( $_REQUEST['orderby'] ) ? $_REQUEST['orderby'] : 'date',

		// 'ASC', 'DESC'
		'order'            => isset( $_REQUEST['order'] ) ? $_REQUEST['order'] : 'DESC',

		// Default is 15
		'posts_per_page'   => isset( $_REQUEST['posts'] ) ? $_REQUEST['posts'] : 15,

		// Page Number
		'paged'            => isset( $_REQUEST['wcpage'] ) ? $_REQUEST['wcpage'] : 1,

		// Topic Search
		's'                => empty( $_REQUEST['wcs'] ) ? '' : $_REQUEST['wcs'],
	);

	// Set up variables
	$wcpt_q = wp_parse_args( $args, $default );
	$r      = extract( $wcpt_q );

	// Call the query
	$wcpt_template = new WP_Query( $wcpt_q );

	// Add pagination values to query object
	$wcpt_template->posts_per_page = $posts_per_page;
	$wcpt_template->paged          = $paged;

	// Only add pagination if query returned results
	if ( (int)$wcpt_template->found_posts && (int)$wcpt_template->posts_per_page ) {

		// Pagination settings with filter
		$wcpt_pagination = apply_filters( 'wcpt_pagination', array (
			'base'      => add_query_arg( 'wcpage', '%#%' ),
			'format'    => '',
			'total'     => ceil( (int)$wcpt_template->found_posts / (int)$posts_per_page ),
			'current'   => (int)$wcpt_template->paged,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'mid_size'  => 1
		) );

		// Add pagination to query object
		$wcpt_template->pagination_links = paginate_links ( $wcpt_pagination );
	}

	return apply_filters( 'wcpt_has_organizers', $wcpt_template->have_posts(), $wcpt_template );
}

/**
 * wcpt_organizers()
 *
 * Whether there are more Organizers available in the loop
 *
 * @package Organizer Post Type
 * @subpackage Template Tags
 * @since Organizer Post Type (0.1)
 *
 * @global WP_Query $wcpt_template
 * @return object Organizer information
 */
function wcpt_organizers () {
	global $wcpt_template;
	return $wcpt_template->have_posts();
}

/**
 * wcpt_the_organizer()
 *
 * Loads up the current Organizer in the loop
 *
 * @package Organizer Post Type
 * @subpackage Template Tags
 * @since Organizer Post Type (0.1)
 *
 * @global WP_Query $wcpt_template
 * @return object Organizer information
 */
function wcpt_the_organizer () {
	global $wcpt_template;
	return $wcpt_template->the_post();
}

/**
 * wcpt_organizer_id()
 *
 * Echo id from wcpt_organizer_id()
 *
 * @package Organizer Post Type
 * @subpackage Template Tags
 * @since Organizer Post Type (0.1)
 *
 * @uses wcpt_get_organizer_id()
 */
function wcpt_organizer_id () {
	echo wcpt_get_organizer_id();
}
	/**
	 * wcpt_get_organizer_id()
	 *
	 * Get the id of the user in a Organizer loop
	 *
	 * @package Organizer Post Type
	 * @subpackage Template Tags
	 * @since Organizer Post Type (0.1)
	 *
	 * @return string Organizer id
	 */
	function wcpt_get_organizer_id () {
		global $wcpt_template;

		if ( isset( $wcpt_template->post ) )
			$organizer_id = $wcpt_template->post->ID;
		else
			$organizer_id = get_the_ID();

		return apply_filters( 'wcpt_get_organizer_id', $organizer_id );
	}

/**
 * wcpt_organizer_permalink ()
 *
 * Output the link to the Organizer in the Organizer loop
 *
 * @package Organizer Post Type
 * @subpackage Template Tags
 * @since Organizer Post Type (0.1)
 *
 * @param int $organizer_id optional
 * @uses wcpt_get_organizer_permalink()
 */
function wcpt_organizer_permalink ( $organizer_id = 0 ) {
	echo wcpt_get_organizer_permalink( $organizer_id );
}
	/**
	 * wcpt_get_organizer_permalink()
	 *
	 * Return the link to the Organizer in the loop
	 *
	 * @package Organizer Post Type
	 * @subpackage Template Tags
	 * @since Organizer Post Type (0.1)
	 *
	 * @param int $organizer_id optional
	 * @uses apply_filters
	 * @uses get_permalink
	 * @return string Permanent link to Organizer
	 */
	function wcpt_get_organizer_permalink ( $organizer_id = 0 ) {
		if ( empty( $organizer_id ) )
			$organizer_id = wcpt_get_organizer_id();

		return apply_filters( 'wcpt_get_organizer_permalink', get_permalink( $organizer_id ) );
	}

/**
 * wcpt_organizer_title ()
 *
 * Output the title of the Organizer in the loop
 *
 * @package Organizer Post Type
 * @subpackage Template Tags
 * @since Organizer Post Type (0.1)
 *
 * @param int $organizer_id optional
 * @uses wcpt_get_organizer_title()
 */
function wcpt_organizer_title ( $organizer_id = 0 ) {
	echo wcpt_get_organizer_title( $organizer_id );
}
	/**
	 * wcpt_get_organizer_title ()
	 *
	 * Return the title of the Organizer in the loop
	 *
	 * @package Organizer Post Type
	 * @subpackage Template Tags
	 * @since Organizer Post Type (0.1)
	 *
	 * @param int $organizer_id optional
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @return string Title of Organizer
	 *
	 */
	function wcpt_get_organizer_title ( $organizer_id = 0 ) {
		return apply_filters( 'wcpt_get_organizer_title', get_the_title( $organizer_id ) );
	}

/**
 * wcpt_organizer_email ()
 *
 * Output the Organizers Email Address
 *
 * @package Organizer Post Type
 * @subpackage Template Tags
 * @since Organizer Post Type (0.1)
 *
 * @uses wcpt_get_organizer_date()
 * @param int $organizer_id optional
 */
function wcpt_organizer_email ( $organizer_id = 0 ) {
	echo wcpt_get_organizer_email( $organizer_id );
}
	/**
	 * wcpt_get_organizer_email ()
	 *
	 * Return the Organizers Email Address
	 *
	 * @package Organizer Post Type
	 * @subpackage Template Tags
	 * @since Organizer Post Type (0.1)
	 *
	 * @return string
	 * @param int $organizer_id optional
	 */
	function wcpt_get_organizer_email ( $organizer_id = 0 ) {
		if ( empty( $organizer_id ) )
			$organizer_id = wcpt_get_organizer_id();

		return apply_filters( 'wcpt_get_organizer_email', get_post_meta( $organizer_id, 'Email Address', true ) );
	}

/**
 * wcpt_organizer_mailing ()
 *
 * Output the Organizers Mailing Address
 *
 * @package Organizer Post Type
 * @subpackage Template Tags
 * @since Organizer Post Type (0.1)
 *
 * @uses wcpt_get_organizer_location()
 * @param int $organizer_id optional
 */
function wcpt_organizer_mailing ( $organizer_id = 0 ) {
	echo wcpt_get_organizer_mailing( $organizer_id );
}
	/**
	 * wcpt_get_organizer_mailing ()
	 *
	 * Return the Organizers Mailing Address
	 *
	 * @package Organizer Post Type
	 * @subpackage Template Tags
	 * @since Organizer Post Type (0.1)
	 *
	 * @return string
	 * @param int $organizer_id optional
	 */
	function wcpt_get_organizer_mailing ( $organizer_id = 0 ) {
		if ( empty( $organizer_id ) )
			$organizer_id = wcpt_get_organizer_id();

		return apply_filters( 'wcpt_get_organizer_mailing', get_post_meta( $organizer_id, 'Mailing Address', true ) );
	}

/**
 * wcpt_organizer_telephone ()
 *
 * Output the Organizers Telephone
 *
 * @package Organizer Post Type
 * @subpackage Template Tags
 * @since Organizer Post Type (0.1)
 *
 * @uses wcpt_get_organizer_url()
 * @param int $organizer_id optional
 */
function wcpt_organizer_telephone ( $organizer_id = 0 ) {
	echo wcpt_get_organizer_telephone( $organizer_id );
}
	/**
	 * wcpt_get_organizer_telephone ()
	 *
	 * Return the Organizers Telephone
	 *
	 * @package Organizer Post Type
	 * @subpackage Template Tags
	 * @since Organizer Post Type (0.1)
	 *
	 * @return string
	 * @param int $organizer_id optional
	 */
	function wcpt_get_organizer_telephone ( $organizer_id = 0 ) {
		if ( empty( $organizer_id ) )
			$organizer_id = wcpt_get_organizer_id();

		return apply_filters( 'wcpt_get_organizer_telephone', get_post_meta( $organizer_id, 'Telephone', true ) );
	}

/**
 * wcpt_organizer_pagination_count ()
 *
 * Output the pagination count
 *
 * @package Organizer Post Type
 * @subpackage Template Tags
 * @since Organizer Post Type (0.1)
 *
 * @global WP_Query $wcpt_template
 */
function wcpt_organizer_pagination_count () {
	echo wcpt_get_organizer_pagination_count();
}
	/**
	 * wcpt_get_organizer_pagination_count ()
	 *
	 * Return the pagination count
	 *
	 * @package Organizer Post Type
	 * @subpackage Template Tags
	 * @since Organizer Post Type (0.1)
	 *
	 * @global WP_Query $wcpt_template
	 * @return string
	 */
	function wcpt_get_organizer_pagination_count () {
		global $wcpt_template;

		// Set pagination values
		$start_num = intval( ( $wcpt_template->paged - 1 ) * $wcpt_template->posts_per_page ) + 1;
		$from_num  = wcpt_number_format( $start_num );
		$to_num    = wcpt_number_format( ( $start_num + ( $wcpt_template->posts_per_page - 1 ) > $wcpt_template->found_posts ) ? $wcpt_template->found_posts : $start_num + ( $wcpt_template->posts_per_page - 1 ) );
		$total     = wcpt_number_format( $wcpt_template->found_posts );

		// Set return string
		if ( $total > 1 )
			$retstr = sprintf( __( 'Viewing %1$s to %2$s (of %3$s)', 'wcpt' ), $from_num, $to_num, $total );
		else
			$retstr = sprintf( __( 'Viewing %1$s Organizer', 'wcpt' ), $total );

		// Filter and return
		return apply_filters( 'wcpt_get_organizer_pagination_count', $retstr );
	}

/**
 * wcpt_organizer_pagination_links ()
 *
 * Output pagination links
 *
 * @package Organizer Post Type
 * @subpackage Template Tags
 * @since Organizer Post Type (0.1)
 */
function wcpt_organizer_pagination_links () {
	echo wcpt_get_organizer_pagination_links();
}
	/**
	 * wcpt_get_organizer_pagination_links ()
	 *
	 * Return pagination links
	 *
	 * @package Organizer Post Type
	 * @subpackage Template Tags
	 * @since Organizer Post Type (0.1)
	 *
	 * @global WP_Query $wcpt_template
	 * @return string
	 */
	function wcpt_get_organizer_pagination_links () {
		global $wcpt_template;

		return apply_filters( 'wcpt_get_organizer_pagination_links', $wcpt_template->pagination_links );
	}
