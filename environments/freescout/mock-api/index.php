<?php
/**
 * Mock of the api.wordpress.org/dotorg/freescout/ endpoints.
 *
 * Checks requests like the real endpoints (signature, JSON object, age), and answers sidebar requests with the data it received.
 * Webhook events are logged to the container output: `docker compose logs mock-api`.
 * Also stands in for login.wordpress.org's identity provider at /idp; see idp.php.
 *
 * @package WordPressdotorg\FreeScout\Environment
 */

declare( strict_types = 1 );

namespace WordPressdotorg\FreeScout\Environment\MockAPI;

/**
 * Sends a JSON response and ends the request.
 *
 * @param int          $status HTTP status code.
 * @param array|object $data   Response body; an object for `{}`.
 * @return void
 */
function respond( int $status, array|object $data ): void {
	http_response_code( $status );
	header( 'Content-Type: application/json' );
	echo json_encode( $data );
	exit;
}

/**
 * Escapes a value for HTML output.
 *
 * @param string $value Value.
 * @return string
 */
function esc( string $value ): string {
	return htmlspecialchars( $value, ENT_QUOTES );
}

$endpoint = basename( (string) parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );

// Browsers come here, not FreeScout, so there's no signature.
if ( 'idp' === $endpoint ) {
	require __DIR__ . '/idp.php';
	exit;
}

$body      = (string) file_get_contents( 'php://input' );
$signature = (string) ( $_SERVER['HTTP_X_FREESCOUT_SIGNATURE'] ?? '' );
$secret    = (string) getenv( 'WPORG_API_SECRET' );

if ( ! hash_equals( hash_hmac( 'sha256', $body, $secret ), $signature ) ) {
	respond( 403, array( 'error' => 'Invalid signature.' ) );
}

$request = json_decode( $body );

// The real endpoints refuse requests older or newer than 15 minutes.
if ( ! is_object( $request ) || abs( time() - (int) ( $request->sent_at ?? 0 ) ) > 15 * 60 ) {
	respond( 403, array( 'error' => 'Not a fresh JSON object.' ) );
}

if ( 'account.php' === $endpoint ) {
	$accounts = require __DIR__ . '/accounts.php';
	$username = strtolower( (string) ( $request->username ?? '' ) );

	if ( ! isset( $accounts[ $username ] ) ) {
		respond( 200, array( 'user' => null ) );
	}

	respond(
		200,
		array(
			'user' => array_merge(
				$accounts[ $username ],
				array(
					'username'    => $username,
					'profile_url' => 'https://profiles.wordpress.org/' . $username . '/',
					'avatar_url'  => 'https://www.gravatar.com/avatar/' . md5( $accounts[ $username ]['email'] ) . '?s=256&d=mm',
				)
			),
		)
	);
}

if ( 'webhook.php' === $endpoint ) {
	file_put_contents(
		'php://stderr',
		sprintf(
			"[webhook] %s conversation #%d in %s by %s\n",
			(string) ( $request->event ?? '' ),
			(int) ( $request->conversation->number ?? 0 ),
			(string) ( $request->mailbox->name ?? '' ),
			(string) ( $request->agent->email ?? 'sender' )
		)
	);
	respond( 200, new \stdClass() );
}

$email   = (string) ( $request->sender->email ?? '' );
$threads = count( $request->threads ?? array() );

respond(
	200,
	array(
		'html' => sprintf(
			'<p><em>Mock %s</em></p><p>Sender: %s</p><p>Mailbox: %s</p><p>Threads received: %d</p>',
			esc( $endpoint ),
			esc( $email ),
			esc( (string) ( $request->mailbox->name ?? '' ) ),
			$threads
		),
	)
);
