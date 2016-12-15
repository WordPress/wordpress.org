<?php

function wporg_login_rest_routes() {
	register_rest_route( 'wporg/v1', '/username-available/(?P<login>.*)', array(
		'methods'  => WP_REST_Server::READABLE,
		'callback' => 'wporg_login_rest_username_exists'
	) );
	register_rest_route( 'wporg/v1', '/username-available/?', array(
		'methods'  => WP_REST_Server::READABLE,
		'callback' => 'wporg_login_rest_username_exists'
	) );

	register_rest_route( 'wporg/v1', '/email-in-use/(?P<email>.*)', array(
		'methods'  => WP_REST_Server::READABLE,
		'callback' => 'wporg_login_rest_email_in_use'
	) );
	register_rest_route( 'wporg/v1', '/email-in-use/?', array(
		'methods'  => WP_REST_Server::READABLE,
		'callback' => 'wporg_login_rest_email_in_use'
	) );
}
add_action( 'rest_api_init', 'wporg_login_rest_routes' );

function wporg_login_rest_username_exists( $request ) {
	$login = trim( $request['login'] );

	$validate_signup = wpmu_validate_user_signup( $login, 'placeholder@placeholder.domain' );

	// We're going to enforce that you can't have a user_login which matches another users user_nicename.. just because sanity.
	if ( ($user = get_user_by( 'login', $login )) || ($user = get_user_by( 'slug', $login )) ) {
		return [
			'available' => false,
			'error' => __( 'That username is already in use.', 'wporg-login' ) . '<br>' . __( 'Is it yours? <a href="/lostpassword">Reset your password</a>.', 'wporg-plugins' ),
			'avatar' => get_avatar( $user, 64 ),
		];
	}

	// Perform general validations.
	$validate_signup_error = $validate_signup['errors']->get_error_message( 'user_name' );

	if ( $validate_signup_error ) {
		return [
			'available' => false,
			'error' => $validate_signup_error,
			'avatar' => false,
		];
	}

	return [ 'available' => true ];
}

function wporg_login_rest_email_in_use( $request ) {
	$email = trim( $request['email'] );

	if ( $user = get_user_by( 'email', $email ) ) {
		return [
			'available' => false,
			'error' => __( 'That email address already has an account.', 'wporg-login' ) . '<br>' . __( 'Is it yours? <a href="/lostpassword">Reset your password</a>.', 'wporg-plugins' ),
			'avatar' => get_avatar( $user, 64 ),
		];
	}

	$validate_signup = wpmu_validate_user_signup( '', $email );
	$validate_signup_error = $validate_signup['errors']->get_error_message( 'user_email' );
	if ( $validate_signup_error ) {
		return [
			'available' => false,
			'error' => $validate_signup_error,
			'avatar' => false,
		];
	}

	return [ 'available' => true ];
}