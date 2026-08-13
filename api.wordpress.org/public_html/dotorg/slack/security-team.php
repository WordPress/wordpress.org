<?php
namespace Dotorg\Slack\Security_Team;

if ( ! isset( $GLOBALS['wpdb'] ) ) {
	require dirname( __DIR__, 2 ) . '/includes/hyperdb/bb-10-hyper-db.php';
}

require dirname( __DIR__, 2 ) . '/includes/slack-config.php';

/*
 * Standalone when requested directly: it loads HyperDB but not WordPress, so nothing is
 * slashed. (Also included as a library by trac/mentions-handler.php, which skips the
 * request handling below.) The Trac server authenticates with the shared API_TOKEN secret.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
 */

function slack_api( $method, $content = array() ) {
	$content['token'] = SLACK_TOKEN;
	$content = http_build_query( $content );
	$context = stream_context_create( array(
	    'http' => array(
		'method'  => 'POST',
		'header'  => 'Content-Type: application/x-www-form-urlencoded' . PHP_EOL,
		'content' => $content,
	    ),
	) );

	$response = file_get_contents( 'https://slack.com/api/' . $method, false, $context );
	return json_decode( $response, true );
}

/**
 * Returns array of security team members by specified field value.
 *
 * @param string $user_field Optional. The user column value to return for each security team member. Defaut 'user_login'.
 * @return array
 */
function get_security_team( $user_field = 'user_login' ) {
	global $wpdb;
	$group = slack_api( 'groups.info', array( 'channel' => SECURITY_GROUP_ID ) );

	if ( empty( $group['ok'] ) ) {
		return false;
	}

	$slack_user_ids = $group['group']['members'];
	$slack_user_ids = array_filter( $slack_user_ids, function( $user_id ) {
		return (bool) preg_match( '/^U[A-Z0-9]+$/', $user_id );
	});
	$slack_user_ids_for_sql = "'" . implode( "', '", $slack_user_ids ) . "'";
	$user_ids = $wpdb->get_col( "SELECT user_id FROM slack_users WHERE slack_id IN ($slack_user_ids_for_sql)" );

	$user_ids = array_map( 'intval', $user_ids );
	$user_ids_for_sql = implode( ', ', $user_ids );

	// Whitelist user field before using.
	if ( ! in_array( $user_field, array( 'ID', 'user_email', 'user_login', 'user_nicename', 'display_name' ) ) ) {
		$user_field = 'user_login';
	}

	$user_logins = $wpdb->get_col( "SELECT $user_field FROM $wpdb->users WHERE ID IN ($user_ids_for_sql)" );
	return $user_logins;
}

function api_call() {
	header( 'Content-type: text/plain' );

	// Confirm it came from the Trac server.
	if ( ! is_string( $_GET['token'] ?? null ) || ! hash_equals( API_TOKEN, $_GET['token'] ) ) {
		exit;
	}

	$user_field = 'user_login';
	if ( isset( $_GET['user_field'] ) && 'user_email' === $_GET['user_field'] ) {
		$user_field = 'user_email';
	}

	$team = get_security_team( $user_field );
	if ( $team === false ) {
		exit;
	}

	// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain-text list; trailing newline critical.
	echo implode( "\n", $team ) . "\n";
	exit;
}

// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- substring comparison only.
if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], '/security-team.php?token=' ) ) {
	api_call();
}
