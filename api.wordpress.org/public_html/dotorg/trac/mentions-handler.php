<?php

define( 'BLOCKED',    0 );
define( 'SUBSCRIBED', 1 );
define( 'MENTIONED',  2 );

require dirname( dirname( __DIR__ ) ) . '/includes/slack-config.php';

if ( ! isset( $_POST['secret'] ) || $_POST['secret'] !== \Dotorg\Slack\Trac\URL_SECRET__MENTIONS ) {
	exit;
}

$payload = json_decode( $_POST['payload'] );

$_SERVER['HTTP_HOST'] = 'wordpress.org';
require WPORGPATH . 'wp-load.php';

require_once WP_PLUGIN_DIR . '/wporg-notifications.php';
$notif = WPOrg_Notifications::$instance;

$notif->plugins_loaded();

$type = $payload->type === 'comment' ? 'comment' : 'ticket';

if ( 'comment' === $type ) {
	$search_text = $payload->comment;
	// Remove reply (quoted) text.
	$search_text = preg_replace( "/^>.*\n\n/sm", '', $search_text );
	$user_login  = $payload->author;
} else {
	$search_text = $payload->summary . ' ' . $payload->description;
	$user_login  = $payload->reporter;
}

$user = get_user_by( 'login', $user_login );

function wporg_user_has_visited_trac( $user_login ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM trac_users WHERE user_login = %s", $user_login ) );
}

function wporg_get_trac_ticket_subscription_status( $username, $ticket ) {
	global $wpdb;
	add_db_table( 'trac_core', '_ticket_subs' ); // HyperDB
	$status = $wpdb->get_var( $wpdb->prepare(
		"SELECT status FROM _ticket_subs WHERE username = %s AND ticket = %s",
		$username, $ticket
	) );

	if ( is_numeric( $status ) ) {
		return (int) $status;
	}
	return false;
}

if ( ! wporg_user_has_visited_trac( $user_login ) ) {
	$wpdb->insert( 'trac_users', compact( 'user_login' ) );
}

add_filter( 'wporg_notifications_notify_username', function( $notify, $username ) use ( $type, $payload, $wpdb ) {
	// Core Trac has notifications configured, see if the user has blocked the ticket.
	if ( $payload->trac === 'core' ) {
		$status = wporg_get_trac_ticket_subscription_status( $username, $payload->ticket_id );
		if ( BLOCKED === $status ) {
			return false;
		}
	}

	if ( $type === 'ticket' ) {
		// Don't need a query to say we can notify the owner and reporter.
		if ( $username === $payload->owner || $username === $payload->reporter ) {
			return true;
		}
	}

	if ( wporg_user_has_visited_trac( $username ) ) {
		// If on Core Trac, a user is not a reporter, owner, or subscriber, subscribe them.
		if ( isset( $status ) && false === $status ) {
			$wpdb->insert( '_ticket_subs', array(
				'username' => $username,
				'ticket'   => $payload->ticket_id,
				'status'   => MENTIONED,
			) );
		}

		return true;
	}
	return $notify;
}, 10, 2 );

$notif->match_notify( array(
	'author_id'   => $user->ID,
	'object'      => $payload,
	'search_text' => $search_text,
	'type'        => "trac_$type",
) );

