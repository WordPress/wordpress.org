<?php

namespace WordCamp\Privacy;

defined( 'WPINC' ) || die();

add_filter( 'privacy_policy_url', __NAMESPACE__ . '\set_privacy_policy_url', 10 );

/**
 * Set a consistent Privacy Policy across all sites.
 *
 * @param string $url
 *
 * @return string
 */
function set_privacy_policy_url( $url ) {
	return 'https://wordpress.org/about/privacy/';
}
