<?php

namespace WordCamp\RemoteCSS;
use Jetpack;
use Exception;

defined( 'WPINC' ) or die();

if ( is_configured() ) {
	add_action( 'wp_enqueue_scripts',           __NAMESPACE__ . '\enqueue_cached_css', 11 );  // after the theme's stylesheet, but before Core's Custom CSS stylesheet
	add_filter( 'stylesheet_uri',               __NAMESPACE__ . '\skip_theme_stylesheet'  );
	add_action( 'wp_ajax_'        . CSS_HANDLE, __NAMESPACE__ . '\output_cached_css'      );
	add_action( 'wp_ajax_nopriv_' . CSS_HANDLE, __NAMESPACE__ . '\output_cached_css'      );
	add_filter( 'nocache_headers',              __NAMESPACE__ . '\set_cache_headers'      );
}

/**
 * Enqueue the cached CSS
 *
 * An AJAX endpoint is used because the CSS is stored in the database, rather than on the file system.
 */
function enqueue_cached_css() {
	try {
		$cachebuster = get_latest_revision_id();
	} catch ( Exception $exception ) {
		$cachebuster = date( 'Y-m-d-H' );
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
 * Skip the theme's stylesheet when in `replace` mode
 *
 * Normally Jetpack handles this (via `Jetpack_Custom_CSS_Enhancements::style_filter`), but we still need to do it
 * even if the `custom-css` module isn't active. We can't include `custom-css-4.7.php`, because it has
 * side-effects. It doesn't seem worth it to require the module to be active just for this, so instead we're just
 * duplicating the functionality.
 *
 * @param string $stylesheet_url
 *
 * @return string
 */
function skip_theme_stylesheet( $stylesheet_url ) {
	if ( ! is_admin() && 'replace' === get_output_mode() && ! Jetpack::is_module_active( 'custom-css' ) ) {
		$stylesheet_url = plugins_url( 'custom-css/custom-css/css/blank.css', Jetpack::get_module_path( 'custom-css' ) );
	}

	return $stylesheet_url;
}

/**
 * Get the ID of the latest revision of the safe CSS post
 *
 * @return int
 */
function get_latest_revision_id() {
	$safe_css = get_safe_css_post();
	$latest_revision = wp_get_post_revisions( $safe_css->ID, array( 'posts_per_page' => 1 ) );

	if ( empty( $latest_revision ) ) {
		$latest_revision = $safe_css;
	} else {
		$latest_revision = array_shift( $latest_revision );
	}

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

	try {
		$safe_css = get_safe_css_post();
	} catch ( Exception $exception ) {
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
	// Explicitly tell the browser that this is CSS, to avoid MIME sniffing vulnerabilities
	header( 'Content-Type: text/css; charset=' . get_option( 'blog_charset' ) );

	try {
		$safe_css_post = get_safe_css_post();
		$safe_css      = $safe_css_post->post_content;
	} catch ( Exception $exception ) {
		$safe_css = '';
	}

	echo $safe_css;

	wp_die();
}
