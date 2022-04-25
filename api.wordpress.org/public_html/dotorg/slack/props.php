<?php

/*
 * This endpoint receives requests from the `#props - production` app.
 */

namespace {
	require dirname( __DIR__, 2 ) . '/includes/hyperdb/bb-10-hyper-db.php';
	require dirname( __DIR__, 2 ) . '/includes/slack-config.php';
}

namespace Dotorg\Slack\Props {
	use Exception;
	use function Dotorg\Slack\{ is_valid_request };

	require dirname( __DIR__, 2 ) . '/includes/slack/helpers.php';
	require dirname( __DIR__, 2 ) . '/includes/slack/props/lib.php';

	try {
		$result              = '';
		$request_body_raw    = file_get_contents( 'php://input' );
		$request_body_parsed = json_decode( $request_body_raw );
		$valid_request       = is_valid_request( APP_ID, APP_SIGNING_SECRET, getallheaders(), $request_body_raw );

		if ( $valid_request ) {
			// Replying with the challenge verifies this handler to Slack.
			$result = $request_body_parsed->challenge ?? handle_props_message( $request_body_parsed );
		}

		die( $result );

	} catch ( Exception $exception ) {
		trigger_error( $exception->getMessage(), E_USER_WARNING );
	}
}
