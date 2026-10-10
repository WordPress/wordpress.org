<?php
/**
 * Mock of the api.wordpress.org/dotorg/freescout/ endpoints.
 *
 * Checks requests like the real endpoints (signature, JSON object, age, endpoint; not nonces), and answers sidebar requests with panels recorded from the real endpoints.
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

// Like the real endpoints, minus the nonce check.
$age = time() - (int) ( $request->sent_at ?? 0 );
if ( ! is_object( $request ) || $age < -10 || $age > 5 * 60 || ( $request->endpoint ?? '' ) !== $endpoint ) {
	respond( 403, array( 'error' => 'Not a fresh JSON object for this endpoint.' ) );
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

if ( 'replace-copies.php' === $endpoint ) {
	$copies = (array) ( $request->copies ?? array() );
	file_put_contents( 'php://stderr', sprintf( "[replace-copies] %d conversations\n", count( $copies ) ) );
	respond( 200, array( 'replaced' => count( $copies ) ) );
}

// Like plugin-review.php: any plugin ID is a plugin in review, in the plugins team's mailbox.
if ( 'plugin-review.php' === $endpoint ) {
	$plugin_id = (int) ( $request->plugin_id ?? 0 );
	if ( ! $plugin_id || ! str_starts_with( (string) ( $request->mailbox->email ?? '' ), 'plugins' ) ) {
		respond( 200, array( 'plugin' => null ) );
	}

	respond(
		200,
		array(
			'plugin' => array(
				'id'           => $plugin_id,
				'name'         => 'Mock Plugin Renamed',
				'slug'         => 'mock-plugin-renamed',
				'status'       => 'pending',
				'status_label' => 'In Review',
				'released'     => false,
				'scans_url'    => '',
				'edit_url'     => 'https://wordpress.org/plugins/wp-admin/post.php?action=edit&post=' . $plugin_id,
				'page_url'     => 'https://wordpress.org/plugins/mock-plugin-renamed/',
				'download_url' => 'https://wordpress.org/plugins/wp-content/uploads/mock-plugin.zip#wporgapi:https://wordpress.org/plugins/wp-json/plugins/v1/plugin-review/' . $plugin_id . '-0123456789abcdef0123456789abcdef/',
				'submitter'    => array(
					'username' => 'mockauthor',
					'email'    => 'author@example.org',
				),
				'reviewer'     => 'Mock Reviewer',
			),
		)
	);
}

/*
 * What the real endpoints sent for obenland's WordPress.org account, whatever the sender, though searches are for the
 * sender's email, as they are there. Download links lose their review info, and the forum note and privacy request
 * are samples, as the account has neither.
 */
$panel = __DIR__ . '/panels/' . basename( $endpoint, '.php' ) . '.json';
$email = rawurlencode( (string) ( $request->sender->email ?? '' ) );

respond( 200, is_file( $panel ) ? json_decode( str_replace( '{sender_email}', $email, (string) file_get_contents( $panel ) ) ) : array( 'blocks' => array() ) );
