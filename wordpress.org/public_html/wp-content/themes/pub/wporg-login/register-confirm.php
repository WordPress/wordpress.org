<?php
/**
 * An old registration flow template, just redirects to a expired link template now.
 *
 * @package wporg-login
 */

$confirm_user = isset( WP_WPOrg_SSO::$matched_route_params['confirm_user'] ) ? WP_WPOrg_SSO::$matched_route_params['confirm_user'] : false;

wp_safe_redirect( home_url( '/linkexpired/lostpassword/' . urlencode( $confirm_user ) ) );
exit;
