<?php
/**
 * Mock of the api.wordpress.org/dotorg/freescout/ endpoints.
 *
 * Checks requests like the real endpoints (signature, JSON object, age), and answers sidebar requests with sample panels that include some of the data it received.
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
$mailbox = (string) ( $request->mailbox->name ?? '' );
$threads = count( $request->threads ?? array() );

/**
 * Renders a sample plugin, like plugins-themes.php does.
 *
 * @param string $name   Plugin name.
 * @param string $status Badge text, empty for a published plugin.
 * @param string $tone   Badge tone.
 * @return string
 */
function item( string $name, string $status = '', string $tone = 'neutral' ): string {
	$slug = strtolower( str_replace( ' ', '-', $name ) );

	return sprintf(
		'<li class="wporg-sidebar-item"><a class="wporg-sidebar-item-title" href="#">%s</a> %s<div class="wporg-sidebar-item-meta">%s · <span title="Last updated">3w</span> <span class="wporg-sidebar-item-links"><a href="#" title="View on WordPress.org" aria-label="View on WordPress.org"><i class="glyphicon glyphicon-link"></i></a> <a href="#" title="Download" aria-label="Download"><i class="glyphicon glyphicon-download-alt"></i></a></span></div></li>',
		esc( $name ),
		$status ? sprintf( '<span class="wporg-sidebar-badge is-%s">%s</span>', esc( $tone ), esc( $status ) ) : '',
		esc( $slug )
	);
}

// Sample panels in the real endpoints' markup, so the sidebar's styles can be worked on locally.
$html = match ( $endpoint ) {
	'profile.php'        => sprintf(
		'<p class="wporg-sidebar-lead"><a href="#">%s</a></p><p class="wporg-sidebar-meta">Mock: %d threads from %s</p><ul class="wporg-sidebar-links"><li><a href="#">Account &amp; Security</a></li><li><a href="#">Forum Profile</a></li><li><a href="#">Search pending signups</a></li></ul><h5 class="wporg-sidebar-heading">Slack</h5><ul class="wporg-sidebar-items"><li class="wporg-sidebar-item"><a class="wporg-sidebar-item-title" href="#">%1$s</a> <span class="wporg-sidebar-badge is-success">Active</span><div class="wporg-sidebar-item-meta">Updated 2026-01-01</div></li></ul>',
		esc( (string) strtok( $email, '@' ) ),
		$threads,
		esc( $mailbox )
	),
	'forums.php'         => '<ul class="wporg-sidebar-items"><li class="wporg-sidebar-item"><p class="wporg-sidebar-note">Mock note: asked to stop bumping their topics.</p><div class="wporg-sidebar-item-meta"><a href="#">January 1, 2026</a> · moderator</div></li></ul>',
	'plugins-themes.php' => '<h5 class="wporg-sidebar-heading">Plugins mentioned in this email</h5><ul class="wporg-sidebar-items">' . item( 'Mock Plugin', 'In Review', 'warning' ) . '</ul>'
		. '<h5 class="wporg-sidebar-heading"><a href="#">Plugins owned</a></h5><ul class="wporg-sidebar-items">' . item( 'Mock Plugin', 'In Review', 'warning' ) . item( 'Hello Mock' ) . item( 'Mock Blocks', 'Approved', 'success' ) . item( 'Old Mock', 'Closed: Author Request', 'error' ) . item( 'Mock Widgets' ) . item( 'Mock SEO', 'Rejected', 'error' ) . item( 'Mock Forms' ) . '</ul>',
	'dpo.php'            => '<ul class="wporg-sidebar-links"><li><a href="#">Search erasures</a></li><li><a href="#">Search exports</a></li></ul><ul class="wporg-sidebar-items"><li class="wporg-sidebar-item"><span class="wporg-sidebar-item-title">Export</span> <span class="wporg-sidebar-badge is-success">Completed</span><div class="wporg-sidebar-item-meta">2026-01-01</div></li></ul>',
	default              => '',
};

respond( 200, array( 'html' => $html ) );
