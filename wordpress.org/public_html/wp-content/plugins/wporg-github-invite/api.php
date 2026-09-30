<?php
namespace WordPressdotorg\GitHub\MakeInviter;
use WordPressdotorg\MU_Plugins\Utilities\Github_App_Authorization;
use WP_Error;

/**
 * Get the allowed teams for this site.
 */
function get_allowed_teams() {
	$allowed_teams = array_map( 'intval', get_option( 'gh_invite_allowed_teams', array() ) );

	return array_diff( $allowed_teams, get_never_teams() );
}

/**
 * A list of teams that should never be selected.
 */
function get_never_teams() {
	return [
		1114244, // Security team.
		80104, // Another special team
	];
}

/**
 * Fetch the teams from the WordPress GitHub organization
 */
function get_teams() {
	$teams = get_site_transient( 'gh_teams', false );
	if ( false === $teams ) {
		$teams = api( '/orgs/{ORG}/teams?per_page=100' );

		set_site_transient( 'gh_teams', $teams, 5 * MINUTE_IN_SECONDS );
	}

	if ( is_wp_error( $teams ) ) {
		return [];
	}

	return $teams;
}

/**
 * Fetch the pending invites from the WordPress GitHub organization
 */
function get_pending_invites() {
	$invites = get_site_transient( 'gh_invites', false );
	if ( false === $invites ) {
		$invites = api( '/orgs/{ORG}/invitations' );

		set_site_transient( 'gh_invites', $invites, 5 * MINUTE_IN_SECONDS );
	}

	if ( is_wp_error( $invites ) ) {
		return [];
	}

	return $invites;
}

/**
 * Invite a member to the organisation, with specific Teams.
 *
 * @param int|string $who The GitHub user ID, or email of the user to invite.
 */
function invite_member( $who, array $team_ids ) {
	$args = [
		'role'       => 'direct_member',
		'team_ids'   => $team_ids
	];

	if ( is_int( $who ) ) {
		$args['invitee_id'] = $who;
	} else {
		$args['email'] = $who;
	}

	return api(
		'/orgs/{ORG}/invitations',
		$args
	);
}

/**
 * Add an organisation member to a team.
 *
 * @param int|string $who      The GitHub user login.
 * @param array      $team_ids The team IDs to add the user to.
 */
function add_to_team( $who, array $team_ids ) {
	$error = new WP_Error;

	foreach ( $team_ids as $team_id ) {
		$response = api(
			"/orgs/{ORG}/team/{$team_id}/memberships/{$who}",
			false,
			'PUT'
		);

		if ( is_wp_error( $response ) ) {
			$error->merge_from( $response );
		}
	}

	if ( $error->get_error_code() ) {
		return $error;
	}

	return $response;
}

/**
 * Cancel an invitation by ID
 */
function cancel_invite( $id ) {
	return api(
		'/orgs/{ORG}/invitations/' . $id,
		[],
		'DELETE'
	);
}

/**
 * Quick GitHub API method.
 *
 * @param string $endpoint The API endpoint to call.
 * @param mixed  $body     The body to send with the request.
 */
function api( $endpoint, $body = false, $method = null ) {
	static $github_app = null;

	// Setup the App if needed.
	$github_app ??= new Github_App_Authorization( APP_ID, KEY );
	$method     ??= $body ? 'POST' : 'GET';

	$args = array(
		'method'  => $method,
		'headers' => array(
			'Accept'               => 'application/vnd.github+json',
			'Content-Type'         => 'application/json',
			'X-GitHub-Api-Version' => '2022-11-28'
		),
	);

	if ( $body ) {
		$args['body'] = json_encode( $body );
	}

	$response = $github_app->request( $endpoint, $args );

	if ( is_wp_error( $response ) ) {
		// Includes the HTTP response code only due to privacy concerns.
		return new WP_Error( 'http_error', 'GitHub API error: ' . wp_remote_retrieve_response_code( $response ) );
	}

	$json = json_decode( $response['body'] );
	
	if ( 200 != wp_remote_retrieve_response_code( $response ) && isset( $json->message ) ) {
		return new WP_Error( 'api_error', 'GitHub API error: ' . $json->message, $json );
	}

	return $json;
}