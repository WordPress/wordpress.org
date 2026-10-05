<?php
/**
 * Mock of the api.wordpress.org/dotorg/freescout/ endpoints.
 *
 * Checks requests like the real endpoints (signature, JSON object, age, endpoint; not nonces), and answers sidebar requests with sample panels that include some of the data it received.
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

$email   = (string) ( $request->sender->email ?? '' );
$mailbox = (string) ( $request->mailbox->name ?? '' );
$threads = count( $request->threads ?? array() );

/**
 * A sample plugin, like plugins-themes.php sends.
 *
 * @param string $name   Plugin name.
 * @param string $status Badge text, empty for a published plugin.
 * @param string $tone   Badge tone.
 * @return array
 */
function item( string $name, string $status = '', string $tone = 'neutral' ): array {
	$slug = strtolower( str_replace( ' ', '-', $name ) );

	return array(
		'title'  => $name,
		'url'    => 'https://wordpress.org/plugins/wp-admin/post.php?action=edit&post=1',
		'badges' => $status ? array( badge( $status, $tone ) ) : array(),
		'meta'   => array(
			array( 'text' => $slug ),
			array(
				'text'    => 'Updated 3 weeks ago',
				'tooltip' => '2026-01-01',
			),
		),
		'links'  => array(
			array(
				'text' => 'View on WordPress.org',
				'url'  => 'https://wordpress.org/plugins/' . $slug . '/',
				'icon' => 'link',
			),
			array(
				'text' => 'Download',
				'url'  => 'https://downloads.wordpress.org/plugin/' . $slug . '.latest-stable.zip',
				'icon' => 'download-alt',
			),
		),
	);
}

/**
 * A badge.
 *
 * @param string $label Text.
 * @param string $tone  Tone.
 * @return array
 */
function badge( string $label, string $tone ): array {
	return array(
		'label' => $label,
		'tone'  => $tone,
	);
}

/**
 * A list of items.
 *
 * @param array ...$items Items.
 * @return array
 */
function items( array ...$items ): array {
	return array(
		'type'  => 'items',
		'items' => $items,
	);
}

$user = (string) strtok( $email, '@' );

// Sample panels like the real endpoints send, so the sidebar's styles can be worked on locally.
$blocks = match ( $endpoint ) {
	'profile.php'        => array(
		array(
			'type' => 'lead',
			'text' => $user,
			'url'  => 'https://profiles.wordpress.org/' . rawurlencode( $user ) . '/',
		),
		array(
			'type' => 'meta',
			'text' => sprintf( 'Mock: %d threads from %s', $threads, $mailbox ),
		),
		array(
			'type'  => 'links',
			'links' => array(
				array(
					'text' => 'Account & Security',
					'url'  => 'https://profiles.wordpress.org/',
				),
				array(
					'text' => 'Forum Profile',
					'url'  => 'https://wordpress.org/support/',
				),
				array(
					'text' => 'Search pending signups',
					'url'  => 'https://login.wordpress.org/',
				),
			),
		),
		array(
			'type' => 'heading',
			'text' => 'Slack',
		),
		items(
			array(
				'title'  => $user,
				'url'    => 'https://wordpress.slack.com/',
				'badges' => array( badge( 'Active', 'success' ) ),
				'meta'   => array( array( 'text' => 'Updated 2026-01-01' ) ),
			),
			array(
				'title'  => $user . '-old',
				'url'    => 'https://wordpress.slack.com/',
				'badges' => array( badge( 'Deactivated', 'error' ) ),
				'meta'   => array( array( 'text' => 'Updated 2019-06-01' ) ),
			)
		),
	),
	'forums.php'         => array(
		items(
			array(
				'note' => 'Mock note: asked to stop bumping their topics.',
				'meta' => array(
					array(
						'text' => 'January 1, 2026',
						'url'  => 'https://wordpress.org/support/',
					),
					array( 'text' => 'moderator' ),
				),
			)
		),
	),
	'plugins-themes.php' => array(
		array(
			'type'  => 'heading',
			'text'  => 'Plugins mentioned',
			'count' => 1,
		),
		items( item( 'Mock Plugin', 'In Review', 'warning' ) ),
		array(
			'type'  => 'heading',
			'text'  => 'Plugins owned',
			'url'   => 'https://wordpress.org/plugins/wp-admin/edit.php',
			'count' => 7,
		),
		items( item( 'Mock Plugin', 'In Review', 'warning' ), item( 'Hello Mock' ), item( 'Mock Blocks', 'Approved', 'success' ), item( 'Old Mock', 'Closed: Author Request', 'error' ), item( 'Mock Widgets' ), item( 'Mock SEO', 'Rejected', 'error' ), item( 'Mock Forms' ) ),
		array(
			'type'  => 'heading',
			'text'  => 'Themes owned',
			'url'   => 'https://wordpress.org/themes/wp-admin/edit.php',
			'count' => 2,
		),
		items( item( 'Mock Theme' ), item( 'Twenty Mock', 'Suspended', 'error' ) ),
	),
	'dpo.php'            => array(
		array(
			'type'  => 'links',
			'links' => array(
				array(
					'text' => 'Search erasures',
					'url'  => 'https://wordpress.org/wp-admin/erase-personal-data.php',
				),
				array(
					'text' => 'Search exports',
					'url'  => 'https://wordpress.org/wp-admin/export-personal-data.php',
				),
			),
		),
		items(
			array(
				'title'   => 'Export',
				'tooltip' => 'Created: 2026-01-01 00:00:00',
				'badges'  => array( badge( 'Completed', 'success' ) ),
				'meta'    => array( array( 'text' => '2026-01-01' ) ),
			)
		),
	),
	default              => array(),
};

respond( 200, array( 'blocks' => $blocks ) );
