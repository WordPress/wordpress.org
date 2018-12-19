<?php

namespace Dotorg\Slack\Announce;

// Required function: get_whitelist()
// Optional function: get_avatar()

/**
 * Returns a whitelist of users by channel.
 *
 * As Slack has deprecated the usage of user_names, please use WordPress.org user_logins here. Case Sensitive.
 * See https://api.slack.com/changelog/2017-09-the-one-about-usernames
 *
 * The array keys are the channel name (omit #) and the
 * values are an array of users.
 */
function get_whitelist() {
	$wordcamp_central = array(
		'adityakane',
		'andreamiddleton',
		'angelasjin',
		'camikaos',
		'chanthaboune',
		'coreymckrill',
		'courtneypk',
		'hlashbrooke',
		'iandunn',
		'_DorsVenabili', // @rocio on Slack
		'vedjain', // @vendanshu on Slack
	);

	return array(
		'accessibility' => array(
			'afercia',
			'joedolson',
			'rianrietveld',
			'audrasjb',
			'arush',
		),
		'bbpress' => array(
			'johnjamesjacoby', // @jjj on Slack
			'netweb',
		),
		'buddypress' => array(
			'boonebgorges', // @boone on Slack
			'DJPaul',
			'johnjamesjacoby', // @jjj on Slack
		),
		'cli' => array(
			'danielbachhuber',
			'schlessera',
		),
		'community-events' => array_merge( $wordcamp_central, array(
			'francina',
		) ),
		'community-team' => array_merge( $wordcamp_central, array(
			'francina',
		) ),
		'core' => array_merge( get_committers(), array(
			'antpb',
			'audrasjb',
			'chanthaboune',
			'danieltj',
			'desrosj',
			'JeffPaul',
			'karmatosed',
			'psykro', // @jon_bossenger on Slack
			'JoshuaWold',
			'pbiron',
			'welcher',
			'whitneyyadrich', // @Whitney on Slack
		) ),
		'core-bootstrap' => array_merge( get_committers(), array(
			'schlessera',
		) ),
		'core-comments' => array_merge( get_committers(), array(
			'rachelbaker',
		) ),
		'core-committers' => get_committers(),
		'core-customize' => array_merge( get_committers(), array(
			'celloexpressions',
			'JeffPaul',
			'ocean90',
			'valendesigns',
			'voldemortensen',
			'westonruter',
		) ),
		'core-docs' => array_merge( get_committers(), array(
			'DrewAPicture', // @drew on Slack
			'Kenshino',
			'omarreiss',
			'johnbillion',
			'atimmer',
			'chrisvanpatten',
		) ),
		'core-editor' => array_merge( get_committers(), array(
			'azaozz',
			'gziolo',
			'iseulde',
			'JeffPaul',
			'karmatosed',
			'matveb', // @matias on Slack
			'mcsf',
			'youknowriad',
		) ),
		'core-fields' => array_merge( get_committers(), array(
			'sc0ttkclark',
		) ),
		'core-test' => array_merge( get_committers(), array(
			'ryan', // @boren on Slack
		) ),
		'core-https' => array_merge( get_committers(), array(
			'ericlewis', // @eric on Slack
			'tollmanz',
		) ),
		'core-i18n' => array_merge( get_committers(), array(
			'ocean90',
			'SergeyBiryukov', // @sergey on Slack
			'swissspidy',
		) ),
		'core-js' => array_merge( get_committers(), array(
			'adamsilverstein',
			'aduth',
			'rmccue',
		) ),
		'core-media' => array_merge( get_committers(), array(
			'antpb',
			'desrosj',
			'joemcgill',
			'karmatosed',
			'mikeschroder', // @mike on Slack
			'ocean90',
		) ),
		'core-multisite' => array_merge( get_committers(), array(
			'flixos90',
			'jeremyfelt',
			'spacedmonkey',
		) ),
		'core-passwords' => array_merge( get_committers(), array(
			'georgestephanis',
			'valendesigns',
		) ),
		'core-php' => array_merge( get_committers(), array(
			'flixos90',
			'schlessera',
		) ),
		'core-privacy' => array_merge( get_committers(), array(
			'allendav',
			'azaozz',
			'casiepa',
			'desrosj',
			'idea15', // @Heather Burns on Slack
		) ),
		'core-restapi' => array_merge( get_committers(), array(
			'danielbachhuber',
			'desrosj',
			'flixos90',
			'joehoyle',
			'kadamwhite',
			'krogsgard',
			'rachelbaker',
			'rmccue',
		) ),
		'core-themes' => array_merge( get_committers(), array(
			'davidakennedy',
			'iamtakashi',
			'karmatosed',
			'laurelfulford',
			'melchoyce',
		) ),
		'design' => array(
			'boemedia',
			'hugobaeta',
			'JoshuaWold',
			'karmatosed',
			'melchoyce',
			'mizejewski',
		),
		'design-dashicons' => array(
			'EmpireOfLight',
		),
		'docs' => array(
			'atachibana',
			'chrisvanpatten',
			'Clorith',
			'DrewAPicture', // @drew on Slack
			'hlashbrooke',
			'Kenshino',
			'lizkaraffa',
			'milana_cap', // @zzap on Slack
		),
		'feature-notifications' => array(
			'johnbillion',
			'schlessera',
		),
		'feature-shinyupdates' => array(
			'obenland',
			'adamsilverstein',
			'michaelarestad',
			'swissspidy',
		),
		'forums' => array(
			'Clorith',
			'Ipstenu',
			'jdembowski', // @jan_dembowski on Slack
			'macmanx',
		),
		'glotpress' => array(
			'ocean90',
			'GregRoss',
		),
		'hosting-community' => array(
			'andrew.taylor', // @ataylorme on Slack
			'mikeschroder', // @mike on Slack
			'jadonn',
		),
		'marketing' => array(
			'rosso99', // @sararosso on Slack
			'bridgetwillard', // @gidgey on Slack
		),
		'meta' => array(
			'obenland',
			'SergeyBiryukov', // @sergey on Slack
			'tellyworth',
		),
		'meta-devhub' => array(
			'coffee2code',
			'DrewAPicture', // @drew on Slack
		),
		'meta-i18n' => array(
			'ocean90',
		),
		'meta-wordcamp' => $wordcamp_central,
		'polyglots' => array(
			'casiepa',
			'CoachBirgit',
			'deconf',
			'Nao',
			'ocean90',
			'petya',
			'SergeyBiryukov', // @sergey on Slack
			'tobifjellner',
		),
		'polyglots-events' => array(
			'casiepa',
			'CoachBirgit',
			'deconf',
			'Nao',
			'ocean90',
			'petya',
			'SergeyBiryukov', // @sergey on Slack
			'tobifjellner',
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
		'tide' => array(
			'JeffPaul',
			'lukecarbis',
			'valendesigns',
		),
		'training' => array(
			'courtneydawn',
			'donkiely',
			'liljimmi',
			'bethsoderberg',
			'courane01', // @courtneyengle on Slack
			'chanthaboune',
			'melindahelt',
			'juliekuehl',
		),
		// NOTE: Private Groups need not be listed here. All members of Private Groups have access to /announce & /here
		'wcus' => array(
			'cronkled', // @alx on Slack
			'camikaos',
			'vc27', // @randy_hicks on Slack
			'andrealeebishop', // @bishop on Slack
			'DustinMeza',
		),
		'wcus-contributor-day' => array(
			'cronkled', // @alx on Slack
			'camikaos',
			'DrewAPicture', // @drew on Slack
		),
		'wptv' => array(
			'JerrySarcastic',
			'RoseAppleMedia',
		),
	);
}

function get_committers() {
	return array(
		'dd32', 'nacin', 'helen', 'azaozz', 'westi',
		'ocean90', 'wonderboymusic', 'johnbillion',
		'jorbin', 'jeremyfelt', 'pento', 'obenland', 'iseulde',
		'westonruter', 'afercia', 'karmatosed', 'rmccue', 'michaelarestad',
		'swissspidy', 'rachelbaker', 'joehoyle', 'melchoyce',
		'peterwilsoncc', 'joemcgill', 'davidakennedy', 'adamsilverstein',
		'flixos90', 'iandunn', 'kadamwhite',

		'markjaquith',    // @mark on Slack
		'nbachiyski',     // @nb on Slack
		'SergeyBiryukov', // @sergey on Slack
		'DrewAPicture',   // @drew on Slack
		'boonebgorges',   // @boone on Slack
		'ericlewis',      // @eric on Slack
		'mikeschroder',   // @mike on Slack
		'jnylen0',        // @jnylen on Slack
		'matveb',         // @matias on Slack
	);
}

// This is not all deputies; it's only the ones who want to receive `/deputies` pings
function get_pingable_wordcamp_deputies() {
	return array(
		'00Sleepy', '_DorsVenabili', 'adityakane', 'andreamiddleton', 'bph', 'brandondove', 'camikaos',
		'chanthaboune', 'courtneypk', 'drebbits', 'francina', 'gounder', 'heysherie', 'hlashbrooke',
		'karenalma', 'kcristiano', 'kdrewien', 'Kenshino', 'mayukojpn', 'mikelking', 'miss_jwo',
		'remediosgraphic', 'Savione', 'vc27', 'yaycheryl',

		'coreymckrill', 'iandunn', // todo remove after testing
	);
}
