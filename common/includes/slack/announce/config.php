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
			'mike',
			'obenland',
			'ocean90',
			'sergey',
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
			'boren',
		),
		'core-http' => array(
			'eric',
			'tollmanz',
		),
		'core-i18n' => array(
			'ocean90',
			'sergey',
		),
		'core-images' => array(
			'joemcgill',
			'mike',
		),
		'core-multisite' => array(
			'jeremyfelt',
		),
		'core-passwords' => array(
			'georgestephanis',
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
			'hugobaeta',
			'melchoyce',
		),
		'docs' => array(
			'drew',
			'hlashbrooke',
			'lizkaraffa',
		),
		'feature-oembed' => array(
			'swissspidy',
		),
		'feature-shinyupdates' => array(
			'obenland',
			'adamsilverstein',
			'michaelarestad',
		),
		'forums' => array(
			'clorith',
			'ipstenu',
			'jan_dembowski',
			'macmanx',
		),
		'glotpress' => array(
			'ocean90',
			'gregross',
		),
		'marketing' => array(
			'sararosso',
		),
		'meta' => array(
			'obenland',
		),
		'meta-i18n' => array(
			'ocean90',
		),
		'polyglots' => array(
			'deconf',
			'ocean90',
			'petya',
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
			'chanthaboune',
		),
		'wcus-contributor-day' => array(
			'drew',
		),
		'wcus-speakers' => array(
			'alx',
		),
		'wcus-summit' => array(
			'liljimmi',
		),
		'wcus-volunteers' => array(
			'ingridmiller',
			'liamdempsey',
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
		'westonruter', 'afercia', 'karmatosed', 'rmccue', 'michaelarestad',
		'swissspidy', 'rachelbaker', 'joehoyle', 'melchoyce', 'eric', 'mike',
	);
}

