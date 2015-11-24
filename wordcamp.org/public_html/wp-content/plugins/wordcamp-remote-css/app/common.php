<?php

namespace WordCamp\RemoteCSS;

defined( 'WPINC' ) or die();

const SAFE_CSS_POST_SLUG    = 'wcrcss_safe_cached_version';
const OPTION_LAST_UPDATE    = 'wcrcss_last_update';
const AJAX_ACTION           = 'wcrcss_webhook';
const SYNCHRONIZE_ACTION    = 'wcrcss_synchronize';
const WEBHOOK_RATE_LIMIT    = 30; // seconds
const OPTION_REMOTE_CSS_URL = 'wcrcss_remote_css_url';
const CSS_HANDLE            = 'wordcamp_remote_css';
const GITHUB_API_HOSTNAME   = 'api.github.com';

/**
 * Find the ID of the post we use to store the safe CSS
 *
 * @return int|\WP_Error
 */
function get_safe_css_post_id() {
	$post    = get_safe_css_post();
	$post_id = is_a( $post, 'WP_Post' ) ? $post->ID : $post;

	return $post_id;
}

/**
 * Find the post we use to store the safe CSS
 *
 * @return \WP_Post|\WP_Error
 */
function get_safe_css_post() {
	$safe_css_post = get_posts( array(
		'posts_per_page' => 1,
		'post_type'      => 'safecss',
		'post_status'    => 'private',
		'post_name'      => SAFE_CSS_POST_SLUG,
	) );

	if ( $safe_css_post ) {
		$post = $safe_css_post[0];
	} else {
		$post = wp_insert_post( array(
			'post_type'   => 'safecss',
			'post_status' => 'private', // Jetpack_Custom_CSS::post_id() only searches for `public` posts, so this prevents Jetpack from fetching our post
			'post_title'  => SAFE_CSS_POST_SLUG,
			'post_name'   => SAFE_CSS_POST_SLUG,
		), true );

		if ( ! is_wp_error( $post ) ) {
			$post = get_post( $post );
		}
	}

	return $post;
}
