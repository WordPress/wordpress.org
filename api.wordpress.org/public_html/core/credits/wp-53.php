<?php

class WP_53_Credits extends WP_Credits {

	public function groups() {
		$groups = [
			'core-developers'         => [
				'name'    => 'Noteworthy Contributors',
				'type'    => 'titles',
				'shuffle' => false,
				'data'    => [
					'matt'           => [ 'Matt Mullenweg', 'Release Lead' ],
					'francina'       => [ 'Francesca Marano', 'Release Lead' ],
					'davidbaumwald'  => [ 'David Baumwald', 'Release Lead' ],
					'youknowriad'    => [ 'Riad Benguella', 'Core Developer' ],
					'kirasong'       => [ 'Kira Song', 'Core Developer' ],
					'audrasjb'       => [ 'Jean-Baptiste Audras', 'Core Developer' ],
					'SergeyBiryukov' => [ 'Sergey Biryukov', 'Core Developer' ],
					'azaozz'         => [ 'Andrew Ozz', 'Core Developer' ],
					'desrosj'        => [ 'Jonathan Desrosiers', 'Core Developer' ],
					'karmatosed'     => [ 'Tammie Lister', 'Core Developer' ],
					'afercia'        => [ 'Andrea Fercia', 'Core Developer' ],
					'joemcgill'      => [ 'Joe McGill', 'Core Developer' ],
					'mapk'           => 'Mark Uraine',
					'Anlino'         => 'Anders Norén',
					'ianbelanger'    => 'Ian Belanger',
					'williampatton'  => 'William Patton',
					'justinahinon'   => 'Justin Ahinon',
					'mikerbg'        => 'Mike Reid',
					'marybaum'       => 'Mary Baum',
					'Rarst'          => 'Andrey Savchenko',
					'jrf'            => 'Juliette Reinders Folmer',
					'aristath'       => 'Ari Stathopoulos',
					'kjellr'         => 'Kjell Reigstad',
					'chanthaboune'   => 'Josepha Haden',
					'JeffPaul'       => 'Jeff Paul',
					'mcsf'           => 'Miguel Fonseca',
					'matveb'         => 'Matías Ventura',
				],
			],
			'contributing-developers' => [
				'name'    => false,
				'type'    => 'titles',
				'shuffle' => true,
				'data'    => [
					'jorgefilipecosta'  => 'Jorge Costa',
					'aduth'             => 'Andrew Duthie',
					'gziolo'            => 'Grzegorz Ziółkowski',
					'garrett-eclipse'   => [ 'Garrett Hyder', '162eb6b7137a1d51b56d73b9a3ba8b8a' ],
					'whyisjake'         => 'Jake Spurlock',
					'iseulde'           => 'Ella van Durpe',
					'pento'             => 'Gary Pendergast',
					'mukesh27'          => 'Mukesh Panchal',
					'talldanwp'         => 'Daniel Richards',
					'kadamwhite'        => 'K. Adam White',
					'birgire'           => 'Birgir Erlendsson',
					'TimothyBlynJacobs' => 'Timothy Jacobs',
					'ramiy'             => 'Rami Yushuvaev',
					'Clorith'           => 'Marius Jensen',
					'pbiron'            => 'Paul Biron',
					'donmhico'          => 'Michael Panaga',
					'epiqueras'         => 'Enrique Piqueras',
					'adamsilverstein'   => 'Adam Silverstein',
					'Joen'              => 'Joen Asmussen',
					'johnbillion'       => 'John Blackbourn',
					'noisysocks'        => 'Robert Anderson',
					'ocean90'           => 'Dominik Schilling',
					'peterwilsoncc'     => 'Peter Wilson',
					'Soean'             => 'Soren Wrede',
					'spacedmonkey'      => 'Jonny Harris',
					'itowhid06'         => 'Towhidul Islam',
					'swissspidy'        => 'Pascal Birchler',
					'mkaz'              => 'Marcus Kazmierczak',
					'dekervit'          => 'Tugdual de Kerviler',
					'andraganescu'      => 'Andrei Draganescu',
					'jorbin'            => 'Aaron Jorbin',
					'nosolosw'          => 'Andrés Maneiro',
					'david.binda'       => 'David Binovec',
					'dkarfa'            => 'Debabrata Karfa',
					'etoledom'          => 'Eduardo Toledo',
					'netweb'            => 'Stephen Edgar',
					'wppinar'           => 'Pinar Olguc',
					'chetan200891'      => 'Chetan Prajapati',
					'dsifford'          => 'Derek Sifford',
					'afragen'           => 'Andy Fragen',
					'melchoyce'         => 'Mel Choyce',
					'isabel_brison'     => 'Isabel Brison',
					'antpb'             => 'Anthony Burchell',
					'assassinateur'     => 'Seghir Nadir',
					'Hareesh Pillai'    => 'Hareesh Pillai',
					'mattchowning'      => 'Matt Chowning',
					'nerrad'            => 'Darren Ethier',
					'SergioEstevao'     => 'Sérgio Estêvão',
					'get_dave'          => 'Dave Smith',
					'johnjamesjacoby'   => 'John James Jacoby',
					'boonebgorges'      => 'Boone Gorges',
					'poena'             => 'Carolina Nymark',
					'ryelle'            => 'Kelly Dwan',
					'anevins'           => 'Andrew Nevins',
				],
			],
		];

		return $groups;
	}

	public function props() {
		return [
			'123host',
			'1994rstefan',
			'5hel2l2y',
			'aaroncampbell',
			'abhijitrakas',
			'abrain',
			'abrightclearweb',
			'acalfieri',
			'acosmin',
			'adamsilverstein',
			'adamsoucie',
			'adhitya03',
			'aduth',
			'afercia',
			'afragen',
			'agengineering',
			'ahdeubzer',
			'airathalitov',
			'ajayghaghretiya1',
			'ajitbohra',
			'ajlende',
			'aksdvp',
			'aksl95',
			'albertomake',
			'alexclassroom',
			'alexeyskr',
			'alexsanford1',
			'alextran',
			'alexvorn2',
			'aljullu',
			'allancole',
			'allendav',
			'alvarogois',
			'amolv',
			'anantajitjg',
			'andg',
			'andizer',
			'andraganescu',
			'andreamiddleton',
			'andrewtaylor-1',
			'anevins',
			'angelagibson',
			'anischarolia',
			'anlino',
			'antpb',
			'apermo',
			'aprea',
			'arafat',
			'aravindajith',
			'archon810',
			'arena',
			'aristath',
			'artisticasad',
			'arunsathiya',
			'arush',
			'asadkn',
			'ashwinpc',
			'assassinateur',
			'atachibana',
			'ate-up-with-motor',
			'atimmer',
			'atlasmahesh',
			'au87',
			'aubreypwd',
			'audrasjb',
			'augustuswm',
			'aurooba',
			'avinapatel',
			'ayeshrajans',
			'ayubi',
			'azaozz',
			'b-07',
			'backermann1978',
			'bassgang',
			'benjamin_zekavica',
			'bennemann',
			'bfintal',
			'bgermann',
			'bhaktirajdev',
			'bibliofille',
			'biranit',
			'birgire',
			'bitcomplex',
			'bjornw',
			'blogginglife',
			'boblinthorst',
			'boemedia',
			'boga86',
			'boonebgorges',
			'bor0',
			'bradleyt',
			'brentswisher',
			'bronsonquick',
			'bsetiawan88',
			'burhandodhy',
			'caercam',
			'casiepa',
			'cathibosco1',
			'cbravobernal',
			'cdog',
			'celloexpressions',
			'chandrapatel',
			'chanthaboune',
			'chaton666',
			'chesio',
			'chetan200891',
			'chintan1896',
			'chrico',
			'christian1012',
			'christophherr',
			'chrisvanpatten',
			'cleancoded',
			'clorith',
			'cmagrin',
			'codesue',
			'codex-m',
			'coffee2code',
			'collet',
			'compilenix',
			'courtney0burton',
			'crazyjaco',
			'cristianozanca',
			'cybr',
			'daleharrison',
			'danbuk',
			'danielbachhuber',
			'danieliser',
			'danieltj',
			'daniloercoli',
			'danmicamediacom',
			'darthhexx',
			'daveshine',
			'davetgreen',
			'davidanderson',
			'davidbaumwald',
			'davidbinda',
			'daviedr',
			'davilera',
			'daxelrod',
			'dd32',
			'deapness',
			'decrecementofeliz',
			'dehisok',
			'dekervit',
			'dency',
			'denisco',
			'dennis_f',
			'derweili',
			'desaiuditd',
			'desrosj',
			'dfangstrom',
			'dharmin16',
			'dhavalkasvala',
			'dhuyvetter',
			'dianeco',
			'diddledan',
			'diedeexterkate',
			'diego-la-monica',
			'digitalapps',
			'dilipbheda',
			'dimadin',
			'dingo_d',
			'dinhtungdu',
			'dionysous',
			'dkarfa',
			'dlh',
			'dmsnell',
			'donmhico',
			'drewapicture',
			'drw158',
			'dshanske',
			'dsifford',
			'dswebsme',
			'dufresnesteven',
			'dukex',
			'dushanthi',
			'dvankooten',
			'earnjam',
			'eden159',
			'ediamin',
			'edocev',
			'ehtis',
			'ellatrix',
			'elliotcondon',
			'emiluzelac',
			'engelen',
			'epiqueras',
			'erikkroes',
			'estelaris',
			'etoledom',
			'evalarumbe',
			'faazshift',
			'fabiankaegy',
			'fabifott',
			'fblaser',
			'felipeelia',
			'fencer04',
			'fesovik',
			'fierevere',
			'flaviozavan',
			'flipkeijzer',
			'flixos90',
			'foysalremon',
			'francina',
			'freewebmentor',
			'galbaras',
			'garrett-eclipse',
			'garyj',
			'gchtr',
			'gdragon',
			'get_dave',
			'girlieworks',
			'glauberglauber',
			'goodevilgenius',
			'grapplerulrich',
			'gravityview',
			'greatislander',
			'greenshady',
			'gregsullivan',
			'grzegorzjanoszka',
			'gsayed786',
			'guddu1315',
			'gwendydd',
			'gwwar',
			'gziolo',
			'hardeepasrani',
			'hardipparmar',
			'hareesh-pillai',
			'harryfear',
			'harshbarach',
			'haszari',
			'hedgefield',
			'helen',
			'henrywright',
			'herbmiller',
			'herregroen',
			'hesyifei',
			'hideokamoto',
			'hirofumi2012',
			'hkandulla',
			'hlashbrooke',
			'hometowntrailers',
			'howdy_mcgee',
			'hoythan',
			'hypest',
			'iamjaydip',
			'ianbelanger',
			'iandunn',
			'ianmjones',
			'iceable',
			'imath',
			'immeet94',
			'intimez',
			'ipstenu',
			'iqbalbary',
			'ireneyoast',
			'irsdl',
			'isabel_brison',
			'iseulde',
			'ismailelkorchi',
			'ispreview',
			'itowhid06',
			'iworks',
			'ixkaito',
			'jagirbaheshwp',
			'jalpa1984',
			'jameskoster',
			'jameslnewell',
			'janak007',
			'jankimoradiya',
			'janwoostendorp',
			'jared_smith',
			'jarocks',
			'jarretc',
			'javeweb',
			'javorszky',
			'jayswadas',
			'jdgrimes',
			'jeffpaul',
			'jeichorn',
			'jenblogs4u',
			'jenkoian',
			'jeremyclarke',
			'jeremyfelt',
			'jfarthing84',
			'jffng',
			'jg-visual',
			'jikamens',
			'jipmoors',
			'jitendrabanjara1991',
			'jkitchen',
			'jmmathc',
			'jnylen0',
			'joakimsilfverberg',
			'jobthomas',
			'jodamo5',
			'joedolson',
			'joehoyle',
			'joemcgill',
			'joen',
			'johnbillion',
			'johnjamesjacoby',
			'johnregan3',
			'jojotjebaby',
			'jond',
			'jonoaldersonwp',
			'joostdevalk',
			'jorbin',
			'jorgefilipecosta',
			'josephscott',
			'joshuanoyce',
			'joshuawold',
			'joyously',
			'jrchamp',
			'jrf',
			'jsnajdr',
			'juanfra',
			'juiiee8487',
			'juliobox',
			'junktrunk',
			'justdaiv',
			'justinahinon',
			'kadamwhite',
			'kafleg',
			'kailanitish90',
			'kakshak',
			'kamrankhorsandi',
			'karlgroves',
			'karmatosed',
			'karthost',
			'katielgc',
			'kbrownkd',
			'kellychoffman',
			'kerfred',
			'ketanumretiya030',
			'ketuchetan',
			'kevinkovadia',
			'kharisblank',
			'killerbishop',
			'killua99',
			'kingkero',
			'kirasong',
			'kjellr',
			'knutsp',
			'koke',
			'kokers',
			'kraftbj',
			'kraftner',
			'kuus',
			'kyliesabra',
			'larrach',
			'laurelfulford',
			'lbenicio',
			'leogermani',
			'leonblade',
			'leprincenoir',
			'lessbloat',
			'lindstromer',
			'lisota',
			'littlebigthing',
			'lllor',
			'lordlod',
			'loreleiaurora',
			'lovememore',
			'loyaltymanufaktur',
			'luan-ramos',
			'luciano-croce',
			'luigipulcini',
			'luisherranz',
			'lukaswaudentio',
			'lukecarbis',
			'lukecavanagh',
			'luminuu',
			'm-e-h',
			'm1tk00',
			'maartenleenders',
			'maciejmackowiak',
			'macmanx',
			'maguiar',
			'mahesh901122',
			'majemedia',
			'malthert',
			'man4toman',
			'manikmist09',
			'manooweb',
			'manuelaugustin',
			'manzoorwanijk',
			'mapk',
			'marcelo2605',
			'marcguay',
			'marcomartins',
			'marcosalexandre',
			'marekhrabe',
			'markjaquith',
			'markoheijnen',
			'marybaum',
			'masummdar',
			'mat-lipe',
			'matstars',
			'matt',
			'mattchowning',
			'mattheu',
			'matthiasthiel',
			'mattyrob',
			'mauteri',
			'maximeculea',
			'maximejobin',
			'maxme',
			'mayanksonawat',
			'mbabker',
			'mboynes',
			'mchavezi',
			'mcsf',
			'mdgl',
			'mdwolinski',
			'mehidi258',
			'mehulkaklotar',
			'melchoyce',
			'melinedo',
			'meloniq',
			'michael-arestad',
			'michelweimerskirch',
			'michielatyoast',
			'miette49',
			'miguelvieira',
			'mihaiiceyro',
			'mihdan',
			'miinasikk',
			'mikehansenme',
			'mikejolley',
			'mikengarrett',
			'miqrogroove',
			'mista-flo',
			'miyauchi',
			'mkaz',
			'mnelson4',
			'mobeen-abdullah',
			'mohsinrasool',
			'monikarao',
			'moonomo',
			'mor10',
			'mppfeiffer',
			'mrahmadawais',
			'mrasharirfan',
			'mrmadhat',
			'msaari',
			'msaggiorato',
			'mspatovaliyski',
			'mt8biz',
			'mte90',
			'mtias',
			'mukesh27',
			'munyagu',
			'murgroland',
			'mzorz',
			'n7studios',
			'nacin',
			'nadir',
			'naveenkharwar',
			'nayana123',
			'needle',
			'neelpatel7295',
			'nerrad',
			'netweb',
			'nevma',
			'nextendweb',
			'nextscripts',
			'nfmohit',
			'niallkennedy',
			'nickdaugherty',
			'nickylimjj',
			'nicolad',
			'nielsdeblaauw',
			'nielslange',
			'nikolastoqnow',
			'nikschavan',
			'niq1982',
			'nishitlangaliya',
			'nmenescardi',
			'noahtallen',
			'nofearinc',
			'noisysocks',
			'nosolosw',
			'notnownikki',
			'noyle',
			'nrqsnchz',
			'obenland',
			'ocean90',
			'odminstudios',
			'omarreiss',
			'onlanka',
			'otto42',
			'ov3rfly',
			'ounziw',
			'oxyc',
			'ozmatflc',
			'paaljoachim',
			'palmiak',
			'paragoninitiativeenterprises',
			'paresh07',
			'patilswapnilv',
			'patilvikasj',
			'patrelentlesstechnologycom',
			'paulschreiber',
			'pbearne',
			'pbiron',
			'pedromendonca',
			'pento',
			'peterwilsoncc',
			'phillipjohn',
			'phpdocs',
			'pierlo',
			'pikamander2',
			'pixolin',
			'poena',
			'powerbuoy',
			'pputzer',
			'pratikkry',
			'pratikthink',
			'presskopp',
			'priyankkpatel',
			'progremzion',
			'promz',
			'quantumstate',
			'quicoto',
			'raajtram',
			'raamdev',
			'rabmalin',
			'raboodesign',
			'rahe',
			'rahulvaza',
			'ramiy',
			'ramon-fincken',
			'rarst',
			'raubvogel',
			'rbrishabh',
			'rclations',
			'rconde',
			'rebasaurus',
			'redsweater',
			'reikodd',
			'remcotolsma',
			'retrofox',
			'riddhiehta02',
			'rilwis',
			'rmccue',
			'robi-bobi',
			'rockfire',
			'rogueresearch',
			'ronakganatra',
			'roytanck',
			'ryan',
			'ryankienstra',
			'ryelle',
			'ryokuhi',
			'sabernhardt',
			'sachyya-sachet',
			'salzano',
			'samgordondev',
			'samuelfernandez',
			'sarathar',
			'sasiddiqui',
			'saskak',
			'sathyapulse',
			'sbardian',
			'scvleon',
			'sebastianpisula',
			'sebastienserre',
			'seedsca',
			'sergeybiryukov',
			'sergioestevao',
			'sergiomdgomes',
			'seuser',
			'sgastard',
			'sgr33n',
			'shadyvb',
			'shamim51',
			'sharaz',
			'shashank3105',
			'shawfactor',
			'shelob9',
			'shital-patel',
			'siliconforks',
			'simison',
			'simonjanin',
			'simono',
			'sinatrateam',
			'sirreal',
			'sixes',
			'skithund',
			'slaffik',
			'slobodanmanic',
			'smerriman',
			'snapfractalpop',
			'socalchristina',
			'soean',
			'solarissmoke',
			'soulseekah',
			'spacedmonkey',
			'spaceshipone',
			'spectacula',
			'spenserhale',
			'splitti',
			'spuds10',
			'sstoqnov',
			'steevithak',
			'stevenkword',
			'studiotwee',
			'studyboi',
			'subrataemfluence',
			'subratamal',
			'sudhiryadav',
			'superpoincare',
			'svanhal',
			'svovaf',
			'swapnild',
			'swissspidy',
			'talldanwp',
			'tanvirul',
			'tazotodua',
			'tdh',
			'technote0space',
			'tellyworth',
			'tessak22',
			'tferry',
			'tfrommen',
			'tha_sun',
			'thakkarhardik',
			'themes-1',
			'themezly',
			'thomaswm',
			'thrijith',
			'thulshof',
			'tigertech',
			'timhavinga',
			'timon33',
			'timothyblynjacobs',
			'timph',
			'tinkerbelly',
			'tjnowell',
			'tmatsuur',
			'tmdesigned',
			'tobiasbg',
			'tobifjellner',
			'toddhalfpenny',
			'tollmanz',
			'tonybogdanov',
			'torres126',
			'tosho',
			'toszcze',
			'trasweb',
			'travisnorthcutt',
			'travisseitler',
			'trepmal',
			'triplejumper12',
			'truchot',
			'truongwp',
			'utsav72640',
			'vaishalipanchal',
			'vbaimas',
			'veminom',
			'venutius',
			'viper007bond',
			'vishalkakadiya',
			'vishitshah',
			'vjik',
			'vladlu',
			'vladwtz',
			'voldemortensen',
			'vortfu',
			'vrimill',
			'w3rkjana',
			'waleedt93',
			'webcommsat',
			'webdados',
			'webmandesign',
			'welcher',
			'westonruter',
			'whyisjake',
			'williampatton',
			'withinboredom',
			'wonderboymusic',
			'worldweb',
			'wpboss',
			'wpdavis',
			'wpdennis',
			'wpfed',
			'wpgurudev',
			'wppinar',
			'xavortm',
			'xel1045',
			'xendo',
			'xknown',
			'xkon',
			'xyfi',
			'yanngarcia',
			'yarnboy',
			'yashar_hv',
			'yoavf',
			'yodiyo',
			'youknowriad',
			'yvettesonneveld',
			'zaantar',
			'zalak151291',
			'zebulan',
			'zinigor',
			'zodiac1978',
		];
	}

	public function external_libraries() {
		return [
			[ 'Babel Polyfill', 'https://babeljs.io/docs/en/babel-polyfill' ],
			[ 'Backbone.js', 'http://backbonejs.org/' ],
			[ 'Class POP3', 'https://squirrelmail.org/' ],
			[ 'clipboard.js', 'https://clipboardjs.com/' ],
			[ 'Closest', 'https://github.com/jonathantneal/closest' ],
			[ 'CodeMirror', 'https://codemirror.net/' ],
			[ 'Color Animations', 'https://plugins.jquery.com/color/' ],
			[ 'getID3()', 'http://getid3.sourceforge.net/' ],
			[ 'FormData', 'https://github.com/jimmywarting/FormData' ],
			[ 'Horde Text Diff', 'https://pear.horde.org/' ],
			[ 'hoverIntent', 'http://cherne.net/brian/resources/jquery.hoverIntent.html' ],
			[ 'imgAreaSelect', 'http://odyniec.net/projects/imgareaselect/' ],
			[ 'Iris', 'https://github.com/Automattic/Iris' ],
			[ 'jQuery', 'https://jquery.com/' ],
			[ 'jQuery UI', 'https://jqueryui.com/' ],
			[ 'jQuery Hotkeys', 'https://github.com/tzuryby/jquery.hotkeys' ],
			[ 'jQuery serializeObject', 'http://benalman.com/projects/jquery-misc-plugins/' ],
			[ 'jQuery.query', 'https://plugins.jquery.com/query-object/' ],
			[ 'jQuery.suggest', 'https://github.com/pvulgaris/jquery.suggest' ],
			[ 'jQuery UI Touch Punch', 'http://touchpunch.furf.com/' ],
			[ 'json2', 'https://github.com/douglascrockford/JSON-js' ],
			[ 'Lodash', 'https://lodash.com/' ],
			[ 'Masonry', 'http://masonry.desandro.com/' ],
			[ 'MediaElement.js', 'http://mediaelementjs.com/' ],
			[ 'Moment', 'http://momentjs.com/' ],
			[ 'PclZip', 'http://www.phpconcept.net/pclzip/' ],
			[ 'PemFTP', 'https://www.phpclasses.org/package/1743-PHP-FTP-client-in-pure-PHP.html' ],
			[ 'phpass', 'http://www.openwall.com/phpass/' ],
			[ 'PHPMailer', 'https://github.com/PHPMailer/PHPMailer' ],
			[ 'Plupload', 'http://www.plupload.com/' ],
			[ 'random_compat', 'https://github.com/paragonie/random_compat' ],
			[ 'React', 'https://reactjs.org/' ],
			[ 'Redux', 'https://redux.js.org/' ],
			[ 'Requests', 'http://requests.ryanmccue.info/' ],
			[ 'SimplePie', 'http://simplepie.org/' ],
			[ 'The Incutio XML-RPC Library', 'https://code.google.com/archive/p/php-ixr/' ],
			[ 'Thickbox', 'http://codylindley.com/thickbox/' ],
			[ 'TinyMCE', 'https://www.tinymce.com/' ],
			[ 'Twemoji', 'https://github.com/twitter/twemoji' ],
			[ 'Underscore.js', 'http://underscorejs.org/' ],
			[ 'whatwg-fetch', 'https://github.com/github/fetch' ],
			[ 'zxcvbn', 'https://github.com/dropbox/zxcvbn' ],
		];
	}
}

