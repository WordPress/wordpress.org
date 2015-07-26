<?php

namespace {
	require dirname( dirname( __DIR__ ) ) . '/includes/hyperdb/bb-10-hyper-db.php';
	require dirname( dirname( __DIR__ ) ) . '/includes/slack-config.php';
}

namespace Dotorg\Slack\Announce {

require dirname( dirname( __DIR__ ) ) . '/includes/slack/announce/lib.php';

function get_avatar( $username, $slack_id, $team_id ) {
	global $wpdb;

	$wp_user_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT user_id FROM slack_users WHERE slack_id = %s",
		$slack_id
	) );

	$email = $wpdb->get_var( $wpdb->prepare(
		"SELECT user_email FROM $wpdb->users WHERE ID = %d",
		$wp_user_id
	) );

	$hash = md5( strtolower( trim( $email ) ) );
	return sprintf( 'https://secure.gravatar.com/avatar/%s?s=96d=mm&r=G', $hash );
}

$i = 0;
// WEBHOOK_TOKEN_1, WEBHOOK_TOKEN_2, etc.
while ( defined( __NAMESPACE__ . '\\WEBHOOK_TOKEN_' . ++$i ) ) {
	if ( constant( __NAMESPACE__ . '\\WEBHOOK_TOKEN_' . $i ) === $_POST['token'] ) {
		run( $_POST );
	}
}

}

