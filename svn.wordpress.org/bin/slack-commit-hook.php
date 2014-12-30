#!/usr/bin/env php
<?php

if ( $argc !== 4 ) {
	echo "Usage: <trac> <repo> <rev>\n";
	exit( 1 );
}

define( 'INC', dirname( __DIR__ ) . '/includes/slack-commit-hook/' );
require INC . 'sender.php';
require INC . 'trac.php';
require INC . 'config.php';

list( , $trac, $repo, $rev ) = $argv;

$slack_hook = 'https://hooks.slack.com/services/...';
$sender = new \SlackCommitHook\Sender( $trac, $repo, $rev, $slack_hook );
// $sender->use_test_channel( true );
// $sender->set_svnlook_executable( ... );
$sender->run();
