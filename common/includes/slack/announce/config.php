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
 * The array keys are the channel name (omit #) and the values are an array of users.
 *
 * NOTES:
 *  - Use the linked WordPress.org username, which is case-sensitive. Ask the user to type in `/here` in slack to find out the correct account.
 *  - Private groups do NOT need to be listed here. All members of a private group can use announcements.
 *  - Sub-channels inherit access, if a user is granted announce in #foo, they can also announce in #foo-bar.
 *  - Committers have access to `#core` and all sub-channels. The list comes from the global `$committers` configuration.
 */
function get_whitelist() {
	$wordcamp_central = array(
		'_DorsVenabili', // @rocio on Slack
		'andreamiddleton',
		'angelasjin',
		'camikaos',
		'chanthaboune',
		'coreymckrill',
		'courtneypk',
		'evarlese',
		'harishanker',
		'harmonyromo', // @Harmony Romo on Slack
		'hlashbrooke',
		'iandunn',
		'ryelle',
	);

	return array(
		'accessibility' => array(
			// #core (inc committers) already included via get_parent_channel().
			'afercia',
			'alexstine',
			'arush',
			'audrasjb',
			'azhiyadev', // @Hauwa Abashiya on Slack
			'joedolson',
			'joesimpsonjr',
			'nrqsnchz',
			'rianrietveld',
			'ryokuhi',
			'sarahricker',
		),
		'accessibility-docs' => array(
			'sarahricker',
		),
		'accessibility-events' => array(
			'audrasjb',
			'joedolson',
			'nrqsnchz',
			'ryokuhi',
			'sarahricker',
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
			'annezazu',
			'antpb',
			'audrasjb',
			'Boniu91', // @Piotrek Boniu on Slack
			'cbringmann', // @Chloé Bringmann on Slack
			'chaion07',
			'chanthaboune',
			'costdev',
			'danieltj',
			'desrosj',
			'francina',
			'hellofromTonya', // @hellofromtonya on Slack
			'JeffPaul',
			'JoshuaWold',
			'justinahinon',
			'karmatosed',
			'laurora', // @laura on Slack
			'lukecarbis',
			'mapk',
			'markparnell',
			'marybaum',
			'meaganhanes',
			'metalandcoffee',
			'monikarao',
			'pbiron',
			'priethor',
			'psykro', // @jon_bossenger on Slack
			'sabernhardt',
			'sncoker', // @shawntellecoker on Slack
			'thelmachido', // @thelmachido-zw on Slack
			'thewebprincess',
			'webcommsat', // @abhanonstopnewsuk on Slack
			'welcher',
			'whitneyyadrich', // @Whitney on Slack
		) ),
		'core-auto-updates' => array_merge( get_committers(), array(
			'afragen',
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
			'danfarrow',
			'dryanpress', // @Dave Ryan on Slack
			'isabel_brison', // @tellthemachines on Slack
			'kburgoine',
			'notlaura', // @laras126 on Slack
			'ryelle',
			'wazeter',
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
			'annezazu',
			'azaozz',
			'fabiankaegy',
			'get_dave', // @getdave on Slack
			'gziolo',
			'ellatrix', // @ella on Slack
			'JeffPaul',
			'karmatosed',
			'Mamaduka',
			'mapk',
			'matveb', // @matias on Slack
			'mcsf',
			'ndiego', //@Nick Diego on Slack
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
		'core-php' => array_merge( get_committers(), array(
			'afragen',
			'flixos90',
			'hellofromTonya', // @hellofromtonya on Slack
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
			'spacedmonkey', // @Jonny Harris on Slack
			'TimothyBlynJacobs', // @timothybjacobs on Slack
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
		'core-test' => array_merge( get_committers(), array(
			'Boniu91', // @Piotrek Boniu on Slack
			'francina',
			'hellofromTonya', // @hellofromtonya on Slack
			'ironprogrammer',
			'justinahinon',
			'monikarao',
			'ryan', // @boren on Slack
		) ),
		'core-themes' => array_merge( get_committers(), array(
			'anlino', // @andersnoren on Slack
			'davidakennedy',
			'iamtakashi',
			'ianbelanger',
			'jffng',
			'karmatosed',
			'kjellr',
			'laurelfulford',
			'luminuu', // @jessica on Slack
			'melchoyce',
			'poena',
		) ),
		'design' => array(
			// #core (inc committers) already included via get_parent_channel().
			'chaion07',
			'critterverse',
			'estelaris',
			'hedgefield',
			'Joen',
			'karmatosed',
			'melchoyce',
			'paaljoachim',
			'sarahricker',
			'shaunandrews',
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
		// For all #feature-* channels: #core (inc committers) already included via get_parent_channel().
		'feature-notifications' => array(
			'hrmervin',
			'johnbillion',
			'justinahinon',
			'loganfive', // @Garrett Hunter on Slack
			'psykro', // @jon_bossenger on Slack
			'raaaahman', // @Sylvain Schellenberger on slack
			'schlessera',
			'sephsekla', // @Joe Bailey-Roberts on Slack
			'bacoords',
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
			'mariaojob', // @Mary Job on Slack
			'sterndata',
		),
		'fse-outreach-experiment' => array(
			'annezazu',
		),
		'glotpress' => array(
			'Amieiro',
			'ocean90',
		),
		'hosting-community' => array(
			'andrew.taylor', // @ataylorme on Slack
			'Crixu',
			'mikeschroder', // @mike on Slack
			'jadonn',
			'JavierCasares',
			'amykamala', // @amy kamala on Slack
			'brechtryckaert',
			'brettface',
		),
		'marketing' => array(
			'eidolonnight',
			'lmurillom',
			'nalininonstopnewsuk', // @Nalini on Slack
		),
		'meta' => array(
			'coreymckrill',
			'dd32',
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
		'meta-learn' => array(
			'coreymckrill',
			'tellyworth',
		),
		'meta-wordcamp' => $wordcamp_central,
		'openverse' => array(
			'aetherunbound', // @Madison Swain-Bowden on Slack
			'dhruvkb',
			'fcoveram', // @Francisco on Slack
			'krysal',
			'olgabulat', // @Olga Bulat on Slack
			'ronnybadilla', // @ronny on Slack
			'sarayourfriend',
			'stacimc',
			'zackkrida',
		),
		'core-performance' => array(
			// #core (inc committers) already included via get_parent_channel().
			'clarkeemily', // @Emily Clarke on Slack
			'shetheliving', // @Bethany Chobanian Lang (they/them) on Slack
		),
		'photos' => array(
			'topher1kenobe',
			'katiejrichards',
			'marcusskyverge',
		),
		'polyglots' => array(
			'Amieiro',
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
			'vladytimy', // @vladt on slack
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
			// #core (inc committers) already included via get_parent_channel().
			'JeffPaul',
			'lukecarbis',
			'valendesigns',
		),
		'training' => array_merge( $wordcamp_central, array(
			'arasae', // @Sarah (She/Her) on Slack
			'azhiyadev', // @Hauwa Abashiya on Slack
			'chanthaboune',
			'chetan200891',
			'courane01', // @Courtney on Slack
			'jessecowens', // @Jesse Owens on Slack
			'juliekuehl',
			'onealtr', // @oneal on Slack
			'webtechpooja', // @Pooja Derashri on Slack
		) ),
		// NOTE: Private Groups need not be listed here. All members of Private Groups have access to /announce & /here
		'wcasia' => array(
			'Nao',
			'SamSuresh',
		),
		'wceu' => array(
			'vertizio', // @Pascal on Slack
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

/**
 * Fetch a list of the WordPress core committers.
 *
 * This is defined on WordPress.org in a global variable called `$committers`.
 * It's defined as part of the configuration bootstrap.
 */
function get_committers() {
	global $committers;

	if ( empty( $committers ) ) {
		return array();
	}

	return array_keys( $committers );
}
