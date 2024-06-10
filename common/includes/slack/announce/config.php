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
		'Nao',
		'ryelle',
	);

	return array(
		'6-4-release-leads' => array(
			'akshayar', // @akshaya on Slack
			'cbringmann', // @Chloé Bringmann on Slack
			'chanthaboune',
			'francina',
			'metalandcoffee',
		),
		'accessibility' => array(
			// #core (inc committers) already included via get_parent_channel().
			'afercia',
			'alexstine',
			'arush',
			'audrasjb',
			'azhiyadev', // @Hauwa Abashiya on Slack
			'elblakeo31', // @Blake (Equalify)
			'joedolson',
			'joesimpsonjr',
			'nrqsnchz',
			'rianrietveld',
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
		'contributor-mentorship' => $wordcamp_central,
		'core' => array_merge( get_committers(), array(
			'akshayar', // @akshaya on Slack
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
			'colorful-tones',
			'danieltj',
			'desrosj',
			'fabiankaegy',
			'francina',
			'hellofromTonya', // @hellofromtonya on Slack
			'ironprogrammer',
			'James Roberts', // @jamesroberts on Slack
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
			'meher',
			'metalandcoffee',
			'mikachan',
			'monikarao',
			'mukesh27',
			'nicolefurlan',
			'nhrrob',
			'oglekler',
			'pbiron',
			'priethor',
			'psykro', // @Jonathan on Slack
			'rajinsharwar', // @Rajin Sharwar on Slack
			'sabernhardt',
			'sncoker', // @shawntellecoker on Slack
			'thelmachido', // @thelmachido-zw on Slack
			'thewebprincess',
			'webcommsat', // @abhanonstopnewsuk on Slack
			'welcher',
			'whitneyyadrich', // @Whitney on Slack
		) ),
		'core-upgrade-install' => array_merge( get_committers(), array(
			'afragen',
			'audrasjb',
			'costdev',
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
		'core-dev-blog' => array(
			'bph',
			'greenshady',
		),
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
			'psykro', // @Jonathan on Slack
			'rmccue',
		) ),
		'core-media' => array_merge( get_committers(), array(
			'antpb',
			'desrosj',
			'joemcgill',
			'karmatosed',
			'kirasong',
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
			'paaljoachim',
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
			'Ankit K Gupta', // @Ankit K Gupta on Slack
			'Boniu91', // @Piotrek Boniu on Slack
			'francina',
			'hellofromTonya', // @hellofromtonya on Slack
			'ironprogrammer',
			'justinahinon',
			'monikarao',
			'ryan', // @boren on Slack
			'webtechpooja', // @Pooja Derashri on Slack
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
			'onemaggie',
			'poena',
		) ),
		'deib-working-group' => array(
			'CoachBirgit',
		),
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
			'psykro', // @Jonathan on Slack
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
		'glotpress' => array(
			'Amieiro',
			'ocean90',
		),
		'hosting' => array(
			'andrew.taylor', // @ataylorme on Slack
			'Crixu',
			'kirasong',
			'jadonn',
			'JavierCasares',
			'jessibelle',
			'amykamala', // @amy kamala on Slack
			'brechtryckaert',
			'brettface',
		),
		'marketing' => array(
			'bernard0omnisend', // Bernard Meyer on Slack
			'eidolonnight',
			'lmurillom',
			'nalininonstopnewsuk', // @Nalini on Slack
			'ngreennc', // @Nyasha G on Slack
			'ninianepress', // @jenni on Slack
			'robinwpdeveloper',
			'santanainniss',
			'SeReedMedia',
		),
		'meta' => array(
			'coreymckrill',
			'courane01', // @Courtney on Slack
			'dd32',
			'obenland',
			'SeReedMedia',
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
			'courane01', // @Courtney on Slack
			'devmuhib', // @Muhibul Haque on Slack
			'digitalchild', // @Jamie Madden on Slack
			'psykro', // @Jonathan on Slack
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
		'outreach' => array(
			'annezazu',
			'bph',
			'colorful tones', // @colorful-tones on Slack
			'greenshady',
			'ndiego', // @Nick Diego on Slack
		),
		'core-performance' => array(
			// #core (inc committers) already included via get_parent_channel().
			'clarkeemily', // @Emily Clarke on Slack
			'joegrainger', // @Joe Grainger on Slack
			'mukesh27',
			'mxbclang', // @Bethany Chobanian Lang (they/them) on Slack
		),
		'photos' => array(
			'topher1kenobe',
			'katiejrichards',
			'marcusskyverge',
		),
		'polyglots' => array(
			'Amieiro',
			'casiepa', // @Pascal on Slack
			'chaion07',
			'CoachBirgit',
			'deconf',
			'evarlese',
			'felipeelia',
			'kharisblank', // @kharisulistiyo on Slack
			'Nao',
			'ocean90',
			'petya',
			'SergeyBiryukov', // @sergey on Slack
			'spiraltee', // @Tosin on Slack
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
		'polyglots-multilingual-community' => array(
			'courane01', // @Courtney on Slack
			'estelaris',
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
			'afshanadiya',
			'aion11',
			'amitpatelmd',
			'annezazu',
			'arasae', // @Sarah (She/Her) on Slack
			'azhiyadev', // @Hauwa Abashiya on Slack
			'bsanevans',
			'chanthaboune',
			'chetan200891',
			'chrisbadgett',
			'ChrisMKindred',
			'colorful tones', // @colorful-tones on Slack
			'courane01', // @Courtney on Slack
			'courtneypk',
			'digitalchild',
			'eboxnet',
			'fikekomala',
			'hardeepasrani',
			'jessecowens', // @Jesse Owens on Slack
			'juliekuehl',
			'lada7042',
			'meaganhanes',
			'mrfoxtalbot',
			'ndiego',
			'onealtr', // @oneal on Slack
			'pbrocks',
			'piyopiyofox',
			'psykro', // @Jonathan on Slack
			'richtabor',
			'trynet',
			'webtechpooja', // @Pooja Derashri on Slack
			'west7',
			'wpscholar',
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
		'website-redesign' => array(
			'ndiego', // @Nick Diego on Slack
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
