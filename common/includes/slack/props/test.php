<?php

namespace Dotorg\Slack\Props;

$config = dirname( dirname( dirname( __DIR__ ) ) ) . '/dotorg/slack/props.php';

if ( file_exists( $config ) ) {
	// If the endpoint exists, use it to load everything
	require( $config );
} else {
	// Otherwise, load stuff manually
	require dirname( dirname( __DIR__ ) ) . '/slack-config.php';
	require __DIR__ . '/lib.php';
}

$data = array(
	'token' => WEBHOOK_TOKEN,
	'user_name' => 'nb',
	'text' => 'mdawaffe very props',
	'command' => '/props',
	'user_id' => 'U02S3PS2B',
	'team_id' => '1',
);

run( $data, true );
