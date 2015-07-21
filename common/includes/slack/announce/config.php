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
			'drew',
			'helen',
			'johnbillion',
			'obenland',
		),
		'core-customize' => array(
			'celloexpressions',
			'ocean90',
			'westonruter',
		),
		'core-editor' => array(
			'azaozz',
			'iseulde',
		),
		'core-flow' => array(
			'drew',
		),
		'design' => array(
			'helen',
			'melchoyce',
		),
		'feature-oembed' => array(
			'swissspidy',
		),
		'forums' => array(
			'clorith',
			'ipstenu',
			'jan_dembowski',
			'macmanx',
		),
		'meta-i18n' => array(
			'ocean90',
		),
		'polyglots' => array(
			'japh',
			'ocean90',
			'petya',
			'shinichin',
		),
		'themereview' => array(
			'cais',
			'chipbennett',
			'emiluzelac',
			'grapplerulrich',
			'greenshady',
			'jcastaneda',
			'karmatosed',
		),  
		'training' => array(
			'courtneydawn',
			'liljimmi',
			'bethsoderberg',
			'courtneyengle',
		),  
		'wptv' => array(
			'jerrysarcastic',
			'roseapplemedia',
		),
	);
}
