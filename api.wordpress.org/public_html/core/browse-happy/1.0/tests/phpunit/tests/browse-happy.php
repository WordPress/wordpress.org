<?php

include dirname( __FILE__ ) . '/../../../parse.php';

/**
 *
 * @group browse-happy
 */
class Tests_Browse_Happy extends \PHPUnit\Framework\TestCase {

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

			// Amazon Silk

			[
				'Mozilla/5.0 (Linux; U; Android 2.3.4; en-us; Silk/1.0.146.3-Gen4_12000410) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1 Silk-Accelerated=true',
				'Fire OS Amazon Silk 1.0.146.3',
			],
			[
				'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_3; en-us; Silk/1.0.146.3-Gen4_12000410) AppleWebKit/533.16 (KHTML, like Gecko) Version/5.0 Safari/533.16 Silk-Accelerated=true',
				'Fire OS Amazon Silk 1.0.146.3',
			],
			[
				'Mozilla/5.0 (Linux; U; Android 4.0.3; en-us; KFTT Build/IML74K) AppleWebKit/537.36 (KHTML, like Gecko) Silk/3.68 like Chrome/39.0.2171.93 Safari/537.36',
				'Fire OS Amazon Silk 3.68',
			],
			[
				'Mozilla/5.0 (Linux; Android 4.0.3; KFTT Build/IML74K) AppleWebKit/537.36 (KHTML, like Gecko) Silk/51.2.1 like Chrome/51.0.2704.81 Safari/537.36',
				'Fire OS Amazon Silk 51.2.1',
			],
			[
				'Mozilla/5.0 (Linux; Android 5.1.1; KFDOWI Build/LVY48F) AppleWebKit/537.36 (KHTML, like Gecko) Silk/58.2.6 like Chrome/58.0.3029.83 Safari/537.36',
				'Fire OS Amazon Silk 58.2.6',
			],

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
			[
				'Mozilla/5.0 (Linux; U; Android 4.2.2; en-us; IdeaTab S6000-F Build/JDQ39) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Safari/534.30',
				'Android Android Browser 4.0', // Actually: Symbian, but not worth accurately detecting.
			],
			[ // on Galaxy SIII
				'Mozilla/5.0 (Linux; U; Android 4.4.2; en-us; SCH-I535 Build/KOT49H) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 Mobile Safari/534.30',
				'Android Android Browser 4.0',
			],

			// BlackBerry Browser

			[
				'Mozilla/5.0 (BB10; Touch) AppleWebKit/537.35+ (KHTML, like Gecko) Version/10.3.2.2876 Mobile Safari/537.35+',
				'Mobile BlackBerry Browser',
			],
			[
				'BlackBerry; U; Blackberry 9900; en',
				'Mobile BlackBerry Browser',
			],
			[
				'BlackBerry8520/5.0.0.592 Profile/MIDP-2.1 Configuration/CLDC-1.1 VendorID/168',
				'Mobile BlackBerry Browser',
			],
			[
				'Mozilla/5.0 (BlackBerry; U; BlackBerry 9720; en) AppleWebKit/534.11+ (KHTML, like Gecko) Version/7.1.0.1121 Mobile Safari/534.11+',
				'Mobile BlackBerry Browser 7.1.0.1121',
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

			// Chromium

			[
				'Mozilla/5.0 (Linux; Ubuntu 14.04 like Android 4.4) AppleWebKit/537.36 Chromium/35.0.1870.2 Mobile Safari/537.36',
				'Android Chromium 35.0.1870.2',
			],
			[
				'Mozilla/5.0 (Linux; Ubuntu 15.04 like Android 4.4) AppleWebKit/537.36 Chromium/55.0.2883.75 Mobile Safari/537.36',
				'Android Chromium 55.0.2883.75',
			],
			[
				'Mozilla/5.0 (X11; FreeBSD amd64) AppleWebKit/537.36 (KHTML, like Gecko) Chromium/57.0.2987.110 Safari/537.36',
				'FreeBSD Chromium 57.0.2987.110',
			],
			[
				'Mozilla/5.0 (iPhone; U; en-us; CPU OS 3_0 like Mac OS X) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.37 Chromium/57.0.2987.133',
				'iPhone Chromium 57.0.2987.133',
			],
			[
				'Mozilla/5.0 (SMART-TV; X11; Linux armv7l) AppleWebKit/537.42 (KHTML, like Gecko) Chromium/25.0.1349.2 Chrome/25.0.1349.2 Safari/537.42',
				'Linux Chromium 25.0.1349.2',
			],
			[
				'Mozilla/5.0 (Linux; Ubuntu 14.04) AppleWebKit/537.36 Chromium/35.0.1870.2 Safari/537.36',
				'Linux Chromium 35.0.1870.2',
			],
			[
				'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/58.0.3029.110 Chrome/58.0.3029.110 Safari/537.36',
				'Linux Chromium 58.0.3029.110',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.36 (KHTML, like Gecko) Chromium/61.0.3163.79 Chrome/61.0.3163.79 Safari/537.36',
				'Macintosh Chromium 61.0.3163.79',
			],
			[
				'LiveSlides/1.6.12.0 Office/16.0 (Microsoft Windows 10 Home 6.2.9200.0) CefSharp/43.0.0.0 Cef/r3.2357.1287.g861c26e Chromium/43.0.2357.130',
				'Chromium 43.0.2357.130',
			],
			[
				'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chromium/41.0.2228.0 Chrome/41.0.2228.0 Safari/537.36',
				'Windows Chromium 41.0.2228.0',
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
			[
				'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; AS; rv:11.0) like Gecko',
				'Windows Internet Explorer 11.0',
			],
			[ // #2587
				'Mozilla/5.0 (Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko',
				'Windows Internet Explorer 11.0',
			],
			[
				'Mozilla/5.0 (Windows NT 6.3; Win64; x64; Trident/7.0; Touch; rv:11.0) like Gecko',
				'Windows Internet Explorer 11.0',
			],
			[
				'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.3; WOW64; Trident/7.0; .NET4.0C; .NET4.0E; .NET CLR 2.0.50727; .NET CLR 3.0.30729; .NET CLR 3.5.30729)',
				'Windows Internet Explorer 11.0',
			],
			[
				'Mozilla/5.0 (compatible, MSIE 11, Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko',
				'Windows Internet Explorer 11.0',
			],
			[ // Fictitious Trident version
				'Mozilla/5.0 (Windows NT 6.3; Trident/34.0; rv:38.0) like Gecko',
				'Windows Internet Explorer 34.0',
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
				'Windows Phone OS Internet Explorer Mobile 11.0',
			],
			[
				'Mozilla/5.0 (Windows Phone 8.1; ARM; Trident/7.0; Touch; rv:11.0; IEMobile/11.0; NOKIA; Lumia 520) like Gecko',
				'Windows Phone OS Internet Explorer Mobile 11.0',
			],

			// Kindle Browser

			[
				'Mozilla/4.0 (compatible; Linux 2.6.10) NetFront/3.3 Kindle/1.0 (screen 600x800)',
				'Fire OS Kindle Browser 1.0',
			],
			[
				'Mozilla/5.0 (Linux; U; en-US) AppleWebKit/528.5+ (KHTML, like Gecko, Safari/528.5+) Version/4.0 Kindle/3.0 (screen 600x800; rotate)',
				'Fire OS Kindle Browser 4.0',
			],
			[
				'Mozilla/5.0 (Linux; U; en-US) AppleWebKit/528.5+ (KHTML, like Gecko, Safari/538.5+) Version/4.0 Kindle/3.0 (screen 600x800; rotate)',
				'Fire OS Kindle Browser 4.0',
			],
			[
				'Mozilla/5.0 (Linux; U; Android 2.3.4; en-us; Kindle Fire Build/GINGERBREAD) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1',
				'Fire OS Kindle Browser 4.0',
			],
			[
				'Mozilla/5.0 (X11; U; Linux armv7l like Android; en-us) AppleWebKit/531.2+ (KHTML, like Gecko) Version/5.0 Safari/533.2+ Kindle/3.0+',
				'Fire OS Kindle Browser 5.0',
			],

			// Konqueror

			[
				'Mozilla/5.0 (X11; FreeBSD) AppleWebKit/537.21 (KHTML, like Gecko) konqueror/4.14.3 Safari/537.21',
				'FreeBSD Konqueror 4.14.3',
			],
			[
				'Mozilla/5.0 (compatible; Konqueror/3; Linux)',
				'Linux Konqueror 3',
			],
			[
				'Mozilla/5.0 (compatible; Konqueror/4.4; Linux 2.6.32-22-generic; X11; en_US) KHTML/4.4.3 (like Gecko) Kubuntu',
				'Linux Konqueror 4.4',
			],
			[
				'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.21 (KHTML, like Gecko) konqueror/4.14.2 Safari/537.21',
				'Linux Konqueror 4.14.2',
			],
			[
				'Mozilla/5.0 (compatible; Konqueror/4.1; OpenBSD) KHTML/4.1.4 (like Gecko)',
				'OpenBSD Konqueror 4.1',
			],
			[
				'Mozilla/5.0 (compatible; Konqueror/3.5; SunOS) KHTML/3.5.1 (like Gecko)',
				'SunOS Konqueror 3.5',
			],
			[
				'Mozilla/5.0 (Windows; Windows i686) KHTML/4.10.2 (like Gecko) Konqueror/4.10',
				'Windows Konqueror 4.10',
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
			[
				'Nokia5250/10.0.011 (SymbianOS/9.4; U; Series60/5.0 Mozilla/5.0; Profile/MIDP-2.1 Configuration/CLDC-1.1 ) AppleWebKit/525 (KHTML, like Gecko) Safari/525 3gpp-gba',
				'Symbian Nokia Browser',
			],
			[
				'Mozilla/5.0 (SymbianOS/9.4; Series60/5.0 Nokia5233/51.1.002; Profile/MIDP-2.1 Configuration/CLDC-1.1 ) AppleWebKit/533.4 (KHTML, like Gecko) NokiaBrowser/7.3.1.33 Mobile Safari/533.4 3gpp-gba',
				'Symbian Nokia Browser 7.3.1.33',
			],
			[
				'Mozilla/5.0 (Symbian/3; Series60/5.3 NokiaN8-00/111.040.1511; Profile/MIDP-2.1 Configuration/CLDC-1.1 ) AppleWebKit/535.1 (KHTML, like Gecko) NokiaBrowser/8.3.1.4 Mobile Safari/535.1 3gpp-gba',
				'Symbian Nokia Browser 8.3.1.4',
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
			[
				'Opera/9.80 (J2ME/MIDP; Opera Mini/9.80 (S60; SymbOS; Opera Mobi/23.348; U; en) Presto/2.5.25 Version/10.54',
				'Symbian Opera Mini 10.54',
			],

			// Ovi Browser

			[
				'Mozilla/5.0 (Series40; Nokia2055/03.20; Profile/MIDP-2.1 Configuration/CLDC-1.1) Gecko/20100401 S40OviBrowser/5.5.0.0.27',
				'Symbian Ovi Browser 5.5.0.0.27'
			],

			// Pale Moon

			[
				'Mozilla/5.0 (X11; Linux x86_64; rv:2.1) Gecko/20100101 Goanna/20160701 PaleMoon/26.3.3',
				'Linux Pale Moon 26.3.3',
			],
			[
				'Mozilla/5.0 (X11; Linux x86_64; rv:38.9) Gecko/20100101 Goanna/2.2 Firefox/38.9 PaleMoon/26.5.0',
				'Linux Pale Moon 26.5.0',
			],
			[
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:38.9) Gecko/20100101 Goanna/2.1 Firefox/38.9 PaleMoon/26.3.3',
				'Windows Pale Moon 26.3.3',
			],
			[
				'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:45.9) Gecko/20100101 Goanna/3.2 Firefox/45.9 PaleMoon/27.3.0',
				'Windows Pale Moon 27.3.0',
			],

			// Puffin

			[
				'Mozilla/5.0 (Linux; Android 4.4.2; BLOOM Build/KOT49H; it-it) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/42.0.2311.135 Mobile Safari/537.36 Puffin/6.1.3.1599AP',
				'Android Puffin 6.1.3.1599',
			],
			[
				'Mozilla/5.0 (iPad; CPU OS 9_3_5 like Mac OS X; en-CA) AppleWebKit/537.36 (KHTML, like Gecko) Version/9.3.5 Mobile/13G36 Safari/537.36 Puffin/5.2.0IT Chrome/55.0.2623',
				'iPad Puffin 5.2.0',
			],
			[
				'Mozilla/5.0 (X11; U; Linux x86_64; en-us) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.114 Safari/537.36 Puffin/4.8.0.2965AP',
				'Mobile Puffin 4.8.0.2965',
			],

			// QQ Browser

			[
				'MQQBrowser/2.0,Nokia6681/2.0',
				'Mobile QQ Browser 2.0',
			],
			[
				'Mozilla/5.0 (Linux; U; Android 4.4.2; zh-cn; GT-I9500 Build/KOT49H) AppleWebKit/537.36 (KHTML, like Gecko)Version/4.0 MQQBrowser/5.0 QQ-URL-Manager Mobile Safari/537.36',
				'Android QQ Browser 5.0',
			],
			[
				'Mozilla/5.0 (Linux; U; Android 4.3; zh-cn; SM-T2556 Build/JLS36C) AppleWebKit/537.36 (KHTML, like Gecko)Version/4.0 Chrome/37.0.0.0 MQQBrowser/6.9 Mobile Safari/537.36',
				'Android QQ Browser 6.9',
			],
			[
				'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; QQBrowser/6.12)',
				'Windows QQ Browser 6.12',
			],
			[
				'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/53.0.2785.104 Safari/537.36 Core/1.53.3226.400 QQBrowser/9.6.11681.400',
				'Windows QQ Browser 9.6.11681.400',
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

			// Samsung Browser

			[
				'Mozilla/5.0 (Linux; Android 5.1.1; SAMSUNG SM-G360T1 Build/LMY47X) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/3.3 Chrome/38.0.2125.102 Mobile Safari/537.36',
				'Android Samsung Browser 3.3',
			],
			[
				'Mozilla/5.0 (Linux; Android 7.0; SAMSUNG SM-G950U Build/NRD90M) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/5.4 Chrome/51.0.2704.106 Mobile Safari/537.36',
				'Android Samsung Browser 5.4',
			],
			[
				'Mozilla/5.0 (SMART-TV; Linux; Tizen 2.4.0) AppleWebkit/538.1 (KHTML, like Gecko) SamsungBrowser/1.1 TV Safari/538.1',
				'Linux Samsung Browser 1.1',
			],

			// SeaMonkey

			[
				'Mozilla/5.0 (X11; FreeBSD i386; rv:43.0) Gecko/20100101 Firefox/43.0 SeaMonkey/2.40',
				'FreeBSD SeaMonkey 2.40',
			],
			[
				'Mozilla/5.0 (X11; Linux i686; rv:49.0) Gecko/20100101 Firefox/49.0 SeaMonkey/2.46',
				'Linux SeaMonkey 2.46',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.10; rv:43.0) Gecko/20100101 Firefox/43.0 SeaMonkey/2.40',
				'Macintosh SeaMonkey 2.40',
			],
			[
				'Mozilla/5.0 (X11; OpenBSD i386; rv:36.0) Gecko/20100101 Firefox/36.0 SeaMonkey/2.33.1',
				'OpenBSD SeaMonkey 2.33.1',
			],
			[
				'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:43.0) Gecko/20100101 Firefox/43.0 SeaMonkey/2.40',
				'Windows SeaMonkey 2.40',
			],

			// UC Browser

			[
				'Mozilla/5.0 (Linux; U; Android 4.4.2; id; SM-G900 Build/KOT49H) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 UCBrowser/9.9.2.467 U3/0.8.0 Mobile Safari/534.30 evaliant',
				'Android UC Browser 9.9.2.467',
			],
			[
				'Mozilla/5.0 (Linux; U; Android 4.2.2; en-US; Micromax A102 Build/MicromaxA102) AppleWebKit/534.30 (KHTML, like Gecko) Version/4.0 UCBrowser/11.1.0.882 U3/0.8.0 Mobile Safari/534.30',
				'Android UC Browser 11.1.0.882',
			],
			[
				'UCWEB/8.8 (iPhone; CPU OS_6; en-US)AppleWebKit/534.1 U3/3.0.0 Mobile',
				'iPhone UC Browser 8.8',
			],
			[
				'UCWEB/2.0 (Java; U; MIDP-2.0; Nokia203/20.37) U2/1.0.0 UCBrowser/8.7.0.218 U2/1.0.0 Mobile',
				'Mobile UC Browser 8.7.0.218',
			],
			[
				'Nokia5200/2.0 (05.00) Profile/MIDP-2.0 Configuration/CLDC-1.1 UCWEB/2.0 (Java; U; MIDP-2.0; id; Nokia5200) U2/1.0.0 UCBrowser/9.5.0.449 U2/1.0.0 Mobile',
				'Mobile UC Browser 9.5.0.449',
			],
			[
				'UCWEB/2.0 (Symbian; U; S60 V5; en-US; Nokia5233) U2/1.0.0 UCBrowser/9.2.0.336 U2/1.0.0 Mobile',
				'Symbian UC Browser 9.2.0.336',
			],
			[
				'Mozilla/5.0 (Windows NT 6.2; ARM; Trident/7.0; Touch; rv:11.0; WPDesktop) like Gecko UCBrowser/4.2.1.541',
				'Windows UC Browser 4.2.1.541',
			],

			// Vivaldi

			[
				'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36 Vivaldi/1.2.490.43',
				'Linux Vivaldi 1.2.490.43',
			],
			[
				'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.98 Safari/537.36 Vivaldi/1.6.689.40',
				'Macintosh Vivaldi 1.6.689.40',
			],
			[
				'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.98 Safari/537.36 Vivaldi/1.6.689.40',
				'Windows Vivaldi 1.6.689.40',
			],

			// Miscellaneous

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
				'Mobile unknown', // It's really a Nokia Browser, but not critical to recognize as such.
			],
			[
				'SonyEricssonW995a/R1GB Browser/NetFront/3.4 Profile/MIDP-2.1 Configuration/CLDC-1.1 JavaPlatform/JP-8.4.4',
				'Mobile unknown',
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

		if ( 'Internet Explorer' === $parsed['name'] ) {
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

		if ( in_array( $parsed['platform'], array( 'Android', 'Fire OS', 'iPad', 'iPhone', 'Mobile', 'PlayBook', 'RIM Tablet OS', 'Symbian', 'Windows Phone OS' ) ) ) {
			$this->assertTrue( $parsed['mobile'] );
		} else {
			$this->assertFalse( $parsed['mobile'] );
		}
	}

	function test_browsehappy_get_explicit_browser_tokens() {
		$tokens = browsehappy_get_explicit_browser_tokens();

		$this->assertTrue( is_array( $tokens ) );

		$this->assertArrayHasKey( 'Camino', $tokens );
		$this->assertEmpty( $tokens['Camino'] );

		$this->assertArrayHasKey( 'S40OviBrowser', $tokens );
		$this->assertArrayHasKey( 'name', $tokens['S40OviBrowser'] );
		$this->assertEquals( 'Ovi Browser', $tokens['S40OviBrowser']['name'] );
		$this->assertArrayHasKey( 'mobile', $tokens['S40OviBrowser'] );
		$this->assertTrue( $tokens['S40OviBrowser']['mobile'] );
		$this->assertArrayHasKey( 'platform', $tokens['S40OviBrowser'] );
		$this->assertEquals( 'Symbian', $tokens['S40OviBrowser']['platform'] );
	}

}
