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
$author      = 'comment' === $type ? $payload->author  : $payload->reporter;
$author_obj  = get_user_by( 'login', $author );

if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT user_login FROM trac_users WHERE user_login = %s", $author ) ) ) {
	$wpdb->insert( 'trac_users', array( 'user_login' => $author ) );
}

$notif->match_notify( array(
	'author_id'   => $author_obj->ID,
	'object'      => $payload,
	'search_text' => $search_text,
	'type'        => "trac_$type",
) );

