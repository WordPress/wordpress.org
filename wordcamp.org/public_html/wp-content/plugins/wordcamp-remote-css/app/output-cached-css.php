<?php

namespace WordCamp\RemoteCSS;

defined( 'WPINC' ) or die();

add_action( 'wp_enqueue_scripts',           __NAMESPACE__ . '\enqueue_cached_css', 11 );  // after the theme's stylesheet, but before before Jetpack Custom CSS's stylesheet
add_action( 'wp_ajax_'        . CSS_HANDLE, __NAMESPACE__ . '\output_cached_css'      );
add_action( 'wp_ajax_nopriv_' . CSS_HANDLE, __NAMESPACE__ . '\output_cached_css'      );
add_filter( 'nocache_headers',              __NAMESPACE__ . '\set_cache_headers'      );

/**
 * Enqueue the cached CSS
 *
 * An AJAX endpoint is used because the CSS is stored in the database, rather than on the file system.
 */
function enqueue_cached_css() {
	if ( false === get_option( OPTION_REMOTE_CSS_URL ) ) {
		return;
	}

	$cachebuster = get_latest_revision_id();

	if ( ! $cachebuster ) {
		$cachebuster = date( 'Y-m-d' ); // We should always have a revision ID, but this will work as a fallback if we don't for some reason
	}

	wp_enqueue_style(
		CSS_HANDLE,
		add_query_arg( 'action', CSS_HANDLE, admin_url( 'admin-ajax.php' ) ),
		array(),
		$cachebuster,
		'all'
	);
}

/**
 * Get the ID of the latest revision of the safe CSS post
 *
 * @return int|bool
 */
function get_latest_revision_id() {
	$safe_css = get_safe_css_post();

	if ( ! is_a( $safe_css, 'WP_Post' ) || empty( $safe_css->post_content_filtered ) ) {
		return false;
	}
	$latest_revision = wp_get_post_revisions( $safe_css->ID, array( 'posts_per_page' => 1 ) );

	if ( empty( $latest_revision ) ) {
		return false;
	}

	$latest_revision = array_shift( $latest_revision );

	return $latest_revision->ID;
}

/**
 * Adjust the HTTP response headers so that browsers will cache the CSS we send
 *
 * Normally Core prevents caching of all AJAX requests, but we want to make sure the CSS is cached because it's
 * loaded on every front-end request.
 *
 * @param array $cache_headers
 *
 * @return array
 */
function set_cache_headers( $cache_headers ) {
	if ( ! defined( 'DOING_AJAX' ) || empty( $_GET['action'] ) || CSS_HANDLE !== $_GET['action'] ) {
		return $cache_headers;
	}

	$safe_css = get_safe_css_post();

	if ( ! is_a( $safe_css, 'WP_Post' ) ) {
		return $cache_headers;
	}

	$last_modified     = date( 'D, d M Y H:i:s', strtotime( $safe_css->post_date_gmt ) ) . ' GMT';
	$expiration_period = YEAR_IN_SECONDS;

	$cache_headers = array(
		'Cache-Control' => 'maxage=' . $expiration_period,
		'ETag'          => '"' . md5( $last_modified ) . '"',
		'Last-Modified' => $last_modified, // Currently Core always strips this out, but we want to send it, and maybe Core will allow that in the future
		'Expires'       => gmdate( 'D, d M Y H:i:s', time() + $expiration_period ) . ' GMT',
	);

	return $cache_headers;
}

/**
 * Handles the AJAX endpoint to output the local copy of the CSS
 */
function output_cached_css() {
	header( 'Content-Type: text/css; charset=' . get_option( 'blog_charset' ) );

	$safe_css = get_safe_css_post();

	if ( is_a( $safe_css, 'WP_Post' ) ) {
		echo $safe_css->post_content_filtered;
	}

	wp_die();
}
