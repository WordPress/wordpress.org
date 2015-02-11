<?php

namespace Dotorg\Slack\Announce;

// Required function: get_whitelist()
// Optional function: get_avatar()

/**
 * Returns a whitelist of users by channel.
 *
 * The array keys are the channel name (omit #) and the
 * values are an array of users.
 */
function get_whitelist() {
	return array(
		'bbpress' => array( 
			'jjj', 
			'netweb', 
		),
		'buddypress' => array( 
			'boone', 
			'djpaul', 
			'jjj',
		),
		'cli' => array(
			'danielbachhuber',
		),
		'core' => array(
			'johnbillion',
			'drew',
		),
		'core-flow' => array(
			'drew',
			'boren',
		),
		'polyglots' => array(
			'petya',
		),
		'training' => array(
			'courtneydawn',
			'liljimmi',
        ),  
		'wptv' => array(
			'jerrysarcastic',
		),
	);
}
