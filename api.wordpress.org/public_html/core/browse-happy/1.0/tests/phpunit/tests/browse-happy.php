<?php

include dirname( __FILE__ ) . '/../../../parse.php';

/**
 *
 * @group browse-happy
 */
class Tests_Browse_Happy extends PHPUnit_Framework_TestCase {

	/**
	 * Data provider for test_browsehappy_parse_user_agent().
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $header   'User-Agent' header value.
	 *         @type string $expected Expected browser name and version.
	 *     }
	 * }
	 */
	function data_browse_happy() {
		return [

			// Android Browser

			[
				'Mozilla/5.0 (Linux; U; Android 2.2; en-us; SGH-T959 Build/FROYO) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1',
				'Android Android Browser 4.0',
			],
			[
				'Mozilla/5.0 (Linux; U; Android 2.3.4; en-us; NOOK BNTV250 Build/GINGERBREAD 1.4.3) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Safari/533.1',
				'Android Android Browser 4.0',
			],
			[
				'Mozilla/5.0 (Linux; U; Android 3.1; en-us; GT-P7510 Build/HMJ37) AppleWebKit/534.13 (KHTML, like Gecko) Version/4.0 Safari/534.13',
				'Android Android Browser 4.0',
			],
			[
				'Mozilla/5.0 (Linux; U; Android 4.0.4; pt-br; MZ608 Build/7.7.1-141-7-FLEM-UMTS-LA) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Safari/534.30',
				'Android Android Browser 4.0',
			],
			[ // on Galaxy SIII
				'Mozilla/5.0 (Linux; U; Android 4.4.2; en-us; SCH-I535 Build/KOT49H) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30',
				'Android Android Browser 4.0',
			],

			// Camino

			[
				'Mozilla/5.0 (Macintosh; U; Intel Mac OS X; en; rv:1.8.1.11) Gecko/20071128 Camino/1.5.4',
				'Macintosh Camino 1.5.4',
			],
			[
				'Mozilla/5.0 (Macintosh; U; PPC Mac OS X Mach-O; it; rv:1.8.1.21) Gecko/20090327 Camino/1.6.7 (MultiLang) (like Firefox/2.0.0.21pre)',
				'Macintosh Camino 1.6.7',
			],
			[
				'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.6; en; rv:1.9.0.18) Gecko/2010021619 Camino/2.0.2 (like Firefox/3.0.18)',
				'Macintosh Camino 2.0.2',
			],
			[
				'Mozilla/5.0 (Macintosh; U; PPC Mac OS X 10.5; it; rv:1.9.0.19) Gecko/2010111021 Camino/2.0.6 (MultiLang) (like Firefox/3.0.19)',
				'Macintosh Camino 2.0.6',
			],

			// Chrome

			[
				'Mozilla/5.0 (Linux; Android 4.4.2; ASUS_T00J Build/KVT49L) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/30.0.0.0 Safari/537.36',
				'Android Chrome 30.0.0.0',
			],
			[ // on Galaxy SIII
				'Mozilla/5.0 (Linux; Android 4.3; SGH-I747M Build/JSS15J) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1700.99 Mobile Safari/537.36',
				'Android Chrome 32.0.1700.99',
			],
			[ // #1323
				'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58K; wv) AppleWebKit/537.36 (KHTML, like Gecko) Version/4.0 Chrome/45.0.2454.95 Mobile Safari/537.36',
				'Android Chrome 45.0.2454.95',
			],
			[
				'Mozilla/5.0 (Linux; Android 6.0.1; SM-T800 Build/MMB29K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.107 Safari/537.36',
				'Android Chrome 60.0.3112.107',
			],
			[
				'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.157 Safari/537.36',
				'Linux Chrome 44.0.2403.157',
			],
			[
				'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.106 Safari/537.36',
				'Linux Chrome 51.0.2704.106',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.57 Safari/537.17',
				'Macintosh Chrome 24.0.1312.57',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.90 Safari/537.36',
				'Macintosh Chrome 42.0.2311.90',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36',
				'Macintosh Chrome 56.0.2924.87',
			],
			[
				'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.7 (KHTML, like Gecko) Chrome/7.0.517.41 Safari/534.7',
				'Windows Chrome 7.0.517.41',
			],
			[
				'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.13 (KHTML, like Gecko) Chrome/9.0.597.98 Safari/534.13',
				'Windows Chrome 9.0.597.98',
			],
			[
				'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.16 (KHTML, like Gecko) Chrome/10.0.648.114 Safari/534.16',
				'Windows Chrome 10.0.648.114',
			],
			[
				'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2272.118 Safari/537.36',
				'Windows Chrome 41.0.2272.118',
			],
			[
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.99 Safari/537.36',
				'Windows Chrome 54.0.2840.99',
			],
			[
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.113 Safari/537.36',
				'Windows Chrome 60.0.3112.113',
			],

			// Firefox

			[
				'Mozilla/5.0 (X11; Linux x86_64; rv:10.0) Gecko/20150101 Firefox/20.0 (Chrome)',
				'Linux Firefox 20.0',
			],
			[
				'Mozilla/5.0 (X11; Linux x86_64; rv:10.0) Gecko/20150101 Firefox/47.0 (Chrome)',
				'Linux Firefox 47.0',
			],
			[
				'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:54.0) Gecko/20100101 Firefox/54.0',
				'Linux Firefox 54.0',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:40.0) Gecko/20100101 Firefox/40.0',
				'Macintosh Firefox 40.0',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:50.0) Gecko/20100101 Firefox/50.0',
				'Macintosh Firefox 50.0',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.12; rv:53.0) Gecko/20100101 Firefox/53.0',
				'Macintosh Firefox 53.0',
			],
			[
				'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.9.1.5) Gecko/20091102 Firefox/3.5.5 (.NET CLR 3.5.21022)',
				'Windows Firefox 3.5.5',
			],
			[
				'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.6; en-US; rv:1.9.2.3) Gecko/20100401 Firefox/3.6.3',
				'Macintosh Firefox 3.6.3',
			],
			[
				'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.15) Gecko/20110303 Firefox/3.6.15 FirePHP/0.5',
				'Windows Firefox 3.6.15',
			],
			[
				'Mozilla/5.0 (Windows NT 6.3; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0',
				'Windows Firefox 41.0',
			],
			[
				'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:50.0) Gecko/20100101 Firefox/50.0',
				'Windows Firefox 50.0',
			],
			[
				'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:52.0) Gecko/20100101 Firefox/52.0',
				'Windows Firefox 52.0',
			],
			[
				'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:54.0) Gecko/20100101 Firefox/54.0',
				'Windows Firefox 54.0',
			],
			[
				'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:54.0) Gecko/20100101 Firefox/64.0',
				'Windows Firefox 64.0',
			],

			// Internet Explorer

			[
				'Mozilla/4.0 (Windows; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727)',
				'Windows Internet Explorer 6.0',
			],
			[
				'Mozilla/4.0 (MSIE 6.0; Windows NT 5.1)',
				'Windows Internet Explorer 6.0',
			],
			[
				'Mozilla/4.0 (MSIE 6.0; Windows NT 5.0)',
				'Windows Internet Explorer 6.0',
			],
			[
				'Mozilla/4.0 (compatible;MSIE 6.0;Windows 98;Q312461)',
				'Windows Internet Explorer 6.0',
			],
			[
				'Mozilla/4.0 (Compatible; Windows NT 5.1; MSIE 6.0) (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)',
				'Windows Internet Explorer 6.0',
			],
			[
				'Mozilla/4.0 (compatible; U; MSIE 6.0; Windows NT 5.1)',
				'Windows Internet Explorer 6.0',
			],
			[
				'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1) ; .NET CLR 1.1.4322; .NET CLR 2.0.50727; .NET CLR 3.0.4506.2152; .NET CLR 3.5.30729; .NET4.0C; .NET4.0E)',
				'Windows Internet Explorer 7.0',
			],
			[
				'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; Tablet PC 2.0)',
				'Windows Internet Explorer 8.0',
			],
			[
				'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0; Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1) ; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; InfoPath.3; Tablet PC 2.0)',
				'Windows Internet Explorer 8.0',
			],
			[
				'Mozilla/4.0 (compatible; MSIE 9.0; Windows NT 6.1)',
				'Windows Internet Explorer 9.0',
			],
			[
				'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)',
				'Windows Internet Explorer 9.0',
			],
			[ // #2587
				'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.2; Trident/6.0)',
				'Windows Internet Explorer 10.0',
			],
			[ // #2587
				'Mozilla/5.0 (Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko',
				'Windows Internet Explorer 11',
			],
			[
				'Mozilla/4.0 (compatible; MSIE 7.0; Windows Phone OS 7.0; Trident/3.1; IEMobile/7.0) Asus;Galaxy6',
				'Windows Phone OS Internet Explorer Mobile 7.0',
			],
			[
				'Mozilla/4.0 (compatible; MSIE 7.0; Windows Phone OS 7.0; Trident/3.1; IEMobile/7.0; LG; GW910)',
				'Windows Phone OS Internet Explorer Mobile 7.0',
			],
			[
				'Mozilla/4.0 (compatible; MSIE 7.0; Windows Phone OS 7.0; Trident/3.1; IEMobile/7.0) LG;LG-E900h)',
				'Windows Phone OS Internet Explorer Mobile 7.0',
			],
			[
				'Mozilla/5.0 (compatible; MSIE 9.0; Windows Phone OS 7.5; Trident/5.0; IEMobile/9.0; NOKIA; Lumia 710)',
				'Windows Phone OS Internet Explorer Mobile 9.0',
			],
			[
				'Mozilla/5.0 (compatible; MSIE 10.0; Windows Phone 8.0; Trident/6.0; IEMobile/10.0; ARM; Touch; NOKIA; Lumia 520)',
				'Windows Phone OS Internet Explorer Mobile 10.0',
			],
			[
				'Mozilla/5.0 (Mobile; Windows Phone 8.1; Android 4.0; ARM; Trident/7.0; Touch; rv:11.0; IEMobile/11.0; Microsoft; Lumia 435) like iPhone OS 7_0_3 Mac OS X AppleWebKit/537 (KHTML, like Gecko) Mobile Safari/537',
				'Windows Phone OS Internet Explorer Mobile 11',
			],
			[
				'Mozilla/5.0 (Windows Phone 8.1; ARM; Trident/7.0; Touch; rv:11.0; IEMobile/11.0; NOKIA; Lumia 520) like Gecko',
				'Windows Phone OS Internet Explorer Mobile 11',
			],

			// Microsoft Edge

			[
				'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Safari/537.36 Edge/12.10240',
				'Windows Microsoft Edge 12.10240',
			],
			[
				'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2486.0 Safari/537.36 Edge/13.10586',
				'Windows Microsoft Edge 13.10586',
			],
			[
				'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.79 Safari/537.36 Edge/14.14393',
				'Windows Microsoft Edge 14.14393',
			],
			[
				'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36 Edge/15.15063',
				'Windows Microsoft Edge 15.15063'
			],
			[
				'Mozilla/5.0 (Windows Phone 10.0; Android 4.2.1; Microsoft; Lumia 640 LTE) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/46.0.2486.0 Mobile Safari/537.36 Edge/13.10586',
				'Windows Phone OS Microsoft Edge 13.10586',
			],
			[
				'Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1; Microsoft; Lumia 950) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.79 Mobile Safari/537.36 Edge/14.14393',
				'Windows Phone OS Microsoft Edge 14.14393',
			],
			[
				'Mozilla/5.0 (Windows Phone 10.0; Android 6.0.1; Xbox; Xbox One) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Mobile Safari/537.36 Edge/15.15063',
				'Windows Phone OS Microsoft Edge 15.15063',
			],

			// Nokia Browser

			[
				'Mozilla/5.0 (Linux; Android 4.1.2; Nokia_X Build/JZO54K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.82 Mobile Safari/537.36 NokiaBrowser/1.2.0.12',
				'Android Nokia Browser 1.2.0.12',
			],
			[
				'Mozilla/5.0 (MeeGo; NokiaN9) AppleWebKit/534.13 (KHTML, like Gecko) NokiaBrowser/8.5.0 Mobile Safari/534.13',
				'Mobile Nokia Browser 8.5.0',
			],

			// Opera

			[
				'Opera/9.80 (X11; Linux zvav; U; en) Presto/2.8.119 Version/11.10',
				'Linux Opera 11.10',
			],
			[
				'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.87 Safari/537.36 OPR/37.0.2178.31 (Edition beta)',
				'Linux Opera 37.0.2178.31',
			],
			[
				'Opera/9.80 (Macintosh; Intel Mac OS X 10.10.5) Presto/2.12.388 Version/12.16',
				'Macintosh Opera 12.16',
			],
			[
				'Opera/9.80 (Windows NT 5.1; U; cs) Presto/2.7.62 Version/11.01',
				'Windows Opera 11.01',
			],
			[
				'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.13) Gecko/20101213 Opera/9.80 (Windows NT 6.1; U; zh-tw) Presto/2.7.62 Version/11.01',
				'Windows Opera 11.01',
			],
			[
				'Mozilla/5.0 (Windows NT 6.1; U; nl; rv:1.9.1.6) Gecko/20091201 Firefox/3.5.6 Opera 11.01',
				'Windows Opera 11.01',
			],
			[
				'Mozilla/5.0 (Windows NT 6.1; U; de; rv:1.9.1.6) Gecko/20091201 Firefox/3.5.6 Opera 11.01',
				'Windows Opera 11.01',
			],
			[
				'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; de) Opera 11.01',
				'Windows Opera 11.01',
			],
			[
				'Opera/9.80 (Windows NT 6.0; U; en) Presto/2.8.99 Version/11.10',
				'Windows Opera 11.10',
			],
			[ // #3161
				'Opera/9.80 (Windows NT 6.2; WOW64) Presto/2.12.388 Version/12.18',
				'Windows Opera 12.18',
			],
			[
				'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.112 Safari/537.36 OPR/36.0.2130.80',
				'Windows Opera 36.0.2130.80',
			],
			[
				'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/56.0.2924.87 Safari/537.36 OPR/43.0.2442.991',
				'Windows Opera 43.0.2442.991',
			],

			// Opera Mini

			[
				'Opera/9.80 (Android; Opera Mini/24.0.2254/62.178; U; en) Presto/2.12.423 Version/12.16',
				'Android Opera Mini 12.16',
			],
			[
				'Opera/9.80 (J2ME/MIDP; Opera Mini/4.2/28.3590; U; en) Presto/2.8.119 Version/11.10',
				'Mobile Opera Mini 11.10',
			],
			[
				'Opera/9.80 (SpreadTrum; Opera Mini/4.4.31492/66.299; U; en) Presto/2.12.423 Version/12.16',
				'Mobile Opera Mini 12.16',
			],

			// Safari

			[
				'Mozilla/5.0 (Windows; U; Windows NT 6.1; tr-TR) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27',
				'Windows Safari 5.0.4',
			],
			[
				'Mozilla/5.0 (Macintosh; U; PPC Mac OS X 10_4_11; en) AppleWebKit/531.22.7 (KHTML, like Gecko) Version/4.0.5 Safari/531.22.7',
				'Macintosh Safari 4.0.5',
			],
			[
				'Mozilla/5.0 (Macintosh; U; PPC Mac OS X 10_4_11; en) AppleWebKit/533.18.1 (KHTML, like Gecko) Version/4.1.2 Safari/533.18.5',
				'Macintosh Safari 4.1.2',
			],
			[
				'Mozilla/5.0 (Macintosh; U; PPC Mac OS X; en) AppleWebKit/312.9 (KHTML, like Gecko) Safari/312.6',
				'Macintosh Safari 312.6',
			],
			[
				'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_3; en-us) AppleWebKit/533.16 (KHTML, like Gecko) Version/5.0 Safari/533.16',
				'Macintosh Safari 5.0',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/601.4.4 (KHTML, like Gecko) Version/9.0.3 Safari/601.4.4',
				'Macintosh Safari 9.0.3',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/603.3.8 (KHTML, like Gecko) Version/10.1.2 Safari/603.3.8',
				'Macintosh Safari 10.1.2',
			],
			[
				'Mozilla/5.0 (iPad; U; CPU iPhone OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B314 Safari/531.21.10',
				'iPad Safari 4.0.4',
			],
			[
				'Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.10',
				'iPad Safari 4.0.4',
			],
			[
				'Mozilla/5.0 (iPad; CPU OS 9_3_2 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13F69 Safari/601.1',
				'iPad Safari 9.0',
			],
			[
				'Mozilla/5.0 (iPad; CPU OS 10_2_1 like Mac OS X) AppleWebKit/602.4.6 (KHTML, like Gecko) Version/10.0 Mobile/14D27 Safari/602.1',
				'iPad Safari 10.0',
			],
			[
				'Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en) AppleWebKit/420+ (KHTML, like Gecko) Version/3.0 Mobile/1C25 Safari/419.3',
				'iPhone Safari 3.0',
			],
			[
				'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_2_6 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8E200 Safari/6533.18.5',
				'iPhone Safari 5.0.2',
			],
			[
				'Mozilla/5.0 (iPhone; CPU iPhone OS 9_3 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Version/9.0 Mobile/13E188a Safari/601.1',
				'iPhone Safari 9.0',
			],
			[
				'Mozilla/5.0 (iPhone; CPU iPhone OS 10_2_1 like Mac OS X) AppleWebKit/602.4.6 (KHTML, like Gecko) Version/10.0 Mobile/14D27 Safari/602.1',
				'iPhone Safari 10.0',
			],

			// Miscellaneous

			[
				'Mozilla/4.0 (compatible; Linux 2.6.10) NetFront/3.3 Kindle/1.0 (screen 600x800)',
				'Kindle Kindle 1.0',
			],
			[
				'Mozilla/5.0 (Linux; U; en-US) AppleWebKit/528.5+ (KHTML, like Gecko, Safari/528.5+) Version/4.0 Kindle/3.0 (screen 600x800; rotate)',
				'Kindle Kindle 4.0',
			],
			[
				'Mozilla/5.0 (Linux; U; en-US) AppleWebKit/528.5+ (KHTML, like Gecko, Safari/538.5+) Version/4.0 Kindle/3.0 (screen 600x800; rotate)',
				'Kindle Kindle 4.0',
			],
			[
				'Mozilla/5.0 (PlayBook; U; RIM Tablet OS 1.0.0; en-US) AppleWebKit/534.8 (KHTML, like Gecko) Version/0.0.1 Safari/534.8',
				'PlayBook PlayBook 0.0.1',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_7) AppleWebKit/534.24 (KHTML, like Gecko) RockMelt/0.9.58.494 Chrome/11.0.696.71 Safari/534.24',
				'Macintosh RockMelt 0.9.58.494',
			],

			// Unknown

			[
				'Dalvik/1.6.0 (Linux; U; Android 4.1.1; BroadSign Xpress 1.0.14 B- (720) Build/JRO03H)',
				'Android unknown',
			],
			[ // on Galaxy SIII
				'Dalvik/1.6.0 (Linux; U; Android 4.4.2; SCH-I535 Build/KOT49H)',
				'Android unknown',
			],
			[
				'Mozilla/5.0 (iPhone; CPU iPhone OS 9_3_2 like Mac OS X) AppleWebKit/601.1.46 (KHTML, like Gecko) Mobile/13F69 [FBAN/FBIOS;FBAV/59.0.0.51.142;FBBV/33266808;FBRV/0;FBDV/iPhone7,1;FBMD/iPhone;FBSN/iPhone OS;FBSV/9.3.2;FBSS/3;FBCR/Telkomsel;FBID/phone;FBLC/en_US;FBOP/5] evaliant',
				'iPhone unknown', // Actually: iPhone Facebook App 59.0.0.51.142
			],
			[
				'Nokia-MIT-Browser/3.0',
				'unknown', // It's really a Nokia Browser, but not critical to recognize as such.
			],

		];
	}

	/**
	 * @dataProvider data_browse_happy
	 *
	 * @param string $header   'User-Agent' header value.
	 * @param string $expected Expected browser name and version.
	 */
	function test_browsehappy_parse_user_agent( $header, $expected ) {
		$parsed = browsehappy_parse_user_agent( $header );
		$result = $parsed['platform'] . ' ' . $parsed['name'] . ' ' . $parsed['version'];

		$this->assertEquals( $expected, trim( $result ) );
	}

	/**
	 * @dataProvider data_browse_happy
	 *
	 * @param string $header 'User-Agent' header value.
	 */
	function test_insecure_browsers( $header ) {
		$parsed = browsehappy_parse_user_agent( $header );

		if ( 'Internet Explorer' === $parsed['name'] && version_compare( $parsed['version'], '11', '<' ) ) {
			$this->assertTrue( $parsed['insecure'] );
		} elseif ( 'Firefox' === $parsed['name'] && version_compare( $parsed['version'], '52', '<' ) ) {
			$this->assertTrue( $parsed['insecure'] );
		} elseif ( 'Opera' === $parsed['name'] && version_compare( $parsed['version'], '12.18', '<' ) ) {
			$this->assertTrue( $parsed['insecure'] );
		} elseif ( 'Safari' === $parsed['name'] && version_compare( $parsed['version'], '10', '<' ) && ! $parsed['mobile'] ) {
			$this->assertTrue( $parsed['insecure'] );
		} else {
			$this->assertFalse( $parsed['insecure'] );
		}
	}

	/**
	 * Test that the 'upgrade' parsed data field is correct.
	 *
	 * @dataProvider data_browse_happy
	 *
	 * @param string $header 'User-Agent' header value.
	 */
	function test_upgrade_browsers( $header ) {
		$parsed = browsehappy_parse_user_agent( $header );

		// Currently, mobile browsers are not flagged as upgradable.
		if ( $parsed['mobile'] ) {
			$this->assertFalse( $parsed['upgrade'] );
			return;
		}

		$versions = get_browser_current_versions();

		if ( ! empty( $versions[ $parsed['name'] ] ) ) {
			if ( version_compare( $parsed['version'], $versions[ $parsed['name'] ], '<' ) ) {
				$this->assertTrue( $parsed['upgrade'] );
			} else {
				$this->assertFalse( $parsed['upgrade'] );
			}
		} else {
			$this->assertFalse( $parsed['upgrade'] );
		}
	}

	/**
	 * @dataProvider data_browse_happy
	 *
	 * @param string $header 'User-Agent' header value.
	 */
	function test_mobile_browsers( $header ) {
		$parsed = browsehappy_parse_user_agent( $header );

		if ( in_array( $parsed['platform'], array( 'Android', 'iPad', 'iPhone', 'Mobile', 'PlayBook', 'RIM Tablet OS', 'Windows Phone OS' ) ) ) {
			$this->assertTrue( $parsed['mobile'] );
		} else {
			$this->assertFalse( $parsed['mobile'] );
		}
	}

}
