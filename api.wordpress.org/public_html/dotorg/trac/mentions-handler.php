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

if ( $payload->type === 'comment' ) {
	$notif->match_notify( array(
		'author_id'   => get_user_by( 'login', $payload->author )->ID,
		'object'      => $payload,
		'search_text' => $payload->comment,
		'type'        => 'trac_comment',
	) );
} else {
	$notif->match_notify( array(
		'author_id'   => get_user_by( 'login', $payload->reporter )->ID,
		'object'      => $payload,
		'search_text' => $payload->summary . ' ' . $payload->description,
		'type'        => 'trac_ticket',
	) );
}

