<?php
/**
 * Plugin Name: WordPress.org Jetpack social metadata
 */

function wporg_default_og_image() {
	return 'https://s.w.org/images/backgrounds/wordpress-bg-medblue.png';
}
add_filter( 'jetpack_open_graph_image_default', 'wporg_default_og_image' );

/**
 * To prevent a cropped version of the og:image on Twitter, provide a square version.
 */
function wporg_default_twitter_image() {
	return 'https://s.w.org/images/backgrounds/wordpress-bg-medblue-square.png';
}
add_filter( 'jetpack_twitter_cards_image_default', 'wporg_default_twitter_image' );

function _wporg_replace_image_size( $url ) {
	return preg_replace( '/-\d+x\d+(\..+$)/', '$1', $url, 1 );
}

/**
 * Jetpack extracts image URLs like they are used in the content which leads
 * to blurry previews if they are too small.
 * This removes the size part of the image URL so the full URL is used.
 */
function wporg_fix_image_urls( $tags ) {
	$image_tags = [ 'og:image', 'og:image:secure_url', 'twitter:image' ];

	foreach ( $image_tags as $image_tag ) {
		if ( isset( $tags[ $image_tag ] ) ) {
				if ( is_string( $tags[ $image_tag ] ) ) {
					$tags[ $image_tag ] = _wporg_replace_image_size( $tags[ $image_tag ] );
				} elseif ( is_array( $tags[ $image_tag ] ) ) {
					$tags[ $image_tag ] = array_map( '_wporg_replace_image_size', $tags[ $image_tag ] );
				}
			}
	}

	return $tags;
}
add_filter( 'jetpack_open_graph_tags', 'wporg_fix_image_urls', 20 );

/**
 * Customize the Twitter username used as "twitter:site" Twitter Card Meta Tag.
 *
 * This username will also be appended to tweets launched by the tweet button.
 *
 * @param string $handle Twitter Username.
 */
function jetpack_twitter_cards_site_tag_wordpress( $handle ) {
	return $handle ?: 'WordPress';
}
add_filter( 'jetpack_twitter_cards_site_tag', 'jetpack_twitter_cards_site_tag_wordpress' );
