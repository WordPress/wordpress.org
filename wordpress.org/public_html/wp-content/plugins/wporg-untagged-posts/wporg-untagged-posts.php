<?php
namespace WordPressdotorg\Plugin\Untagged_Posts_Feed;
/**
 * Plugin Name: WordPress.org Untagged posts feed.
 * Description: Adds a /feed/untagged/ RSS2 posts field.
 * Plugin URI: https://meta.trac.wordpress.org/ticket/5750
 */

const FEED = 'untagged';

/**
 * Register the feed.
 */
function init() {
	add_feed( FEED, __NAMESPACE__ . '\\feed' );
}
add_action( 'init', __NAMESPACE__ . '\\init' );

/**
 * Load the RSS feed template.
 */
function feed() {
	load_template( ABSPATH . WPINC . '/feed-rss2.php' );
}

/**
 * Flush the rewrite rules upon plugin activation.
 */
function activation() {
	init();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\\activation' );

/**
 * Alter the query for the feed to only have untagged posts.
 */
function pre_get_posts( $query ) {
	if ( ! $query->is_feed( FEED ) ) {
		return;
	}

	// Reload the query ignoring all other query inputs.
	$query->parse_query( [
		'feed'        => FEED,
		'post_type'   => 'post',
		'tag__not_in' => get_terms(
			'post_tag',
	 		[
				 'fields' => 'ids'
			]
		)
	] );
}
add_filter( 'pre_get_posts', __NAMESPACE__ . '\\pre_get_posts' );

/**
 * Prepend feed name in feed's title tag
 */
function prepend_rss_title( $title, $deprecated ) {
	$title = ucwords( FEED ) . " &#8211; " . $title;
	return $title;
}
add_filter( 'wp_title_rss', __NAMESPACE__ . '\\prepend_rss_title', 10, 2 );
