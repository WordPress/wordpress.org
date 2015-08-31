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
		'accessibility' => array( 
			'afercia', 
			'joedolson', 
			'rianrietveld', 
		),
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
			'wonderboymusic',
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
		'core-fields' => array(
			'helen',
			'sc0ttkclark',
		),
		'core-flow' => array(
			'drew',
		),
		'core-multisite' => array(
			'jeremyfelt',
		),
		'core-passwords' => array(
			'georgestefanis',
			'valendesigns',
		),
		'core-restapi' => array(
			'danielbachhuber',
			'joehoyle',
			'rachelbaker',
			'rmccue',
		),
		'core-themes' => array(
			'karmatosed',
			'iamtakashi',
		),
		'design' => array(
			'helen',
			'melchoyce',
		),
		'feature-oembed' => array(
			'swissspidy',
		),
		'feature-respimg' => array(
			'joemcgill',
			'mike',
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

function get_committers() {
	return array(
		'dd32', 'nacin', 'mark', 'helen', 'azaozz', 'westi',
		'nb', 'sergey', 'ocean90', 'wonderboymusic', 'drew', 'johnbillion',
		'jorbin', 'boone', 'jeremyfelt', 'pento', 'obenland', 'iseulde',
		'westonruter',
	);
}

