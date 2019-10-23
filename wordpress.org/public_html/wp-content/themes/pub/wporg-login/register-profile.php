<?php
/**
 * An old registration flow template, just redirects to a expired link template now.
 *
 * @package wporg-login
 */

$profile_user = isset( WP_WPOrg_SSO::$matched_route_params['profile_user'] ) ? WP_WPOrg_SSO::$matched_route_params['profile_user'] : false;

wp_safe_redirect( home_url( '/linkexpired/lostpassword/' . urlencode( $profile_user ) ) );
exit;
