<?php
namespace WordPressdotorg\API\Trac\GithubPRs;

require dirname( dirname( dirname( __DIR__ ) ) ) . '/init.php';
require dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/hyperdb/bb-10-hyper-db.php';
require dirname( dirname( dirname( __DIR__ ) ) ) . '/includes/wp-json-encode.php';

require __DIR__ . '/functions.php';

$trac          = preg_replace( '![^a-z]!', '', $_GET['trac'] ?? '' );
$ticket        = intval( $_GET['ticket'] ?? 0 );
$authenticated = ! empty( $_GET['authenticated'] ); // Longer caches for logged out requests.

if ( empty( $trac ) || empty( $ticket ) ) {
	header( 'HTTP/1.0 400 Bad Request' );
	header( 'Content-Type: application/json' );
	die( '{"error":"Ticket number is invalid."}' );
}

// Fetch any linked PRs
$prs = $wpdb->get_results( $wpdb->prepare(
	"SELECT `repo`, `pr`, `data`, `last_checked`
	FROM `trac_github_prs`
	WHERE trac = %s AND ticket = %s",
	$trac,
	$ticket
) );

// Expand the JSON `data` field.
array_walk( $prs, function( $data ) {
	$data->data = json_decode( $data->data ) ?: false;
	return $data;
} );

// Refresh any data that's needed
// Rules:
//  - 5 minutes for logged in requests, 60 mins for unauthenticated.
//  - If PR created/updated in last half hour, every two minutes
array_walk( $prs, function( $data ) use ( $authenticated ) {
	global $wpdb;

	if (
		// If no data..
		! $data->data ||
		// or it's out of date..
		strtotime( $data->last_checked ) <= time() - ($authenticated ? 5*60 : 60*60) ||
		// or the PR was created/updated within the last 30 minutes AND is more than 2 minutes out of date
		(
			strtotime( $data->data->updated_at ) > time() - 30*60
			&&
			strtotime( $data->last_checked_at ) <= time() - 2*60
		)
	) {
		$pr_data = fetch_pr_data( $data->repo, $data->pr );

		if ( $pr_data ) {
			$data->data = $pr_data;

			// TODO: catch the trac ticket changing and update the database.
			unset( $data->data->trac_ticket );

			$wpdb->update(
				'trac_github_prs',
				[
					'data'         => json_encode( $pr_data ),
					'last_checked' => gmdate( 'Y-m-d H:i:s' ),
				],
				[
					'repo' => $data->repo,
					'pr'   => $data->pr
				]
			);
		}
	}

	return $data;
} );

// Expiry is an hour for everyone..
// ..unless authenticated and a linked PR has changed within the last week, then 5 min.
// ..unless the PR is created/updated within the 30 min, in which case 2min
$expiry = 60*60;
if ( $authenticated ) {
	foreach ( $prs as $pr ) {
		if ( strtotime( $pr->updated_at ) > time() - 30*60 ) {
			$expiry = min( $expiry, 2*60 );
		} elseif ( strtotime( $pr->updated_at ) > time() - 7*24*60*60 ) {
			$expiry = min( $expiry, 5*60 );
		}
	}
}

header( 'Cache-Control: max-age=' . $expiry );
header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + $expiry ) );
header( 'Content-Type: application/json' );
header( 'Access-Control-Allow-Origin: *' );

// Only return the actual PR data needed
$prs = array_column( $prs, 'data' );

echo wp_json_encode( $prs );
