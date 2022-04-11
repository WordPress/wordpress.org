<?php

namespace Dotorg\Slack\Props;

$config = dirname( __DIR__, 4 ) . '/dotorg/slack/props.php';

if ( file_exists( $config ) ) {
	// If the endpoint exists, use it to load everything
	require_once $config;
} else {
	// Otherwise, load stuff manually
	require_once dirname( __DIR__, 3 ) . '/slack-config.php';
	require_once __DIR__ . '/lib.php';
}

$data = array(
	'token'     => WEBHOOK_TOKEN,
	'user_name' => 'nb',
	'text'      => 'mdawaffe very props',
	'command'   => '/props',
	'user_id'   => 'U02S3PS2B',
	'team_id'   => '1',
);

echo run( $data, true );
