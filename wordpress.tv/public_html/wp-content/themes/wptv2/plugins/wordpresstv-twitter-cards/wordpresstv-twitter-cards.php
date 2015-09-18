<?php

namespace WordPressTV\TwitterCards;

/*
 * Register hook callbacks
 */
add_filter( 'jetpack_open_graph_tags', __NAMESPACE__ . '\add_player_tags', 11 ); // after wpcom_twitter_cards_tags

/**
 * Add tags that are necessary for videos to be embedded into tweets
 */
function add_player_tags( $og_tags ) {
	global $post;

	if ( ! is_a( $post, 'WP_Post' ) ) {
		return $og_tags;
	}

	// Grab the ID of the embedded video, if one is present
	$video_id = array_keys( find_all_videopress_shortcodes( $post->post_content ) );

	if ( ! $video_id ) {
		return $og_tags;
	}

	// Add the tags necessary for the player
	$video_info = video_get_info_by_guid( $video_id[0] );
	list( $width, $height ) = wp_expand_dimensions( $video_info->width, $video_info->height, 560, 315 );

	$og_tags['twitter:card']                       = 'player';
	$og_tags['twitter:player']                     = sprintf( 'https://videopress.com/v/%s?autoplay=0', $video_id[0] );
	$og_tags['twitter:player:width']               = $width;
	$og_tags['twitter:player:height']              = $height;
	$og_tags['twitter:player:stream']              = wp_get_attachment_url( $video_info->post_id );
	$og_tags['twitter:player:stream:content_type'] = 'video/mp4';

	return $og_tags;
}
