<?php

/*
Plugin Name: WP15 - Miscellaneous
Description: Miscellaneous functionality for WP15
Version:     0.1
Author:      WordPress Meta Team
Author URI:  https://make.wordpress.org/meta
*/

namespace WP15\Updates;
use DateTime;

defined( 'WPINC' ) or die();

add_filter( 'tggr_end_date', __NAMESPACE__ . '\set_tagregator_cutoff_date' );

/**
 * Tell Tagregator when to stop fetching new items.
 *
 * The #wp15 hashtag will collect spam, etc, after the event is over, and we want to
 * avoid publishing those.
 *
 * @param DateTime|null $date
 *
 * @return DateTime
 */
function set_tagregator_cutoff_date( $date ) {
	// A few weeks after the event ends, so that wrap-up posts, etc are included.
	return new DateTime( 'June 15, 2018' );
}
