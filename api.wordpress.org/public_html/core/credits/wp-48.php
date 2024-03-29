<?php

class WP_48_Credits extends WP_Credits {

	function groups() {
		$groups = array(
			'project-leaders' => array(
				'name'    => 'Project Leaders',
				'type'    => 'titles',
				'shuffle' => true,
				'data'    => array(
					'matt'        => array( 'Matt Mullenweg',  'Release Lead' ),
					'nacin'       => array( 'Andrew Nacin',    'Lead Developer' ),
					'markjaquith' => array( 'Mark Jaquith',    'Lead Developer', '097a87a525e317519b5ee124820012fb' ),
					'azaozz'      => array( 'Andrew Ozz',      'Lead Developer' ),
					'helen'       => array( 'Helen Hou-Sandí', 'Lead Developer' ),
					'dd32'        => array( 'Dion Hulse',      'Lead Developer' ),
				),
			),
			'core-developers' => array(
				'name'    => 'Contributing Developers',
				'type'    => 'titles',
				'shuffle' => false,
				'data'    => array(
					'jbpaul17'       => array( 'Jeff Paul',         'Release Deputy' ),
					'aaroncampbell'  => array( 'Aaron D. Campbell', 'Core Developer' ),
					'jorbin'         => array( 'Aaron Jorbin',      'Core Developer' ),
					'afercia'        => array( 'Andrea Fercia',     'Core Developer' ),
					'boonebgorges'   => array( 'Boone B. Gorges',   'Core Developer' ),
					'ocean90'        => array( 'Dominik Schilling', 'Core Developer' ),
					'DrewAPicture'   => array( 'Drew Jaynes',       'Core Developer' ),
					'iseulde'        => array( 'Ella Iseulde Van Dorpe', 'Core Developer' ),
					'pento'          => array( 'Gary Pendergast',   'Core Developer' ),
					'jeremyfelt'     => array( 'Jeremy Felt',       'Core Developer' ),
					'joemcgill'      => array( 'Joe McGill',        'Core Developer' ),
					'johnbillion'    => array( 'John Blackbourn',   'Core Developer' ),
					'kirasong'       => array( 'Kira Song',         'Core Developer' ),
					'swissspidy'     => array( 'Pascal Birchler',   'Core Developer' ),
					'rachelbaker'    => array( 'Rachel Baker' ,     'Core Developer' ),
					'wonderboymusic' => array( 'Scott Taylor',      'Core Developer' ),
					'SergeyBiryukov' => array( 'Sergey Biryukov',   'Core Developer' ),
					'westonruter'    => array( 'Weston Ruter',      'Core Developer', '22ed378fbf1d918ef43a45b2a1f34634' ),
					'davidakennedy'  => 'David A. Kennedy',
					'flixos90'       => 'Felix Arntz',
					'melchoyce'      => 'Mel Choyce',
				),
			),
			'contributing-developers' => array(
				'name'    => false,
				'type'    => 'titles',
				'shuffle' => true,
				'data'    => array(
					'obenland'        => 'Konstantin Obenland',
					'rmccue'          => array( 'Ryan McCue', '08818120f223035a0857c2a0ec417f93' ),
					'karmatosed'      => 'Tammie Lister',
					'joehoyle'        => 'Joe Hoyle',
					'ericlewis'       => 'Eric Andrew Lewis',
					'peterwilsoncc'   => 'Peter Wilson',
					'kovshenin'       => 'Konstantin Kovshenin',
					'michaelarestad'  => 'Michael Arestad',
					'adamsilverstein' => 'Adam Silverstein',
					'jnylen0'         => 'James Nylen',
					'kadamwhite'      => 'K.Adam White',
					'Joen'            => 'Joen Asmussen',
					'matveb'          => 'Matias Ventura',
				),
			),
			'recent-rockstars' => array(
				'name'    => false,
				'type'    => 'titles',
				'shuffle' => true,
				'data'    => array(
				),
			),
		);

		return $groups;
	}

	function props() {
		return array(
			'1naveengiri',
			'4nickpick',
			'aaroncampbell',
			'abhishek',
			'abhishekfdd',
			'abrain',
			'adamsilverstein',
			'adamsoucie',
			'afercia',
			'afzalmultani',
			'ajoah',
			'alexkingorg',
			'andreamiddleton',
			'ankit-k-gupta',
			'apmarshall',
			'arena',
			'arena94',
			'arshidkv12',
			'aryamaaru',
			'asalce',
			'ashokkumar24',
			'atanasangelovdev',
			'aussieguy123',
			'azaozz',
			'barryceelen',
			'batmoo',
			'bcworkz',
			'bharatkambariya',
			'bhargavbhandari90',
			'blobfolio',
			'boonebgorges',
			'bor0',
			'bradt',
			'bradyvercher',
			'bridgetwillard',
			'camikaos',
			'carl-alberto',
			'caseypatrickdriscoll',
			'cazm',
			'ccprog',
			'celloexpressions',
			'certainstrings',
			'chandrapatel',
			'chanthaboune',
			'cheffheid',
			'chesio',
			'chiragpatel',
			'chopinbach',
			'chouby',
			'chris_dev',
			'chriseverson',
			'christian1012',
			'cklosows',
			'clarinetlord',
			'codegeass',
			'coffee2code',
			'coreymckrill',
			'courtneypk',
			'cristianozanca',
			'csloisel',
			'curdin',
			'cybr',
			'danielbachhuber',
			'darshan02',
			'darthaud',
			'davidakennedy',
			'davidanderson',
			'davidbenton',
			'davidbinda',
			'dd32',
			'delawski',
			'designsimply',
			'desrosj',
			'dhanendran',
			'dharm1025',
			'dhaval-parekh',
			'diddledan',
			'dimadin',
			'dingo_d',
			'dlh',
			'dllh',
			'dotancohen',
			'dots',
			'doublehhh',
			'dreamon11',
			'drewapicture',
			'drivingralle',
			'drrobotnik',
			'dshanske',
			'dspilka',
			'ejner69',
			'emirpprime',
			'endif-media',
			'ethitter',
			'f-j-kaiser',
			'fab1en',
			'fibonaccina',
			'figureone',
			'flixos90',
			'francina',
			'fstaude',
			'garyc40',
			'georgestephanis',
			'ghosttoast',
			'gitlost',
			'gma992',
			'gonom9',
			'grapplerulrich',
			'greatislander',
			'greuben',
			'h3llas',
			'hedgefield',
			'helen',
			'helgatheviking',
			'hristo-sg',
			'iaaxpage',
			'iandunn',
			'ig_communitysites',
			'imath',
			'ipstenu',
			'ireneyoast',
			'iseulde',
			'iv3rson76',
			'ivantedja',
			'ixkaito',
			'jackreichert',
			'jaydeep-rami',
			'jazbek',
			'jblz',
			'jbpaul17',
			'jdgrimes',
			'jenblogs4u',
			'jeremyfelt',
			'jesseenterprises',
			'jfarthing84',
			'jigneshnakrani',
			'jipmoors',
			'jjcomack',
			'jmdodd',
			'jnylen0',
			'joedolson',
			'joehoyle',
			'joemcgill',
			'joen',
			'johnbillion',
			'johnjamesjacoby',
			'joostdevalk',
			'jorbin',
			'jpry',
			'juhise',
			'kadamwhite',
			'kafleg',
			'kailanitish90',
			'karinedo',
			'karmatosed',
			'kaushik',
			'kawauso',
			'keesiemeijer',
			'kelderic',
			'ketuchetan',
			'kirasong',
			'kjellr',
			'kkoppenhaver',
			'kopepasah',
			'kostasx',
			'kovshenin',
			'kraftbj',
			'kubik-rubik',
			'kuck1u',
			'lancewillett',
			'laurelfulford',
			'leemon',
			'leewillis77',
			'lewiscowles',
			'liammcarthur',
			'littlerchicken',
			'lucasstark',
			'lukasbesch',
			'lukecavanagh',
			'maedahbatool',
			'maguiar',
			'mantismamita',
			'mapk',
			'markoheijnen',
			'matheusfd',
			'matheusgimenez',
			'mathieuhays',
			'matias',
			'mattheu',
			'mattwiebe',
			'mattyrob',
			'matveb',
			'maximeculea',
			'mayukojpn',
			'mayurk',
			'mboynes',
			'melchoyce',
			'menakas',
			'michalzuber',
			'michelleweber',
			'mihai2u',
			'mikehansenme',
			'mikejolley',
			'mikelittle',
			'milindmore22',
			'mista-flo',
			'mitraval192',
			'miyauchi',
			'mmdeveloper',
			'mnelson4',
			'mohanjith',
			'monikarao',
			'morganestes',
			'mp518',
			'mrahmadawais',
			'mrgregwaugh',
			'mrwweb',
			'mschadegg',
			'mt8biz',
			'mte90',
			'nacin',
			'nao',
			'naomicbush',
			'natereist',
			'nerrad',
			'netweb',
			'nikschavan',
			'nitin-kevadiya',
			'nobremarcos',
			'nomnom99',
			'nosegraze',
			'nsundberg',
			'nullvariable',
			'obenland',
			'ocean90',
			'odysseygate',
			'pauldewouters',
			'pavelevap',
			'pbearne',
			'pbiron',
			'pdufour',
			'pento',
			'peterwilsoncc',
			'philipjohn',
			'piewp',
			'postpostmodern',
			'pranalipatel',
			'pratikshrestha',
			'presskopp',
			'printsachen1',
			'priyankabehera155',
			'prosti',
			'psoluch',
			'ptbello',
			'r-a-y',
			'rachelbaker',
			'rafaehlers',
			'raggedrobins',
			'raisonon',
			'ramiabraham',
			'ramiy',
			'ranh',
			'rclations',
			'redrambles',
			'reidbusi',
			'reldev',
			'rellect',
			'rensw90',
			'reportermike',
			'rianrietveld',
			'riddhiehta02',
			'rinkuyadav999',
			'rmccue',
			'rockwell15',
			'runciters',
			'ryan',
			'ryelle',
			'sa3idho',
			'sagarjadhav',
			'sagarkbhatt',
			'sagarprajapati',
			'salcode',
			'samantha-miller',
			'samikeijonen',
			'samuelsidler',
			'sanchothefat',
			'sanketparmar',
			'sathyapulse',
			'sboisvert',
			'seanchayes',
			'sebastianpisula',
			'sergeybiryukov',
			'sfpt',
			'sgolemon',
			'shadyvb',
			'shanee',
			'shashwatmittal',
			'shazahm1hotmailcom',
			'shelob9',
			'shulard',
			'sirbrillig',
			'slbmeh',
			'soean',
			'soulseekah',
			'spacedmonkey',
			'sstoqnov',
			'stephdau',
			'stephenharris',
			'stevenkword',
			'stormrockwell',
			'stubgo',
			'sudar',
			'supercoder',
			'swissspidy',
			'szaqal21',
			'takayukister',
			'technopolitica',
			'teinertb',
			'tejas5989',
			'tellyworth',
			'terwdan',
			'tfrommen',
			'tharsheblows',
			'themiked',
			'thepelkus',
			'timmydcrawford',
			'timothyblynjacobs',
			'timph',
			'tmatsuur',
			'tomdxw',
			'topher1kenobe',
			'trepmal',
			'triplejumper12',
			'truongwp',
			'tymvie',
			'tyxla',
			'utkarshpatel',
			'vaishuagola27',
			'vijustin',
			'voldemortensen',
			'vortfu',
			'welcher',
			'westonruter',
			'whyisjake',
			'wonderboymusic',
			'wpfo',
			'wpsmith',
			'xknown',
			'xrmx',
			'ze3kr',
			'zinigor',
			'zoonini',
		);
	}

	function external_libraries() {
		return array(
			array( 'Backbone.js', 'http://backbonejs.org/' ),
			array( 'Class POP3', 'https://squirrelmail.org/' ),
			array( 'Color Animations', 'https://plugins.jquery.com/color/' ),
			array( 'getID3()', 'http://getid3.sourceforge.net/' ),
			array( 'Horde Text Diff', 'https://pear.horde.org/' ),
			array( 'hoverIntent', 'http://cherne.net/brian/resources/jquery.hoverIntent.html' ),
			array( 'imgAreaSelect', 'http://odyniec.net/projects/imgareaselect/' ),
			array( 'Iris', 'https://github.com/Automattic/Iris' ),
			array( 'jQuery', 'https://jquery.com/' ),
			array( 'jQuery UI', 'https://jqueryui.com/' ),
			array( 'jQuery Hotkeys', 'https://github.com/tzuryby/jquery.hotkeys' ),
			array( 'jQuery serializeObject', 'http://benalman.com/projects/jquery-misc-plugins/' ),
			array( 'jQuery.query', 'https://plugins.jquery.com/query-object/' ),
			array( 'jQuery.suggest', 'https://github.com/pvulgaris/jquery.suggest' ),
			array( 'jQuery UI Touch Punch', 'http://touchpunch.furf.com/' ),
			array( 'json2', 'https://github.com/douglascrockford/JSON-js' ),
			array( 'Masonry', 'http://masonry.desandro.com/' ),
			array( 'MediaElement.js', 'http://mediaelementjs.com/' ),
			array( 'PclZip', 'http://www.phpconcept.net/pclzip/' ),
			array( 'PemFTP', 'https://www.phpclasses.org/package/1743-PHP-FTP-client-in-pure-PHP.html' ),
			array( 'phpass', 'http://www.openwall.com/phpass/' ),
			array( 'PHPMailer', 'https://github.com/PHPMailer/PHPMailer' ),
			array( 'Plupload', 'http://www.plupload.com/' ),
			array( 'random_compat', 'https://github.com/paragonie/random_compat' ),
			array( 'Requests', 'http://requests.ryanmccue.info/' ),
			array( 'SimplePie', 'http://simplepie.org/' ),
			array( 'The Incutio XML-RPC Library', 'https://code.google.com/archive/p/php-ixr/' ),
			array( 'Thickbox', 'http://codylindley.com/thickbox/' ),
			array( 'TinyMCE', 'https://www.tinymce.com/' ),
			array( 'Twemoji', 'https://github.com/twitter/twemoji' ),
			array( 'Underscore.js', 'http://underscorejs.org/' ),
			array( 'zxcvbn', 'https://github.com/dropbox/zxcvbn' ),
		);
	}
}
