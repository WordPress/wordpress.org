<?php

/*
 * This endpoint receives requests from the `#props - production` app.
 *
 * See `includes/slack/props/lib.php` for setup/testing instructions and helper functions.
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

	$result = '';

	try {
		$request_body_raw    = file_get_contents( 'php://input' );
		$request_body_parsed = json_decode( $request_body_raw );
		$headers             = getallheaders();
		$valid_request       = is_valid_request( APP_ID, APP_SIGNING_SECRET, $headers, $request_body_raw );

		/*
		 * This endpoint shouldn't try to process retries, because that could lead to duplicate activity items.
		 * The most likely reason is that we didn't reply in under 3 seconds, but still processed the event
		 * successfully. Slack interprets that as an HTTP timeout.
		 *
		 * See https://api.slack.com/apis/connections/events-api#the-events-api__field-guide__error-handling__graceful-retries
		 * See https://api.slack.com/apis/connections/events-api#the-events-api__responding-to-events
		 */
		if ( isset( $headers['X-Slack-Retry-Reason'] ) ) {
			$valid_request = false;
			$message_id    = sprintf( '%s-%s', $request_body_parsed->event->channel, $request_body_parsed->event->ts );

			header( 'X-Slack-No-Retry', 1 ); // Don't retry this event again.
			trigger_error(
				sprintf( 'Received retry for %s because: %s', $message_id, $headers['X-Slack-Retry-Reason'] ),
				E_USER_NOTICE
			);
		}

		if ( $valid_request ) {
			// Replying with the challenge verifies this handler to Slack.
			$result = $request_body_parsed->challenge ?? handle_props_message( $request_body_parsed );
		}

	} catch ( Exception $exception ) {
		trigger_error( $exception->getMessage(), E_USER_WARNING );

	} finally {
		/*
		 * If the response isn't a 200 then Slack might retry the request. They only care if we received the
		 * request, so this should still be 200 even if something goes wrong on our end.
		 */
		http_response_code( 200 );
		die( $result );
	}
}
