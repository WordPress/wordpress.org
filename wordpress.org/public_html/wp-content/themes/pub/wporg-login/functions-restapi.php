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

	register_rest_route( 'wporg/v1', '/resend-confirmation-email/?', array(
		'methods'  => WP_REST_Server::EDITABLE,
		'callback' => 'wporg_login_rest_resend_confirmation_email'
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
			'error' => __( 'That username is already in use.', 'wporg' ) . '<br>' .
				__( 'Is it yours? <a href="/lostpassword">Reset your password</a>.', 'wporg' ),
			'avatar' => get_avatar( $user, 64 ),
		];
	}

	// Check we don't have a pending registration for that username.
	if ( $pending = wporg_get_pending_user( $login ) ) {
		return [
			'available' => false,
			'error' => __( 'That username is already in use.', 'wporg' ) . '<br>' .
				__( 'The registration is still pending, please check your email for the confirmation link.', 'wporg' ) . '<br>' .
				'<a href="#" class="resend">' . __( 'Resend confirmation email.', 'wporg' ) . '</a>',
			'avatar' => get_avatar( $pending->user_email, 64 ),
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
			'error' => __( 'That email address already has an account.', 'wporg' ) . '<br>' .
				__( 'Is it yours? <a href="/lostpassword">Reset your password</a>.', 'wporg' ),
			'avatar' => get_avatar( $user, 64 ),
		];
	}

	// Check we don't have a pending registration for that email.
	if ( $pending = wporg_get_pending_user( $email ) ) {
		return [
			'available' => false,
			'error' => __( 'That email address already has an account.', 'wporg' ) . '<br>' .
				__( 'The registration is still pending, please check your email for the confirmation link.', 'wporg' ) . '<br>' .
				'<a href="#" class="resend">' . __( 'Resend confirmation email.', 'wporg' ) . '</a>',
			'avatar' => get_avatar( $email, 64 ),
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

/*
 * Resend a confirmation email to create an account.
 * 
 * This API intentionally doesn't report if it performs the action, always returning the success message.
 */
function wporg_login_rest_resend_confirmation_email( $request ) {
	$account = $request['account'];

	$success_message = sprintf(
		__( 'Please check your email %s for a confirmation link to set your password.', 'wporg' ),
		'<code>' . esc_html( $account ) . '</code>'
	);

	$pending_user = wporg_get_pending_user( $request['account'] );
	if ( ! $pending_user || $pending_user['created'] ) {
		return $success_message;
	}

	// Allow for w.org plugins to block the action.
	if ( null !== ( $pre_register_error = apply_filters( 'wporg_login_pre_registration', null, $pending_user['user_login'], $pending_user['user_email'], $pending_user['meta']['user_mailinglist'] ) ) ) {
		return $success_message;
	}

	// Only one email per..
	// - 1 minute for brand new accounts (<15min)
	// - 5 minutes for new accounts (<1hr)
	// - 3 hours there after
	list( $requested_time, ) = explode( ':', $pending_user['user_activation_key'] );
	$time_limit = 3 * HOUR_IN_SECONDS;

	if ( time() - strtotime( $pending_user['user_registered'] ) < HOUR_IN_SECONDS ) {
		$time_limit = 5 * MINUTE_IN_SECONDS;
	}

	if ( time() - strtotime( $pending_user['user_registered'] ) < 15 * MINUTE_IN_SECONDS ) {
		$time_limit = MINUTE_IN_SECONDS;
	}

	if ( ( time() - $requested_time ) < $time_limit ) {
		return $success_message;
	}

	wporg_login_send_confirmation_email( $pending_user );

	return $success_message;
}