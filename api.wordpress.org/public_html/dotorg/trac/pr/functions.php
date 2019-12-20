<?php
namespace WordPressdotorg\API\Trac\GithubPRs;

/**
 * Fetches and reformats the Github PR API response to the details we need.
 */
function fetch_pr_data( $repo, $pr ) {
	$url = '/repos/' . $repo . '/pulls/' . intval( $pr );
	$data = api_request( $url );

	// Error time..
	if ( ! $data || ! $data->number ) {
		return false;
	}

	return (object) [
		'repo'            => $data->base->repo->full_name,
		'number'          => $data->number,
		'html_url'        => $data->html_url,
		'state'           => $data->state,
		'title'           => $data->title,
		'created_at'      => $data->created_at,
		'updated_at'      => $data->updated_at,
		'closed_at'       => $data->closed_at,
		'mergeable_state' => $data->mergeable_state,
		'user'            => (object) [
			'url'  => $data->user->html_url,
			'name' => $data->user->login,
		],
		'changes'         => (object) [
			'additions' => $data->additions,
			'deletions' => $data->deletions,
			'patch_url' => $data->patch_url,
			'html_url'  => $data->html_url,
		],
		'trac_ticket'    => determine_trac_ticket( $data ),
	];
}

/**
 * Find a WordPress.org user by a Github login.
 */
function find_wporg_user_by_github( $github_user ) {
	global $wpdb;

	return $wpdb->get_var( $wpdb->prepare(
		"SELECT u.user_login
			FROM wporg_github_users g
				JOIN {$wpdb->users} u ON g.user_id = u.ID
			WHERE g.github_user = %s",
		$github_user
	) );
}

/**
 * A simple wrapper to make a Github API request..
 */
function api_request( $url, $args = null, $headers = [], $method = null ) {
	// Prepend GitHub URL for relative URLs, not all API URI's are on api.github.com, which is why we support full URI's.
	if ( '/' === substr( $url, 0, 1 ) ) {
		$url = 'https://api.github.com' . $url;
	}

	$context = stream_context_create( [ 'http' => [
		'method'        => $method ?: ( is_null( $args ) ? 'GET' : 'POST' ),
		'user_agent'    => 'WordPress.org Trac; trac.WordPress.org',
		'max_redirects' => 0,
		'timeout'       => 5,
		'ignore_errors' => true,
		'header'        => array_merge(
			[
				'Accept: application/json',
				'Authorization: ' . get_authorization_token(),
			],
			$headers
		),
		'content'       => $args ?: null,
	] ] );

	return json_decode( file_get_contents(
		$url,
		false,
		$context
	) );
}

/**
 * Fetch an Authorization token for a Github API request.
 */
function get_authorization_token() {
	global $wpdb;

	// TODO: This needs to be switched to a Github App token.
	// This works temporarily to avoid the low unauthenticated limits.
	return 'BEARER ' . $wpdb->get_var( "SELECT access_token FROM wporg_github_users WHERE github_user = 'dd32'");
}

/**
 * Use some rough heuristics to find the Trac ticket for a given PR.
 * 
 * TODO: This should probably support multiple Trac Tickets, but once you start to use the final few regexes it can start to match Gutenberg references.
 */
function determine_trac_ticket( $pr ) {
	$ticket = false;

	// For now, we assume everything is destined for the Core Trac.
	switch ( $pr->base->repo->full_name ) {
		case 'WordPress/wordpress-develop':
		default:
			$trac = 'core';
			break;
	}

	$regexes = [
		'!' . $trac . '.trac.wordpress.org/ticket/(\d+)!i',
		'!(?:^|\s)#WP(\d+)!', // #WP1234
		'!(?:^|\s)#(\d{4,5})!', // #1234
		'!Ticket[ /-](\d+)!i',
		// diff filenames.
		'!\b(\d+)(\.\d)?\.(?:diff|patch)!i',
		// Formats of common branches
		'!(?:' . $trac . '|WordPress|fix|trac)[-/](\d+)!i',
		// Starts or ends with a ticketish number
		// These match things it really shouldn't, and are a last-ditch effort.
		'!\s(\d{4,5})$!i',
		'!^(\d{4,5})[\s\W]!i',
	];

	// Simple, the Trac ticket is mentioned in the title, or body.
	foreach ( $regexes as $regex ) {
		foreach ( [
			$pr->title,
			$pr->body,
			$pr->head->label,
			$pr->head->ref
		] as $field ) {
			if ( preg_match( $regex, $field, $m ) ) {
				return [ $trac, $m[1] ];
			}
		}
	}

	return false;
}

