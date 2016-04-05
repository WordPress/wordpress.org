<?php

namespace WordPress_Community\Applications;
defined( 'WPINC' ) or die();

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets', 11 );

/**
 * Enqueue scripts and stylesheets
 */
function enqueue_assets() {
	global $post;

	wp_register_style(
		'wp-community-applications',
		plugins_url( 'css/applications/common.css', __DIR__ ),
		array(),
		1
	);

	if ( isset( $post->post_content ) && has_shortcode( $post->post_content, WordCamp\SHORTCODE_SLUG ) ) {
		wp_enqueue_style(  'wp-community-applications' );
	}
}
