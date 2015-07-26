<?php

// Allow committers to publicly mention other committers via @committers.

namespace Dotorg\Slack\Committers;

require dirname( dirname( __DIR__ ) ) . '/includes/slack-config.php';

if ( $_POST['token'] !== WEBHOOK_TOKEN ) {
	return;
}

echo json_encode( array(
	'username'   => 'wordpressdotorg',
	'link_names' => 1,
	'text'       => sprintf( '@%s: Use the `/committers` command.', $_POST['user_name'] ),
) );

exit;
