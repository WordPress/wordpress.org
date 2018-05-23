<?php
/**
 * Functions for the Privacy Tools - Exports and Erasures.
 */
namespace WordPressdotorg\MainTheme;
use WordPressdotorg\GDPR\Main as GDPR_Main;

function privacy_process_request( $type ) {
	$email = $error_message = $success = false;
	$nonce_action = 'request_' . $type;

	if ( empty( $_POST['email'] ) || ! $type || ! in_array( $type, [ 'erase', 'export' ] ) ) {
		return compact( 'email', 'error_message', 'success', 'nonce_action' );
	}

	$email = trim( wp_unslash( $_POST['email'] ) );

	$requesting_user = false;
	if ( is_user_logged_in() ) {
		$requesting_user = wp_get_current_user()->user_login;
	}

	// Currently only enabled for special accounts.
	if ( ! is_user_logged_in() || ! wporg_user_has_restricted_password() ) {
		$api_request = new \WP_Error(
			'not_available',
			'This form is currently disabled.'
		);
	} else
	if ( ! reCAPTCHA\check_status() ) {
		$error_message = 'Your form session has expired. Please try again.';
	} elseif (
		is_user_logged_in() &&
		! wp_verify_nonce( $_POST['_wpnonce'], $nonce_action )
	) {
		$error_message = 'Your form session has expired. Please try again.';

	} elseif (
		// Check if a user account exists for this email before processing.
		false != ( $email_user = get_user_by( 'email', $email ) ) &&
		$email_user->user_login !== $requesting_user
	) {
		if ( is_user_logged_in() ) {
			$error_message = sprintf(
				'The provided email address belongs to a different WordPress.org account. Please <a href="%1$s">log out</a> and <a href="%2$s">login to the account first</a>.',
				wp_logout_url( get_permalink() ),
				wp_login_url( get_permalink() )
			);
		} else {
			$error_message = sprintf(
				'The provided email address belongs to a WordPress.org account. Please <a href="%s">login to the account first</a>.',
				wp_login_url( get_permalink() )
			);
		}

	} else {
		if ( 'export' == $type ) {
			$api_method = 'create-data-export-request';
		} elseif ( 'erase' == $type ) {
			$api_method = 'create-account-erasure-request';
		}

		$api_request = GDPR_Main::instance()->call_api_for_site(
			'wordpress.org/',
			[
				'email'           => $email,
				'requesting_user' => $requesting_user,
			],
			$api_method,
			'POST'
		);

		if ( is_wp_error( $api_request ) ) {
			$error_message = $api_request->get_error_message();

			if ( 'duplicate_request' == $api_request->get_error_code() ) {
				// TODO This should never have to be displayed to an end user. See API for details.
				$error_message = 'A request for this email address already exists. Please check your spam folder for your confirmation email.';

			} elseif ( 'invalid_identifier' == $api_request->get_error_code() ) {
				$error_message = 'The provided email was invalid. Please check the address and try again.';

			}
		} elseif ( !empty( $api_request['created'] ) ) {
			$success = true;
		}
	}

	return compact( 'email', 'error_message', 'success', 'nonce_action' );
}
