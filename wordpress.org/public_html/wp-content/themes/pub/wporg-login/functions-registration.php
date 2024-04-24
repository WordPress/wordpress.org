<?php

function wporg_login_check_recapcha_status( $check_v3_action = false, $block_low_scores = true ) {

	// Allow local installs to bypass
	if (
		'local' === wp_get_environment_type() &&
		! defined( 'RECAPTCHA_V3_PRIVKEY' ) &&
		! defined( 'RECAPTCHA_INVIS_PRIVKEY' )
	) {
		return true;
	}

	// reCaptcha V3 Checks
	if ( $check_v3_action ) {
		if ( empty( $_POST['_reCaptcha_v3_token'] ) ) {
			return false;
		}
		$result = wporg_login_recaptcha_api(
			$_POST['_reCaptcha_v3_token'],
			RECAPTCHA_V3_PRIVKEY
		);

		if (
			! $result ||
			! $result['success'] ||
			empty( $result['action'] ) ||
			$check_v3_action !== $result['action']
		) {
			return false;
		}

		// Block super-low scores.
		if ( $block_low_scores && (float)$result['score'] < (float) get_option( 'recaptcha_v3_threshold', 0.2 ) ) {
			return false;
		}
	}

	// reCaptcha V2 Checks
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
 * Handles creating a "Pending" registration that will later be converted to an actual user account.
 */
function wporg_login_create_pending_user( $user_login, $user_email, $meta = array() ) {
	global $wpdb, $wp_hasher;

	// Remove any whitespace which might have accidentally been added.
	$user_login = trim( $user_login );
	$user_email = trim( $user_email );

	// Allow for w.org plugins to block registrations based on spam checks, etc.
	if ( null !== ( $pre_register_error = apply_filters( 'wporg_login_pre_registration', null, $user_login, $user_email, $meta ) ) ) {
		if ( is_wp_error( $pre_register_error ) ) {
			wp_die( $pre_register_error );
		}
		wp_die( __( 'Registration Blocked. Please stop.', 'wporg' ) );
	}

	$profile_key        = wp_generate_password( 24, false, false );
	$hashed_profile_key = time() . ':' . wp_hash_password( $profile_key );

	$pending_user = array(
		'user_login'          => $user_login,
		'user_email'          => $user_email,
		'user_registered'     => gmdate('Y-m-d  H:i:s'),
		'user_activation_key' => '',
		'user_profile_key'    => $hashed_profile_key,
		'meta'                => $meta + array(
			'registration_ip'         => $_SERVER['REMOTE_ADDR'], // Spam & fraud control. Will be discarded after the account is created.
			'registration_ip_country' => ( is_callable( 'WordPressdotorg\GeoIP\query' ) ? \WordPressdotorg\GeoIP\query( $_SERVER['REMOTE_ADDR'], 'country_short' ) : '' ),
			'source'                  => $_COOKIE['wporg_came_from'] ?? '',
		),
		'scores'              => array(
			'pending' => 1,
		),
		'cleared' => 0,
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

	$pending_user['meta']['heuristics'] = 'allow';
	if ( function_exists( 'wporg_registration_check_private_heuristics' ) ) {
		// Returns block, review, allow.
		$pending_user['meta']['heuristics'] = wporg_registration_check_private_heuristics( compact( 'user_login', 'user_email' ) );
	}

	$passes_block_words = wporg_login_check_against_block_words( $pending_user );

	$pending_user['cleared'] = (
		'allow' === $pending_user['meta']['heuristics'] &&
		(float)$pending_user['scores']['pending'] >= (float) get_option( 'recaptcha_v3_threshold', 0.2 ) &&
		$passes_block_words
	);

	// Run a filter on the cleared status..
	if ( ! apply_filters( 'wporg_login_registration_check_user', true, $pending_user ) ) {
		$pending_user['cleared'] = false;
	}

	$inserted = wporg_update_pending_user( $pending_user );
	if ( ! $inserted ) {
		wp_die( __( 'Error! Something went wrong with your registration. Try again?', 'wporg' ) );
	}

	wporg_login_send_confirmation_email( $user_email );

	$url = home_url( sprintf(
		'/register/create-profile/%s/%s/',
		$user_login,
		$profile_key
	) );

	wp_safe_redirect( $url );
	die();
}

/**
 * Send a "Welcome to WordPress.org" confirmation email.
 */
function wporg_login_send_confirmation_email( $user ) {
	global $wpdb;

	$user = wporg_get_pending_user( $user );

	if ( ! $user || $user['created'] || ! $user['cleared'] ) {
		return false;
	}

	$user_login = $user['user_login'];
	$user_email = $user['user_email'];

	$activation_key = wp_hash( $user_login . ':' . $user_email, 'activation' );

	// Every email bumps the expiration time.
	$user['user_activation_key'] = time() . ':' . wp_hash_password( $activation_key );
	if ( ! wporg_update_pending_user( $user ) ) {
		return false;
	}

	$password_set_url = home_url( '/register/create/' . urlencode( $user_login ) . '/' . urlencode( $activation_key ) . '/' );

	$body  = sprintf( __( 'Hi %s,', 'wporg' ), $user_login ) . "\n\n";
	$body .= __( 'Welcome to WordPress.org! Your new account has been setup.', 'wporg' ) . "\n";
	$body .= "\n";
	$body .= sprintf( __( 'Your username is: %s', 'wporg' ), $user_login ) . "\n";
	$body .= __( 'You can create a password at the following URL:', 'wporg' ) . "\n";
	$body .= $password_set_url;
	$body .= "\n\n";
	$body .= __( '-- The WordPress.org Team', 'wporg' );

	$headers = array(
		'From: "WordPress.org" <noreply@wordpress.org>'
	);

	if ( 'local' === wp_get_environment_type() ) {
		$headers = array();
		setcookie( 'emailed_url', $password_set_url );
	}

	return wp_mail(
		$user_email,
		__( '[WordPress.org] Your new account', 'wporg' ),
		$body,
		$headers
	);
}

/**
 * Fetches a pending user record from the database by username or Email.
 *
 * @param string|int $who The username, email address, or user ID.
 */
function wporg_get_pending_user( $who ) {
	global $wpdb;

	// Is it a pending user object already?
	if ( is_array( $who ) && isset( $who['pending_id'] ) ) {
		return $who;
	}

	if ( is_numeric( $who ) && (int) $who == $who ) {
		$field = 'pending_id';
	} elseif ( str_contains( $who, '@' ) ) {
		$field = 'user_email';
	} else {
		$field = 'user_login';
	}

	$who = trim( $who );
	if ( ! $who ) {
		return false;
	}

	$pending_user = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM `{$wpdb->base_prefix}user_pending_registrations` WHERE %i = %s LIMIT 1",
		$field,
		$who
	), ARRAY_A );

	if ( ! $pending_user ) {
		return false;
	}

	$pending_user['meta']   = json_decode( $pending_user['meta'], true );
	$pending_user['scores'] = json_decode( $pending_user['scores'], true );

	// Cast the int fields to an integer.
	$pending_user['pending_id'] = (int) $pending_user['pending_id'];
	$pending_user['cleared']    = (int) $pending_user['cleared'];
	$pending_user['created']    = (int) $pending_user['created'];

	return $pending_user;
}

/**
 * Fetches a pending user record from the database by "inbox", ignoring plus addressing.
 */
function wporg_get_pending_user_by_email_wildcard( $email ) {
	global $wpdb;

	$email_wildcard = preg_replace( '/[+][^@]+@/i', '+%@', $wpdb->esc_like( $email ) );  // abc+def@ghi => abc+%@ghi
	$email_base     = preg_replace( '/[+][^@]+@/i', '@', $email ); // abc+def@ghi => abc@ghi

	$matching_email = $wpdb->get_var( $sql = $wpdb->prepare(
		"SELECT `user_email` FROM `{$wpdb->base_prefix}user_pending_registrations` WHERE ( `user_email` = %s OR `user_email` LIKE %s ) LIMIT 1",
		$email_base,
		$email_wildcard
	) );

	if ( $matching_email ) {
		return wporg_get_pending_user( $matching_email );
	}

	return false;
}

/**
 * Update the pending user record, similar to `wp_update_user()` but for the not-yet-created user record.
 */
function wporg_update_pending_user( $pending_user ) {
	global $wpdb;

	// Allow altering the user fields.
	$pending_user = apply_filters( 'wporg_login_registration_update_pending_user', $pending_user );

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

function wporg_delete_pending_user( $pending_user ) {
	global $wpdb;

	if ( empty( $pending_user['pending_id'] ) ) {
		return false;
	}

	return $wpdb->delete(
		"{$wpdb->base_prefix}user_pending_registrations",
		array( 'pending_id' => $pending_user['pending_id'] )
	);
}

/**
 * Create a user record from a pending record.
 */
function wporg_login_create_user_from_pending( $pending_user, $password = false ) {
	global $wpdb;

	// Insert user, no password tho.
	$user_login = trim( $pending_user['user_login'] );
	$user_email = trim( $pending_user['user_email'] );
	$user_mailinglist = !empty( $pending_user['meta']['user_mailinglist'] ) && $pending_user['meta']['user_mailinglist'];

	if ( ! $password ) {
		$password = wp_generate_password();
	}

	// Use wpmu_create_user() on multisite, and wp_create_user() on single-sites (local testing).
	$wp_create_user = function_exists( 'wpmu_create_user' ) ? 'wpmu_create_user' : 'wp_create_user';

	$user_id = $wp_create_user(
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
	$pending_user['created']                      = 1;
	$pending_user['created_date']                 = gmdate( 'Y-m-d H:i:s' );
	$pending_user['meta']['confirmed_ip']         = $_SERVER['REMOTE_ADDR'];
	$pending_user['meta']['confirmed_ip_country'] = ( is_callable( 'WordPressdotorg\GeoIP\query' ) ? \WordPressdotorg\GeoIP\query( $_SERVER['REMOTE_ADDR'], 'country_short' ): '' );

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

	$tos_meta_key = WPOrg_SSO::TOS_USER_META_KEY;

	foreach ( array( 'url', 'from', 'occ', 'interests', $tos_meta_key ) as $field ) {
		if ( !empty( $pending_user['meta'][ $field ] ) ) {
			$value = $pending_user['meta'][ $field ];
			if ( 'url' == $field ) {
				wp_update_user( array( 'ID' => $user_id, 'user_url' => $value ) );

				// Update BuddyPress xProfile data.
				if ( function_exists( 'WordPressdotorg\Profiles\update_profile' ) ) {
					WordPressdotorg\Profiles\update_profile( 'Website URL', $value, $user_id );
				}
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
function wporg_login_save_profile_fields( $pending_user = false, $state = '' ) {
	if ( ! $_POST || empty( $_POST['user_fields'] ) ) {
		return false;
	}
	$fields = array( 'url', 'from', 'occ', 'interests' );

	foreach ( $fields as $field ) {
		if ( isset( $_POST['user_fields'][ $field ] ) ) {
			$value = trim( sanitize_text_field( wp_unslash( $_POST['user_fields'][ $field ] ) ) );
			if ( 'url' == $field ) {
				/** This filter is documented in wp-includes/user.php */
				$value = apply_filters( 'pre_user_url', $value );

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

	$updated_email = false;
	$new_email     = trim( wp_unslash( $_POST['user_email'] ?? '' ) );
	if (
		'pending' === $state &&
		empty( $pending_user['meta']['changed_email'] ) && // Only if they've not changed it before.
		$new_email &&
		$new_email !== $pending_user['user_email']
	) {
		/** This filter is documented in wp-includes/user.php */
		$new_email = apply_filters( 'pre_user_email', $new_email );

		// Validate the email
		$error_user_email = rest_do_request( new WP_REST_Request( 'GET', '/wporg/v1/email-in-use/' . urlencode( $new_email ) ) );
		if ( $error_user_email->get_data()['available'] ) {
			// Change their email, resend confirmation.
			$pending_user['meta']['changed_email'] = $pending_user['user_email'];
			$pending_user['user_email']            = $new_email;
			$pending_user['user_activation_key']   = ''; // Clear any existing email hash.
			$updated_email                         = true;

			// Validate heuristics.
			if ( function_exists( 'wporg_registration_check_private_heuristics' ) ) {
				// Returns block, review, allow.
				$pending_user['meta']['heuristics'] = wporg_registration_check_private_heuristics( [
					'user_login' => $pending_user['user_login'],
					'user_email' => $pending_user['user_email']
				] );
			}

			// If the new email fails our checks, and the user hasn't manually been approved..
			if ( 'allow' !== $pending_user['meta']['heuristics'] && $pending_user['cleared'] < 2 ) {
				$pending_user['cleared'] = 0;
			}
		}
	}

	// If not manually approved, check against block_words, and any other registration checks that are hooked in.
	if ( $pending_user['cleared'] < 2 ) {
		$passes_block_words = wporg_login_check_against_block_words( $pending_user );
		if ( ! $passes_block_words ) {
			$pending_user['cleared'] = 0;
		}

		// Check the filter.
		if ( ! apply_filters( 'wporg_login_registration_check_user', true, $pending_user ) ) {
			$pending_user['cleared'] = 0;
		}
	}

	if ( $pending_user ) {
		wporg_update_pending_user( $pending_user );
		if ( $updated_email ) {
			wporg_login_send_confirmation_email( $pending_user );
		}
	}

	return true;
}

/**
 * Check a pending user object against the 'block words' setting.
 * 
 * @return bool
 */
function wporg_login_check_against_block_words( $user ) {
	$block_words = get_option( 'registration_block_words', [] );

	foreach ( $block_words as $word ) {
		if (
			false !== stripos( $user['user_login'], $word ) ||
			false !== stripos( $user['user_email'], $word )
		) {
			return false;
		}

		foreach ( [ 'url', 'from', 'occ', 'interests' ] as $field ) {
			if (
				! empty( $user['meta'][ $field ] ) &&
				false !== stripos( $user['meta'][ $field ], $word )
			) {
				return false;
			}
		}
	}

	return true;
}
