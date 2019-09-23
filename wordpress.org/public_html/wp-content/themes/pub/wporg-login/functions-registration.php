<?php

function wporg_login_check_recapcha_status() {
	if ( empty( $_POST['g-recaptcha-response'] ) ) {
		return false;
	}

	$result = wporg_login_recaptcha_api(
		$_POST['g-recaptcha-response'],
		RECAPTCHA_INVIS_PRIVKEY
	);

	if ( ! $result ) {
		return false;
	}
	return (bool) $result['success'];
}

/**
 * Handles creating a "Pending" registration that will later be converted to an actual user  account.
 */
function wporg_login_create_pending_user( $user_login, $user_email, $user_mailinglist = false  ) {
	global $wpdb, $wp_hasher;

	// Allow for w.org plugins to block registrations based on spam checks, etc.
	if ( null !== ( $pre_register_error = apply_filters( 'wporg_login_pre_registration', null, $user_login, $user_email, $user_mailinglist ) ) ) {
		if ( is_wp_error( $pre_register_error ) ) {
			wp_die( $pre_register_error );
		}
		wp_die( __( 'Registration Blocked. Please stop.', 'wporg' ) );
	}

	$activation_key = wp_generate_password( 24, false, false );
	$profile_key    = wp_generate_password( 24, false, false );

	$hashed_activation_key = time() . ':' . wp_hash_password( $activation_key );
	$hashed_profile_key    = time() . ':' . wp_hash_password( $profile_key );

	$pending_user = array(
		'user_login' => $user_login,
		'user_email' => $user_email,
		'user_registered' => gmdate('Y-m-d  H:i:s'),
		'user_activation_key' => $hashed_activation_key,
		'user_profile_key' => $hashed_profile_key,
		'meta' => array(
			'user_mailinglist' => $user_mailinglist,
			'registration_ip'  => $_SERVER['REMOTE_ADDR'], // Spam & fraud control. Will be discarded after the account is created.
		),
		'scores' => array()
	);

	// reCaptcha v3 logging.
	if ( isset( $_POST['_reCaptcha_v3_token'] ) ) {
		$recaptcha_api = wporg_login_recaptcha_api(
			$_POST['_reCaptcha_v3_token'],
			RECAPTCHA_V3_PRIVKEY
		);
		$pending_user['scores']['pending'] = -1;
		if ( $recaptcha_api && $recaptcha_api['success'] && 'register' == $recaptcha_api['action'] ) {
			$pending_user['scores']['pending'] = $recaptcha_api['score'];
		}
		
	}

	$inserted = wporg_update_pending_user( $pending_user );
	if ( ! $inserted ) {
		wp_die( __( 'Error! Something went wrong with your registration. Try again?', 'wporg' ) );
	}

	$body  = sprintf( __( 'Hi %s,', 'wporg' ), $user_login ) . "\n\n";
	$body .= __( 'Welcome to WordPress.org! Your new account has been setup.', 'wporg' ) . "\n";
	$body .= "\n";
	$body .= sprintf( __( 'Your username is: %s', 'wporg' ), $user_login ) . "\n";
	$body .= __( 'You can create a password at the following URL:', 'wporg' ) . "\n";
	$body .= home_url( "/register/create/{$user_login}/{$activation_key}/" );
	$body .= "\n\n";
	$body .= __( '-- The WordPress.org Team', 'wporg' );

	wp_mail(
		$user_email,
		__( '[WordPress.org] Your new account', 'wporg' ),
		$body,
		array(
			'From: "WordPress.org" <noreply@wordpress.org>'
		)
	);

	$url = home_url( sprintf(
		'/register/create-profile/%s/%s/',
		$user_login,
		$profile_key
	) );

	wp_safe_redirect( $url );
	die();
}

/**
 * Fetches a pending user record from the database by username or Email.
 */
function wporg_get_pending_user( $login_or_email ) {
	global $wpdb;

	$pending_user = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM `{$wpdb->base_prefix}user_pending_registrations` WHERE ( `user_login` = %s OR `user_email` = %s ) LIMIT 1",
		$login_or_email,
		$login_or_email
	), ARRAY_A );

	if ( ! $pending_user ) {
		return false;
	}

	$pending_user['meta']   = json_decode( $pending_user['meta'], true );
	$pending_user['scores'] = json_decode( $pending_user['scores'], true );

	return $pending_user;
}

/**
 * Update the pending user record, similar to `wp_update_user()` but for the not-yet-created user record.
 */
function wporg_update_pending_user( $pending_user ) {
	global $wpdb;
	$pending_user['meta']   = json_encode( $pending_user['meta'] );
	$pending_user['scores'] = json_encode( $pending_user['scores'] );

	if ( empty( $pending_user['pending_id'] ) ) {
		unset( $pending_user['pending_id'] );
		return $wpdb->insert(
			"{$wpdb->base_prefix}user_pending_registrations",
			$pending_user
		);
	} else {
		return $wpdb->update(
			"{$wpdb->base_prefix}user_pending_registrations",
			$pending_user,
			array( 'pending_id' => $pending_user['pending_id'] )
		);
	}

}

/**
 * Create a user record from a pending record. 
 */
function wporg_login_create_user_from_pending( $pending_user, $password = false ) {
	global $wpdb;

	// Insert user, no password tho.
	$user_login = $pending_user['user_login'];
	$user_email = $pending_user['user_email'];
	$user_mailinglist = !empty( $pending_user['meta']['user_mailinglist'] ) && $pending_user['meta']['user_mailinglist'];

	if ( ! $password ) {
		$password = wp_generate_password();
	}

	$user_id = wpmu_create_user(
		wp_slash( $user_login ),
		$password,
		wp_slash( $user_email )
	);
	if ( ! $user_id ) {
		wp_die( __( 'Error! Something went wrong with your registration. Try again?', 'wporg' ) );
	}

	// Update the registration date to the earlier one.
	wp_update_user( array(
		'ID' => $user_id,
		'user_registered' => $pending_user['user_registered']
	) );

	// Update the pending record with the new details.
	$pending_user['created'] = 1;
	$pending_user['created_date'] = gmdate( 'Y-m-d H:i:s' );
	$pending_user['meta']['confirmed_ip'] = $_SERVER['REMOTE_ADDR']; // Spam/Fraud purposes, will be deleted once not needed.

	// reCaptcha v3 logging.
	if ( isset( $_POST['_reCaptcha_v3_token'] ) ) {
		$recaptcha_api = wporg_login_recaptcha_api(
			$_POST['_reCaptcha_v3_token'],
			RECAPTCHA_V3_PRIVKEY
		);
		$pending_user['scores']['create'] = -1;
		if ( $recaptcha_api && $recaptcha_api['success'] && 'pending_create' == $recaptcha_api['action'] ) {
			$pending_user['scores']['create'] = $recaptcha_api['score'];
		}
	}

	wporg_update_pending_user( $pending_user );

	if ( $user_mailinglist ) {
		update_user_meta( $user_id, 'notify_list', 'true' );
	}

	foreach ( array( 'url', 'from', 'occ', 'interests' ) as $field ) {
		if ( !empty( $pending_user['meta'][ $field ] ) ) {
			$value = $pending_user['meta'][ $field ];
			if ( 'url' == $field ) {
				wp_update_user( array( 'ID' => $user_id, 'user_url' => $value ) );
			} else {
				if ( $value ) {
					update_user_meta( $user_id, $field, $value );
				} else {
					delete_user_meta( $user_id, $field );
				}
			}
		}
	}

	return get_user_by( 'id', $user_id );
}

/**
 * Save the user profile fields, potentially prior to user creation and prior to email confirmation.
 */
function wporg_login_save_profile_fields( $pending_user = false ) {
	if ( ! $_POST || empty( $_POST['user_fields'] ) ) {
		return false;
	}
	$fields = array( 'url', 'from', 'occ', 'interests' );

	foreach ( $fields as $field ) {
		if ( isset( $_POST['user_fields'][ $field ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_POST['user_fields'][ $field ] ) );
			if ( 'url' == $field ) {
				if ( $pending_user ) {
					$pending_user['meta'][ $field ] = esc_url_raw( $value );
				} else {
					wp_update_user( array(
						'ID' => get_current_user_id(),
						'user_url' => esc_url_raw( $value ),
					) );
				}
			} else {
				if ( $pending_user ) {
					$pending_user['meta'][ $field ] = $value;
				} else {
					if ( $value ) {
						update_user_meta( get_current_user_id(), $field, $value );
					} else {
						delete_user_meta( get_current_user_id(), $field );
					}
				}
			}
		}
	}

	if ( $pending_user ) {
		wporg_update_pending_user( $pending_user );
	}

	return true;
}
