<?php

/**
 * wcb_sponsor_query()
 *
 * Creates and fetches the sponsor query.
 *
 * @return WP_Query The sponsor query.
 */
function wcb_sponsor_query( $args=array() ) {
	global $wcb_sponsor_query;

	if ( empty( $args ) && isset( $wcb_sponsor_query ) )
		return $wcb_sponsor_query;

	$defaults = array(
		'post_type'         => WCB_SPONSOR_POST_TYPE,
		'order'             => 'ASC',
		'posts_per_page'    => -1,
	);
	$args = wp_parse_args( $args, $defaults );

	$wcb_sponsor_query = new WP_Query( $args );

	return $wcb_sponsor_query;
}

/**
 * wcb_have_sponsors()
 *
 * Whether there are more sponsors available in the loop.
 *
 * @return object WordCamp information
 */
function wcb_have_sponsors() {
	$query = wcb_sponsor_query();
	return $query->have_posts();
}

/**
 * wcb_rewind_sponsors()
 *
 * Rewind the sponsors loop.
 */
function wcb_rewind_sponsors() {
	$query = wcb_sponsor_query();
	return $query->rewind_posts();
}

/**
 * wcb_the_sponsor()
 *
 * Loads up the current sponsor in the loop.
 *
 * @return object WordCamp information
 */
function wcb_the_sponsor() {
	$query = wcb_sponsor_query();
	return $query->the_post();
}

/**
 * wcb_sponsor_level_class()
 *
 * Prints the sponsor level class attribute.
 */
function wcb_sponsor_level_class( $term, $classes='' ) {
	echo ' class="' . esc_attr( wcb_get_sponsor_level_class( $term, $classes ) ) . '" ';
}
	/**
	 * wcb_get_sponsor_level_class()
	 *
	 * Returns the sponsor level classes.
	 *
	 * @return string Sponsor level classes.
	 */
	function wcb_get_sponsor_level_class( $term, $classes='' ) {
		return "sponsor-level $term->slug $classes";
	}

?>