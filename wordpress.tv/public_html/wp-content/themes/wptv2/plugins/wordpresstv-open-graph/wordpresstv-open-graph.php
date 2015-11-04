<?php

namespace WordPressTV\OpenGraph;

/*
 * Register hook callbacks
 */
add_filter( 'jetpack_open_graph_tags', __NAMESPACE__ . '\customize_open_graph_tags', 11 ); // after wpcom_twitter_cards_tags

/**
 * Customize each post's Open Graph tags for WPTV
 */
function customize_open_graph_tags( $og_tags ) {
	global $post;

	if ( ! is_a( $post, 'WP_Post' ) ) {
		return $og_tags;
	}

	// Grab the ID of the embedded video, if one is present
	$video_id = array_keys( find_all_videopress_shortcodes( $post->post_content ) );

	if ( ! $video_id ) {
		return $og_tags;
	}

	// Make WPTV videos embeddable in Twitter player cards
	$video_info = video_get_info_by_guid( $video_id[0] );
	list( $width, $height ) = wp_expand_dimensions( $video_info->width, $video_info->height, 560, 315 );
	$mp4_url = set_url_scheme( wp_get_attachment_url( $video_info->post_id ), 'https' );

	$og_tags['twitter:card']                       = 'player';
	$og_tags['twitter:player']                     = sprintf( 'https://videopress.com/v/%s?autoplay=0', $video_id[0] );
	$og_tags['twitter:player:width']               = $width;
	$og_tags['twitter:player:height']              = $height;
	$og_tags['twitter:player:stream']              = $mp4_url;
	$og_tags['twitter:player:stream:content_type'] = 'video/mp4';

	/*
	 * Embed MP4 videos in Facebook posts instead of the Flash player
	 *
	 * @todo: This can be removed once https://github.com/Automattic/jetpack/issues/2950 is fixed
	 */
	$og_tags['og:video:type']                      = 'video/mp4';
	$og_tags['og:video']                           = $mp4_url;
	$og_tags['og:video:secure_url']                = $mp4_url;

	return $og_tags;
}
