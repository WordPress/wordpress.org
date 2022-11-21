<?php

/*
 * This provide an interface to add/remove badges and activity entries from profiles.w.org.
 * It uses Requests directly because WP often isn't loaded in endpoints.
 *
 * See `w.org/public_html/wp-content/mu-plugins/pub/profile-helpers.php` for a similar interface that is tailored
 * to WP environments.
 */

namespace Dotorg\Profiles;
use Requests, Requests_Exception;

const HANDLER_URL = 'https://profiles.wordpress.org/wp-admin/admin-ajax.php';

/**
 * Send requests to Profiles and handle errors.
 *
 * `$request_args` should match what the handler expects for a particular request. See
 * `wporg-profiles-activity-handler.php` and `wporg-profiles-association-handler.php`.
 */
function post( array $request_args ) : string {
	init_requests();

	$headers = array();
	$options = array();
	$error   = false;
	$return  = '';

	// Requests to w.org sandbox should also use profiles.w.org sandbox, for testing end to end.
	if ( defined( 'WPORG_SANDBOXED' ) && WPORG_SANDBOXED ) {
		$url             = str_replace( 'profiles.wordpress.org', '127.0.0.1', HANDLER_URL );
		$headers['Host'] = 'profiles.wordpress.org';

		// Sandboxes don't have valid certs. It's safe since we're only connecting to `127.0.0.1`.
		$options['verify'] = false;

	} else {
		$url = HANDLER_URL;
	}

	try {
		$response = Requests::post( $url, $headers, $request_args, $options );

		if ( $response->success ) {
			$return = $response->body;

		} else {
			$error = sprintf(
				'Request to %s failed with code %d and body: %s',
				$url,
				$response->status_code,
				$response->body
			);
		}

	} catch ( Requests_Exception $exception ) {
		$error = sprintf(
			'Request to %s failed with code %d and message: %s',
			$url,
			$exception->getCode(),
			$exception->getMessage()
		);

		$return = '-1 HTTP error';

	} finally {
		if ( $error ) {
			trigger_error( $error, E_USER_WARNING );
		}
	}

	return $return;
}

function init_requests() {
	if ( class_exists( 'Requests' ) ) {
		return;
	}

	require_once WPORGPATH . '/wp-includes/class-requests.php';
	Requests::register_autoloader();
	Requests::set_certificate_path( WPORGPATH . '/wp-includes/certificates/ca-bundle.crt' );
}
