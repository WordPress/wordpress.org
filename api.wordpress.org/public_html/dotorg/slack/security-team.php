<?php

namespace {
	if ( ! isset( $GLOBALS['wpdb'] ) ) {
		require dirname( dirname( __DIR__ ) ) . '/includes/hyperdb/bb-10-hyper-db.php';
	}
}

namespace Dotorg\Slack\Security_Team {

require dirname( dirname( __DIR__ ) ) . '/includes/slack-config.php';

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

function get_security_team() {
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
	$user_logins = $wpdb->get_col( "SELECT user_login FROM $wpdb->users WHERE ID IN ($user_ids_for_sql)" );
	return $user_logins;
}

function api_call() {
	header( 'Content-type: text/plain' );

	// Confirm it came from the Trac server.
	if ( $_GET['token'] !== API_TOKEN ) {
		exit;
	}

	$team = get_security_team();
	if ( $team === false ) {
		exit;
	}

	echo implode( "\n", $team ) . "\n"; // Trailing newline critical.
	exit;
}

if ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], '/security-team.php?token=' ) ) {
	api_call();
}

}
