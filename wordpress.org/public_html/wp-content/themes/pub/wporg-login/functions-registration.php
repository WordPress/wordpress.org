<?php

function wporg_login_check_recapcha_status() {
	if ( empty( $_POST['g-recaptcha-response'] ) ) {
		return false;
	}

	$verify = array(
		'secret'   => RECAPTCHA_PRIVKEY,
		'remoteip' => $_SERVER['REMOTE_ADDR'],
		'response' => $_POST['g-recaptcha-response'],
	);

	$resp = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array( 'body' => $verify ) );

	if ( is_wp_error( $resp ) || 200 != wp_remote_retrieve_response_code( $resp ) ) {
		return false;
	}

	$result = json_decode( wp_remote_retrieve_body( $resp ), true );

	return (bool) $result['success'];
}

/**
 * Handles registrations and redirects thereafter
 */
function wporg_login_create_user( $user_login, $user_email, $user_mailinglist = false ) {
	global $wpdb;

	// Allow for w.org plugins to block registrations based on spam checks, etc.
	if ( null !== ( $pre_register_error = apply_filters( 'wporg_login_pre_registration', null, $user_login, $user_email, $user_mailinglist ) ) ) {
		if ( is_wp_error( $pre_register_error ) ) {
			wp_die( $pre_register_error );
		}
		wp_die( __( 'Registration Blocked. Please stop.', 'wporg-login' ) );
	}

	$user_id = wpmu_create_user( wp_slash( $user_login ), wp_generate_password(), wp_slash( $user_email ) );
	if ( ! $user_id ) {
		wp_die( __( 'Error! Something went wrong with your registration. Try again?', 'wporg-login' ) );
	}

	// Insert a hashed activation key
	$activation_key = wp_generate_password( 24, false, false );
	if ( empty( $wp_hasher ) ) {
		$wp_hasher = new PasswordHash( 8, true );
	}
	$hashed_activation_key = time() . ':' . $wp_hasher->HashPassword( $activation_key );
	$bool = $wpdb->update( $wpdb->users, array( 'user_activation_key' => $hashed_activation_key ), array( 'ID' => $user_id ) );

	if ( $user_mailinglist ) {
		update_user_meta( $user_id, 'notify_list', 'true' );
	}

	$body  = sprintf( __( 'Hi %s,', 'wporg-login' ), $user_login ) . "\n\n";
	$body .= __( 'Welcome to WordPress.org! Your new account has been setup.', 'wporg-login' ) . "\n";
	$body .= "\n";
	$body .= sprintf( __( 'Your username is: %s', 'wporg-login' ), $user_login ) . "\n";
	$body .= __( 'You can create a password at the following URL:', 'wporg-login' ) . "\n";
	$body .= home_url( "/register/confirm/{$user_login}/{$activation_key}/" );
	$body .= "\n\n";
	$body .= __( '-- The WordPress.org Team', 'wporg-login' );

	wp_mail(
		$user_email,
		__( '[WordPress.org] Your new account', 'wporg-login' ),
		$body,
		array(
			'From: "WordPress.org" <noreply@wordpress.org>'
		)
	);

	wp_set_current_user( $user_id );
	$nonce = wp_create_nonce( 'login-register-profile-edit' );
	wp_set_current_user( 0 );

	wp_safe_redirect( '/register/profile/' . $user_login . '/' . $nonce );
	die();
}

function wporg_login_save_profile_fields() {
	if ( ! $_POST || empty( $_POST['user_fields'] ) ) {
		return;
	}
	$fields = array( 'url', 'from', 'occ', 'interests' );

	foreach ( $fields as $field ) {
		if ( isset( $_POST['user_fields'][ $field ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST['user_fields'][ $field ] ) );
			if ( 'url' == $field ) {
				wp_update_user( array(
					'ID' => get_current_user_id(),
					'user_url' => esc_url_raw( $value ),
				) );
			} else {
				update_user_meta( get_current_user_id(), $field, $value );
			}
		}
	}
}
