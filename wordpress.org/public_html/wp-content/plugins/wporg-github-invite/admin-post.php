<?php
namespace WordPressdotorg\GitHub\MakeInviter;
use WordPressdotorg\MU_Plugins\Utilities\Github_App_Authorization;
use WP_Error;

/**
 * Process the invitation.
 */
add_action( 'admin_post_github_invite', function() {
	global $wpdb;

	if ( ! current_user_can( PERMISSION ) ) {
		wp_die( 'You do not have permission to do this' );
	}

	check_admin_referer( 'github_invite' );

	$input    = wp_unslash( $_POST['invite'] );
	$team_ids = (array) wp_unslash( $_POST['team_id'] );
	$team_ids = array_intersect( $team_ids, get_allowed_teams() );
	$team_ids = array_map( 'intval', $team_ids );

	$updated         = 'success';
	$message         = null;
	$invite          = false;
	$github_username = false;

	if ( ! $team_ids ) {
		$updated = 'error';
		$message = 'No teams selected';
	} elseif ( is_email( $input ) ) {
		$invite = $input;
	} elseif ( preg_match( '!^https://github.com/(?<slug>[^/]+)!i', $input, $m ) ) {
		$gh_user         = api( '/users/' . $m['slug'] );
		$github_username = $gh_user->login ?? false;
		$invite          = $gh_user->id ?? 0;
	} else {
		preg_match( '!^https://profiles.wordpress.org/(?<slug>[^/]+)!i', $input, $m );

		$user           = get_user_by( 'slug', $m['slug'] ?? $input );
		$github_details = $wpdb->get_row( $wpdb->prepare(
			'SELECT github_user, user_details FROM wporg_github_users WHERE user_id = %d',
			$user->ID
		) );
		if ( $github_details ) {
			$github_details->user_details = json_decode( $github_details->user_details );
		}

		if ( ! $user || ! $github_details || ! $github_details->user_details ) {
			$updated = 'no-github';
		} else {
			$invite          = $github_details->user_details->id;
			$github_username = $github_details->github_user;
		}
	}

	if ( $invite ) {
		$result = invite_member( $invite, $team_ids );

		if (
			is_wp_error( $result ) &&
			$github_username &&
			'Invitee is already a part of this organization' === $result->get_error_data( 'api_error' )->errors[0]->message ?? ''
		) {
			// Already a GitHub member, just add them to the team.
			$result  = add_to_team( $github_username, $team_ids );
			$message = 'User already a GitHub member, Added to team(s).';
		}

		if ( ! is_wp_error( $result ) ) {
			if ( ! empty( $result->id ) ) {
				// Note that it was invited via this site..
				$invited_gh_users = get_option( 'invited_gh_users', [] );
				$invited_gh_users[] = $result->id;
				update_option( 'invited_gh_users', $invited_gh_users );

				delete_site_transient( 'gh_invites' );
			}

			// Log it to Slack.
			$teams          = get_teams();
			$readable_teams = array_map( static function( $id ) use( $teams ) {
				return array_values( wp_list_filter( $teams, [ 'id' => $id ] ) )[0]->name ?? $id;
			}, $team_ids );

			$log = sprintf(
				'`%s` invited to organisation by `%s` to team(s) `%s`',
				$github_username ?: $invite,
				wp_get_current_user()->user_login,
				implode( ', ', $readable_teams )
			);

			function_exists( 'slack_dm' ) && slack_dm( $log, SLACK_CHANNEL );
		}

		if ( isset( $result->errors ) ) {
			$updated = 'error';
			$message = $result->errors[0]->message ?? '';
		} elseif ( $result->login ?: $result->email ) {
			$message = sprintf( 'Invited User: %s', $result->login ?: $result->email );
		}
	}

	wp_safe_redirect(
		add_query_arg(
			compact( 'updated', 'message' ),
			admin_url( 'tools.php?page=gh_invite_collaborator' )
		)
	);
	die();
} );

/**
 * Cancel an invitation.
 */
add_action( 'admin_post_github_cancel_invite', function() {
	if ( ! current_user_can( PERMISSION ) ) {
		wp_die( 'You do not have permission to do this' );
	}

	$id = (int) wp_unslash( $_GET['invite'] );

	check_admin_referer( 'github_cancel_invite_' . $id );

	$invite = array_values( wp_list_filter( get_pending_invites(), [ 'id' => $id ] ) )[0] ?? null;

	cancel_invite( $id );

	// Log it to Slack.
	$log = sprintf(
		'`%s` invite canceled by `%s`.',
		$invite->login ?: $invite->email,
		wp_get_current_user()->user_login
	);
	function_exists( 'slack_dm' ) && slack_dm( $log, SLACK_CHANNEL );

	delete_site_transient( 'gh_invites' );

	wp_safe_redirect( admin_url( 'tools.php?page=gh_invite_collaborator&updated=canceled' ) );
	die();
} );

/**
 * Allow a super-admin to specify which teams a user may be invited to from this site.
 */
add_action( 'admin_post_github_invite_settings', function() {
	if ( ! is_super_admin() || ! current_user_can( PERMISSION ) ) {
		wp_die( 'You do not have permission to do this' );
	}

	check_admin_referer( 'github_invite_settings' );

	$team_ids = wp_unslash( $_POST['team_id'] );
	$team_ids = array_map( 'intval', $team_ids );

	update_option( 'gh_invite_allowed_teams', $team_ids );

	wp_safe_redirect( admin_url( 'tools.php?page=gh_invite_collaborator&updated=settings' ) );
	die();
} );
