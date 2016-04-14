<?php

namespace WordCamp\Jetpack_Tweaks;
defined( 'WPINC' ) or die();

add_filter( 'jetpack_open_graph_image_default', __NAMESPACE__ . '\default_og_image'               );
add_filter( 'jetpack_images_get_images',        __NAMESPACE__ . '\default_single_og_image', 10, 3 );
add_filter( 'jetpack_open_graph_tags',          __NAMESPACE__ . '\add_og_twitter_summary'         );
add_filter( 'jetpack_twitter_cards_site_tag',   __NAMESPACE__ . '\twitter_sitetag'                );

/*
 * Open Graph Default Image.
 *
 * Provides a default image for sharing WordCamp home/pages to Facebook/Twitter/Google other than the Jetpack "blank" image.
 */
function default_og_image() {
	return 'https://s.w.org/images/backgrounds/wordpress-bg-medblue.png';
}

/**
 * Choose the default Open Graph image for single posts
 *
 * @param array $media
 * @param int   $post_id
 * @param array $args
 *
 * @return array
 */
function default_single_og_image( $media, $post_id, $args ) {
	if ( $media ) {
		return $media;
	}

	if ( has_site_icon() ) {
		$image_url = get_site_icon_url();
	} else if ( has_header_image() ) {
		$image_url = get_header_image();
	} else {
		$image_url = default_og_image();
	}

	return array( array(
		'type' => 'image',
		'from' => 'custom_fallback',
		'src'  => esc_url( $image_url ),
		'href' => get_permalink( $post_id ),
	) );
}

/*
 * Add Twitter Card type.
 *
 * Added the twitter:card = summary OG tag for the home page and other ! is_singular() pages, which is not added by default by Jetpack.
 */
function add_og_twitter_summary( $og_tags ) {
	if ( is_home() || is_front_page() ) {
		$og_tags['twitter:card'] = 'summary';
	}

	return $og_tags;
}

/*
 * User @WordCamp as the default Twitter account.
 *
 * Add default Twitter account as @WordCamp for when individual WCs do not set their Settings->Sharing option for Twitter cards only.
 * Sets the "via" tag to blank to avoid slamming @WordCamp moderators with a ton of shared posts.
 */
function twitter_sitetag( $site_tag ) {
	if ( 'jetpack' == $site_tag ) {
		$site_tag = 'WordCamp';
		add_filter( 'jetpack_sharing_twitter_via', '__return_empty_string' );
	}

	return $site_tag;
}
