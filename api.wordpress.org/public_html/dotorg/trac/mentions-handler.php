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

if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM trac_users WHERE user_login = %s", $user_login ) ) ) {
	$wpdb->insert( 'trac_users', compact( 'user_login' ) );
}

$notif->match_notify( array(
	'author_id'   => $user->ID,
	'object'      => $payload,
	'search_text' => $search_text,
	'type'        => "trac_$type",
) );

