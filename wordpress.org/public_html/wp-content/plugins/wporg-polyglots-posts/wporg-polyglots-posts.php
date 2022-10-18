<?php
namespace WordPressdotorg\Plugin\Polyglots_Posts;

/**
 * Plugin Name: WordPress.org Polyglots Posts.
 * Description: Filters the posts by their tags, excluding the posts with the
 * 'editor-requests', 'request', 'clpte', 'locale-requests' tags in the
 * https://make.wordpress.org/polyglots/?exclude=requests URL.
 * License: GPLv2 or later
 */

/**
 * The ids of the tags to be excluded.
 * 'editor-requests', 'request', 'clpte', 'locale-requests' id tags.
 *
 * @var array
 */
const TAG_IDS = array( 1453, 307, 6039, 1999 );

/**
 * The URL where this plugin will work.
 *
 * @var string
 */
const URL = 'https://make.wordpress.org/polyglots/?exclude=requests';

/**
 * Excludes the tags from the main query.
 *
 * @param $query
 *
 * @return void
 */
function pre_get_posts( $query ) {
	global $wp;

	if ( URL != home_url( add_query_arg( $_GET, $wp->request ) ) ) {
		return;
	}

	if ( $query->is_main_query() ) {
		$query->set( 'tag__not_in', TAG_IDS );
	}
}

add_filter( 'pre_get_posts', __NAMESPACE__ . '\\pre_get_posts' );
