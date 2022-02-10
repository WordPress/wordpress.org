<?php
namespace WordPressdotorg\API\Trac\GithubPRs;

require dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-init.php';
require __DIR__ . '/functions.php';

$trac          = preg_replace( '![^a-z]!', '', $_GET['trac'] ?? '' );
$ticket        = intval( $_GET['ticket'] ?? 0 );
$author        = wp_unslash( $_GET['author'] ?? '' );
$authenticated = ! empty( $_GET['authenticated'] ); // Longer caches for logged out requests.

header( 'Content-Type: application/json' );
header( 'Access-Control-Allow-Origin: *' );

if ( empty( $trac ) || ( empty( $ticket ) && empty( $author ) ) ) {
	header( 'HTTP/1.0 400 Bad Request' );
	die( '{"error":"Trac, Ticket number, or Author is invalid."}' );
}

// Type one: Return PRs by Author.
if ( $author ) {
	header( 'Cache-Control: max-age=' . HOUR_IN_SECONDS );
	header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + HOUR_IN_SECONDS ) );

	$user_id = get_user_by( 'slug', $author )->ID ?? 0;

	$tickets = $wpdb->get_col( $wpdb->prepare(
		"SELECT `ticket`
		FROM `trac_github_prs`
		WHERE trac = %s AND author = %d",
		$trac,
		$user_id
	) );

	echo wp_json_encode( $tickets );
	die();
}

// Fetch any linked PRs
$prs = $wpdb->get_results( $wpdb->prepare(
	"SELECT `repo`, `pr`, `data`, `last_checked`, `author`
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
			strtotime( $data->last_checked ) <= time() - 2*60
		) ||
		// or unit tests are running, then 2min.
		(
			$data->data->check_runs
			&&
			in_array( 'in_progress', (array) $data->data->check_runs )
			&&
			strtotime( $data->last_checked ) <= time() - 2*60
		)
	) {
		$pr_data = fetch_pr_data( $data->repo, $data->pr );

		if ( $pr_data ) {
			$data->data = $pr_data;

			// TODO: catch the trac ticket changing and update the database.
			unset( $data->data->trac_ticket );

			// Check if we now have an author for this PR, the author may link their account after creating the PR.
			if ( ! $data->author ) {
				$data->author = (int) find_wporg_user_by_github( $pr_data->user->name, 'ID' );
			}

			$wpdb->update(
				'trac_github_prs',
				[
					'data'         => json_encode( $pr_data ),
					'last_checked' => gmdate( 'Y-m-d H:i:s' ),
					'author'       => $data->author,
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
		if ( strtotime( $pr->data->updated_at ) > time() - 30*60 ) {
			$expiry = min( $expiry, 2*60 );
		} elseif ( strtotime( $pr->data->updated_at ) > time() - 7*24*60*60 ) {
			$expiry = min( $expiry, 5*60 );
		}
	}
}

header( 'Cache-Control: max-age=' . $expiry );
header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + $expiry ) );

// Only return the actual PR data needed
$prs = array_column( $prs, 'data' );

echo wp_json_encode( $prs );