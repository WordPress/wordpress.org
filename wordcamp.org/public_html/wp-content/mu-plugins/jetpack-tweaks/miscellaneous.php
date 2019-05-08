<?php

namespace WordCamp\Jetpack_Tweaks;
defined( 'WPINC' ) || die();

// Allow Photon to fetch images that are served via HTTPS
add_filter( 'jetpack_photon_reject_https',    '__return_false' );

/**
 * Filter the post types Jetpack has access to, and can synchronize with WordPress.com.
 *
 * @see Jetpack's WPCOM_JSON_API_ENDPOINT::_get_whitelisted_post_types();
 *
 * @param array $allowed_types Array of whitelisted post types.
 *
 * @return array Modified array of whitelisted post types.
 */
function add_post_types_to_rest_api( $allowed_types ) {
	$allowed_types += array( 'wcb_speaker', 'wcb_session', 'wcb_sponsor' );

	return $allowed_types;
}

add_filter( 'rest_api_allowed_post_types', __NAMESPACE__ . '\add_post_types_to_rest_api' );

/**
 * Prepend a unique string to contact form subjects.
 *
 * Otherwise some e-mail clients and management systems -- *cough* SupportPress *cough* -- will incorrectly group
 * separate messages into the same thread.
 *
 * It'd be better to have the key appended rather than prepended, but SupportPress won't always recognize the
 * subject as unique if we do that :|
 *
 * @param string $subject
 *
 * @return string
 */
function grunion_unique_subject( $subject ) {
	return sprintf( '[%s] %s', wp_generate_password( 8, false ), $subject );
}
add_filter( 'contact_form_subject', __NAMESPACE__ . '\grunion_unique_subject' );

/**
 * Lower the timeout for requests to the Brute Protect API to avoid unintentional DDoS.
 *
 * The default timeout is 30 seconds, but when the API goes down, the long timeouts will occupy php-fpm threads,
 * which will stack up until there are no more available, and the site will crash.
 *
 * @link https://wordpress.slack.com/archives/G02QCEMRY/p1553203877064600
 *
 * @param int $timeout
 *
 * @return int
 */
function lower_brute_protect_api_timeout( $timeout ) {
	return 8; // seconds
}
add_filter( 'jetpack_protect_connect_timeout', __NAMESPACE__ . '\lower_brute_protect_api_timeout' );
