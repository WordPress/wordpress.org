<?php

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

$search_text = 'comment' === $type ? $payload->comment : $payload->summary . ' ' . $payload->description;
$user_login  = 'comment' === $type ? $payload->author  : $payload->reporter;
$user        = get_user_by( 'login', $user_login );

function wporg_user_has_visited_trac( $user_login ) {
	global $wpdb;
	return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM trac_users WHERE user_login = %s", $user_login ) );
}

if ( ! wporg_user_has_visited_trac( $user_login ) ) {
	$wpdb->insert( 'trac_users', compact( 'user_login' ) );
}

add_filter( 'wporg_notifications_notify_username', function( $notify, $username ) use ( $type, $payload, $wpdb ) {
	if ( $type === 'ticket' ) {
		// Don't need a query to say we can notify the owner and reporter.
		if ( $username === $payload->owner || $username === $payload->reporter ) {
			return true;
		}
	}

	if ( wporg_user_has_visited_trac( $username ) ) {
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

