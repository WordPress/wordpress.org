<?php
/**
 * The main template file.
 *
 * @package wporg-login
 */

get_header();

/**
 * Test if the path we're on is one that we use, depending on if it
 * has a partial or not, or load the 404 partial as fallback.
 * 
 * Note that the path is first validated in WP_WPOrg_SSO::redirect_all_login_or_signup_to_sso().
 * @see https://meta.trac.wordpress.org/browser/sites/trunk/common/includes/wporg-sso/wp-plugin.php
 */

if ( apply_filters( 'is_valid_wporg_sso_path', false ) && preg_match( '!^(/[^/\?]*)([/\?]{1,2}.*)?$!', $_SERVER['REQUEST_URI'], $matches ) ) {
	$screen = '/' === $matches[1] ? 'login' : preg_replace( '/[^a-z0-9-]/', '', $matches[1] );
} else {
	$screen = '404';
}

$partials_dir = __DIR__ . '/partials/';
$partial      = $partials_dir . $screen . '.php';

if ( file_exists( $partial ) ) {
	if ( ! headers_sent() ) {
		status_header( 200 );
	}
	require_once( $partial );
} else {
	if ( ! headers_sent() ) {
		status_header( 404 );
	}
	require_once( $partials_dir . '404.php');
}

get_footer();
