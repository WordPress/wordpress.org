<?php
/**
 * Redirect endpoint for event tracking.
 *
 * This file is intended to be a simple endpoint that can generate some basic
 * stats on our events API.
 */
$base_dir = dirname( dirname( __DIR__ ) );
require_once $base_dir . '/init.php';
require_once $base_dir . '/includes/hyperdb/bb-10-hyper-db.php';

// for bump_stats_extra().
include_once WPORGPATH . 'wp-content/mu-plugins/1-stats-extra.php';

allow_cors_requests();
header( 'Cache-Control: private, max-age=86400' ); // Cache in browsers for an hour, but not server.

$event     = false;
$url       = urldecode( $_REQUEST['url'] ); // Fallback incase of unexpected DB failure.
$type      = '';
$source_id = '';
foreach ( [ 'meetup', 'wordcamp' ] as $field ) {
	if ( isset( $_REQUEST[ $field ] ) ) {
		$type = $field;
		$source_id = (int) $_REQUEST[ $field ];
		break;
	}
}

// Fetch the event, redirect to the stored URL.
if ( $type && $source_id ) {
	$event = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT url, country
			FROM wporg_events
			WHERE type = %s AND source_id = %d
			LIMIT 1",
			$type,
			$source_id
		)
	);
}

if ( ! empty( $event->url ) ) {
	$url = $event->url; // We trust the URL we've stored.
} else {
	// If no event, validate the provided $url and redirect there.
	$type = 'unknown-' . $type;
	// Only allow redirects to known domains.
	if ( ! preg_match( '#^https?://([^/]+\.)?(meetup.com|wordpress.org|wordcamp.org|doaction.org)/#i', $url ) ) {
		$url = 'https://events.wordpress.org/';
	}

	// We could just sign the request, but for simplicity, we'll just use the above validation.
}

// Redirect
header( 'Location: ' . $url, true, 302 );

if ( function_exists( 'fastcgi_finish_request' ) ) {
	fastcgi_finish_request();
}

if ( function_exists( 'bump_stats_extra' ) ) {
	bump_stats_extra( 'events-clicks', $type );
	if ( $event ) {
		bump_stats_extra( 'events-clicks-country', strtoupper( $event->country ) );
	}
	if ( isset( $_GET['ref'] ) && in_array( $_GET['ref'], [ 'core', 'api', 'events', 'email' ], true ) ) {
		bump_stats_extra( 'events-clicks-ref', $_GET['ref'] );
	}
}

/*
if ( $type && $source_id && $event ) {
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE wporg_events SET clicks = clicks + 1 WHERE type = %s AND source_id = %d",
			$type,
			$source_id
		)
	);
}
*/