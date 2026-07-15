<?php

// Allow committers to publicly mention other committers via @committers.

namespace Dotorg\Slack\Committers;

require dirname( dirname( __DIR__ ) ) . '/includes/slack-config.php';

if ( ! hash_equals( WEBHOOK_TOKEN, $_POST['token'] ) ) {
	return;
}

echo json_encode( array(
	'username'   => 'wordpressdotorg',
	'link_names' => 1,
	'text'       => sprintf( '@%s: Use the `/committers` command.', $_POST['user_name'] ),
) );

exit;
