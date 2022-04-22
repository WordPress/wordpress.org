<?php

namespace Dotorg\Slack;
use Exception;

/**
 * Validate that a request from Slack is:
 *
 *  - intended for our workspace
 *  - from the expected app
 *  - has a valid signature
 */
function is_valid_request( string $app_id, string $app_signing_secret, array $request_headers, string $raw_request_body ) : bool {
	$parsed_request = json_decode( $raw_request_body );

	$valid_signature = is_valid_request_signature(
		$request_headers['X-Slack-Signature'] ?? '',
		(int) $request_headers['X-Slack-Request-Timestamp'] ?? 0,
		$raw_request_body,
		$app_signing_secret
	);

	// Challenges shouldn't have events, so one that does is probably from a pentester.
	// They won't include the app/team IDs, though.
	if ( isset( $parsed_request->challenge ) && empty( $parsed_request->event ) ) {
		$valid_request = $valid_signature;
	} else {
		$valid_request = WORDPRESSORG_TEAM_ID === $parsed_request->team_id &&
		                 $app_id === $parsed_request->api_app_id &&
		                 $valid_signature;
	}

	if ( $valid_request ) {
		return true;
	} else {
		throw new Exception( 'Invalid request' );
	}
}

/**
 * Validate the signature of a request.
 *
 * Slack signs each request using a shared secret, so we need to verify that signing the message w/ our copy of
 * the secret results in the same hash as theirs. If it doesn't, then the message has been tampered with.
 *
 * See https://api.slack.com/authentication/verifying-requests-from-slack
 *
 * `$request_body` must be the raw string, not the deserialized object.
 */
function is_valid_request_signature( string $user_signature, int $timestamp, string $request_body, string $signing_secret ) : bool {
	$version       = 'v0';
	$delay_seconds = time() - $timestamp;

	// Prevent replay attacks.
	if ( $delay_seconds > 30 ) {
		throw new Exception( 'Possible replay attack' );
	}

	$message             = sprintf( '%s:%s:%s', $version, $timestamp, $request_body );
	$authentic_signature = sprintf( '%s=%s', $version, hash_hmac( 'sha256', $message, $signing_secret ) );

	if ( ! hash_equals( $authentic_signature, $user_signature ) ) {
		/*
		 * It's not safe to include the details, because they all contain tokens. Even `$user_signature` might
		 * contain a valid one if there's a bug on our end.
		 */
		throw new Exception( 'Invalid request signature' );
	}

	return true;
}
