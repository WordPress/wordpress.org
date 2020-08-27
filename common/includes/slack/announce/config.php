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
			'arush',
			'audrasjb',
			'joedolson',
			'nrqsnchz',
			'rianrietveld',
			'ryokuhi',
		),
		'accessibility-events' => array(
			'audrasjb',
			'joedolson',
			'nrqsnchz',
			'ryokuhi',
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
			'myselfkhayer',
		) ),
		'contributor-day' => array(
			'camikaos',
			'cronkled', // @alx on Slack
			'DrewAPicture', // @drew on Slack
			'Kau-Boy',
			'sippis',
		),
		'core' => array_merge( get_committers(), array(
			'amykamala', // @amy kamala on Slack
			'angelasjin',
			'antpb',
			'audrasjb',
			'cbringmann', // @Chloé Bringmann on Slack
			'chanthaboune',
			'danieltj',
			'davidbaumwald', // @davidb on Slack
			'desrosj',
			'francina',
			'hellofromtonya',
			'JeffPaul',
			'JoshuaWold',
			'justinahinon', // @justin on Slack
			'karmatosed',
			'laurora', // @laura on Slack
			'lukecarbis',
			'mapk',
			'marybaum',
			'meaganhanes',
			'metalandcoffee',
			'pbiron',
			'psykro', // @jon_bossenger on Slack
			'thelmachido', // @thelmachido-zw on Slack
			'thewebprincess',
			'welcher',
			'whitneyyadrich', // @Whitney on Slack
		) ),
		'core-auto-updates' => array_merge( get_committers(), array(
			'audrasjb',
			'pbiron',
		) ),
		'core-bootstrap' => array_merge( get_committers(), array(
			'schlessera',
		) ),
		'core-comments' => array_merge( get_committers(), array(
			'rachelbaker',
		) ),
		'core-committers' => get_committers(),
		'core-css' => array_merge( get_committers(), array(
			'isabel_brison', // @tellthemachines on Slack
			'notlaura', // @laras126 on Slack
		) ),
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
			'ellatrix', // @ella on Slack
			'JeffPaul',
			'karmatosed',
			'mapk',
			'matveb', // @matias on Slack
			'mcsf',
			'paaljoachim',
			'talldanwp', // @danr on Slack
			'youknowriad',
		) ),
		'core-fields' => array_merge( get_committers(), array(
			'sc0ttkclark',
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
			'nerrad',
			'psykro', // @jon_bossenger on Slack
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
		'core-site-health' => array_merge( get_committers(), array(
			'afragen',
			'Clorith',
			'miss_jwo',
			'spacedmonkey', // @Jonny Harris on Slack
		) ),
		'core-sitemaps' => array_merge( get_committers(), array(
			'tweetythierry', // @Thierry Muller on Slack
		) ),
		'core-php' => array_merge( get_committers(), array(
			'afragen',
			'flixos90',
			'schlessera',
			'spacedmonkey',
		) ),
		'core-privacy' => array_merge( get_committers(), array(
			'allendav',
			'azaozz',
			'carike',
			'casiepa', // @Pascal on Slack
			'dejliglama',
			'desrosj',
			'garrett-eclipse',
			'idea15', // @Heather Burns on Slack
			'lakenh',
			'xkon',
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
			'TimothyBlynJacobs', // @timothybjacobs on Slack
		) ),
		'core-test' => array_merge( get_committers(), array(
			'ryan', // @boren on Slack
		) ),
		'core-themes' => array_merge( get_committers(), array(
			'anlino', // @andersnoren on Slack
			'davidakennedy',
			'iamtakashi',
			'ianbelanger',
			'karmatosed',
			'laurelfulford',
			'melchoyce',
			'poena',
		) ),
		'design' => array(
			'boemedia',
			'estelaris',
			'hugobaeta',
			'JoshuaWold',
			'karmatosed',
			'mapk',
			'melchoyce',
			'mizejewski',
			'paaljoachim',
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
			'hrmervin',
			'johnbillion',
			'justinahinon', // @justin on Slack
			'psykro', // @jon_bossenger on Slack
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
			'Crixu',
			'mikeschroder', // @mike on Slack
			'jadonn',
			'amykamala', // @kamala on Slack
			'brechtryckaert',
			'brettface',
		),
		'marketing' => array(
			'bridgetwillard', // @gidgey on Slack
			'harryjackson1221',
			'joostdevalk',
			'mikerbg', // @miker on Slack
			'rosso99', // @sararosso on Slack
			'siobhanseija',
			'webcommsat', // @abhanonstopnewsuk on Slack
			'yvettesonneveld',
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
		'meta-helphub' => array(
			'atachibana',
			'Kenshino',
			'milana_cap', // @zzap on Slack
		),
		'meta-i18n' => array(
			'ocean90',
		),
		'meta-wordcamp' => $wordcamp_central,
		'polyglots' => array(
			'casiepa', // @Pascal on Slack
			'CoachBirgit',
			'deconf',
			'evarlese', 
			'felipeelia',
			'Nao',
			'ocean90',
			'petya',
			'SergeyBiryukov', // @sergey on Slack
			'tobifjellner',
			'tokyobiyori',
		),
		'polyglots-events' => array(
			'casiepa', // @Pascal on Slack
			'CoachBirgit',
			'deconf',
			'evarlese',
			'felipeelia',
			'Nao',
			'ocean90',
			'petya',
			'SergeyBiryukov', // @sergey on Slack
			'tobifjellner',
			'tokyobiyori',
		),
		'themereview' => array(
			'acalfieri',
			'acosmin',
			'aristath',
			'cais',
			'chipbennett',
			'emiluzelac',
			'grapplerulrich',
			'greenshady',
			'jcastaneda',
			'kafleg',
			'karmatosed',
			'kjellr',
			'poena',
			'williampatton',
		),
		'tide' => array(
			'JeffPaul',
			'lukecarbis',
			'valendesigns',
		),
		'training' => array(
			'chetan200891',
			'courtneydawn',
			'donkiely',
			'jessecowens', // @Jesse Owens on Slack
			'liljimmi',
			'bethsoderberg',
			'courane01', // @courtneyengle on Slack
			'chanthaboune',
			'melindahelt',
			'juliekuehl',
		),
		// NOTE: Private Groups need not be listed here. All members of Private Groups have access to /announce & /here
		'wcasia' => array(
			'Nao',
			'SamSuresh',
		),
		'wceu' => array(
			'casiepa', // @Pascal on Slack
		),
		'wceu-volunteers' => array(
			'Kau-Boy',
			'sippis',
		),
		'wcus' => array(
			'cronkled', // @alx on Slack
			'camikaos',
			'vc27', // @randy_hicks on Slack
			'andrealeebishop', // @bishop on Slack
			'DustinMeza',
		),
		'wptv' => array(
			'casiepa', // @Pascal on Slack
			'JerrySarcastic',
			'mgelves', // @maugelves on Slack
			'nishasingh',
			'rahuldsarker',
			'RoseAppleMedia',
			'sbddesign',
		),
	);
}

function get_committers() {
	return array(
		'aaroncampbell', 'adamsilverstein', 'aduth', 'afercia', 'allancole', 'allendav',
		'antpb', 'atimmer', 'azaozz', 'bpayton', 'davidakennedy', 'dd32', 'desrosj',
		'flixos90', 'gziolo', 'helen', 'herregroen', 'ianbelanger', 'iandunn', 'iandstewart',
		'jeremyfelt', 'joedolson', 'joehoyle', 'joemcgill', 'joen', 'johnbillion', 'jorbin',
		'jorgefilipecosta', 'josephscott', 'jrf', 'kadamwhite', 'karmatosed', 'kovshenin',
		'lancewillett', 'laurelfulford', 'matt', 'mattmiklic', 'mcsf', 'mdawaffe',
		'melchoyce', 'michaelarestad', 'nacin', 'noisysocks', 'obenland', 'ocean90',
		'omarreiss', 'pento', 'peterwilsoncc', 'rachelbaker', 'rmccue', 'swissspidy',
		'tellyworth', 'westi', 'westonruter', 'whyisjake', 'wonderboymusic', 'xknown',
		'youknowriad',

		'boonebgorges',      // @boone on Slack
		'DrewAPicture',      // @drew on Slack
		'ellatrix',          // @ella on Slack
		'ericlewis',         // @eric on Slack
		'johnjamesjacoby',   // @JJJ on Slack
		'jnylen0',           // @jnylen on Slack
		'lonelyvegan',       // @tofumatt on Slack
		'markjaquith',       // @mark on Slack
		'matveb',            // @matias on Slack
		'mikeschroder',      // @mike on Slack
		'nbachiyski',        // @nb on Slack
		'SergeyBiryukov',    // @sergey on Slack
		'talldanwp',         // @danr on Slack
		'TimothyBlynJacobs', // @timothybjacobs on Slack
	);
}
