<?php

include dirname( __FILE__ ) . '/parse.php';

$tests = array (
  'Mozilla/4.0 (Windows; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 2.0.50727)' => 'Windows Internet Explorer 6.0',
  'Mozilla/4.0 (MSIE 6.0; Windows NT 5.1)' => 'Windows Internet Explorer 6.0',
  'Mozilla/4.0 (MSIE 6.0; Windows NT 5.0)' => 'Windows Internet Explorer 6.0',
  'Mozilla/4.0 (compatible;MSIE 6.0;Windows 98;Q312461)' => 'Windows Internet Explorer 6.0',
  'Mozilla/4.0 (Compatible; Windows NT 5.1; MSIE 6.0) (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322; .NET CLR 2.0.50727)' => 'Windows Internet Explorer 6.0',
  'Mozilla/4.0 (compatible; U; MSIE 6.0; Windows NT 5.1)' => 'Windows Internet Explorer 6.0',
  'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1; Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1) ; .NET CLR 1.1.4322; .NET CLR 2.0.50727; .NET CLR 3.0.4506.2152; .NET CLR 3.5.30729; .NET4.0C; .NET4.0E)' => 'Windows Internet Explorer 7.0',
  'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; .NET4.0E; Tablet PC 2.0)' => 'Windows Internet Explorer 8.0',
  'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0; Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1) ; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; .NET4.0C; InfoPath.3; Tablet PC 2.0)' => 'Windows Internet Explorer 8.0',
  'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0)' => 'Windows Internet Explorer 9.0',
  'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-GB; rv:1.9.1.5) Gecko/20091102 Firefox/3.5.5 (.NET CLR 3.5.21022)' => 'Windows Firefox 3.5.5',
  'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.15) Gecko/20110303 Firefox/3.6.15 FirePHP/0.5' => 'Windows Firefox 3.6.15',
  'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.13 (KHTML, like Gecko) Chrome/9.0.597.98 Safari/534.13' => 'Windows Chrome 9.0.597.98',
  'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.16 (KHTML, like Gecko) Chrome/10.0.648.114 Safari/534.16' => 'Windows Chrome 10.0.648.114',
  'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/534.7 (KHTML, like Gecko) Chrome/7.0.517.41 Safari/534.7' => 'Windows Chrome 7.0.517.41',
  'Opera/9.80 (Windows NT 6.0; U; en) Presto/2.8.99 Version/11.10' => 'Windows Opera 11.10',
  'Opera/9.80 (Windows NT 5.1; U; cs) Presto/2.7.62 Version/11.01' => 'Windows Opera 11.01',
  'Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.13) Gecko/20101213 Opera/9.80 (Windows NT 6.1; U; zh-tw) Presto/2.7.62 Version/11.01' => 'Windows Opera 11.01',
  'Mozilla/5.0 (Windows NT 6.1; U; nl; rv:1.9.1.6) Gecko/20091201 Firefox/3.5.6 Opera 11.01' => 'Windows Opera 11.01',
  'Mozilla/5.0 (Windows NT 6.1; U; de; rv:1.9.1.6) Gecko/20091201 Firefox/3.5.6 Opera 11.01' => 'Windows Opera 11.01',
  'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; de) Opera 11.01' => 'Windows Opera 11.01',
  'Mozilla/5.0 (Windows; U; Windows NT 6.1; tr-TR) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27' => 'Windows Safari 5.0.4',
  'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.6; en-US; rv:1.9.2.3) Gecko/20100401 Firefox/3.6.3' => 'Macintosh Firefox 3.6.3',
  'Mozilla/5.0 (Macintosh; U; PPC Mac OS X 10_4_11; en) AppleWebKit/531.22.7 (KHTML, like Gecko) Version/4.0.5 Safari/531.22.7' => 'Macintosh Safari 4.0.5',
  'Mozilla/5.0 (Macintosh; U; PPC Mac OS X 10_4_11; en) AppleWebKit/533.18.1 (KHTML, like Gecko) Version/4.1.2 Safari/533.18.5' => 'Macintosh Safari 4.1.2',
  'Mozilla/5.0 (Macintosh; U; PPC Mac OS X; en) AppleWebKit/312.9 (KHTML, like Gecko) Safari/312.6' => 'Macintosh Safari 312.6',
  'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_3; en-us) AppleWebKit/533.16 (KHTML, like Gecko) Version/5.0 Safari/533.16' => 'Macintosh Safari 5.0',
  'Mozilla/5.0 (Macintosh; U; PPC Mac OS X 10.5; it; rv:1.9.0.19) Gecko/2010111021 Camino/2.0.6 (MultiLang) (like Firefox/3.0.19)' => 'Macintosh Camino 2.0.6',
  'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10.6; en; rv:1.9.0.18) Gecko/2010021619 Camino/2.0.2 (like Firefox/3.0.18)' => 'Macintosh Camino 2.0.2',
  'Mozilla/5.0 (Macintosh; U; PPC Mac OS X Mach-O; it; rv:1.8.1.21) Gecko/20090327 Camino/1.6.7 (MultiLang) (like Firefox/2.0.0.21pre)' => 'Macintosh Camino 1.6.7',
  'Mozilla/5.0 (Macintosh; U; Intel Mac OS X; en; rv:1.8.1.11) Gecko/20071128 Camino/1.5.4' => 'Macintosh Camino 1.5.4',
  'Mozilla/5.0 (Linux; U; Android 2.2; en-us; SGH-T959 Build/FROYO) AppleWebKit/533.1 (KHTML, like Gecko) Version/4.0 Mobile Safari/533.1' => 'Linux Mobile Safari 4.0',
  'Mozilla/5.0 (iPad; U; CPU iPhone OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B314 Safari/531.21.10' => 'iPad Safari 4.0.4',
  'Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.10' => 'iPad Safari 4.0.4',
  'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_2_6 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8E200 Safari/6533.18.5' => 'iPhone Safari 5.0.2',
  'Mozilla/5.0 (iPhone; U; CPU like Mac OS X; en) AppleWebKit/420+ (KHTML, like Gecko) Version/3.0 Mobile/1C25 Safari/419.3' => 'iPhone Safari 3.0',
  'Mozilla/4.0 (compatible; MSIE 7.0; Windows Phone OS 7.0; Trident/3.1; IEMobile/7.0) Asus;Galaxy6' => 'Windows Phone OS Internet Explorer Mobile 7.0',
  'Mozilla/4.0 (compatible; MSIE 7.0; Windows Phone OS 7.0; Trident/3.1; IEMobile/7.0; LG; GW910)' => 'Windows Phone OS Internet Explorer Mobile 7.0',
  'Mozilla/4.0 (compatible; MSIE 7.0; Windows Phone OS 7.0; Trident/3.1; IEMobile/7.0) LG;LG-E900h)' => 'Windows Phone OS Internet Explorer Mobile 7.0',
  'Mozilla/4.0 (compatible; Linux 2.6.10) NetFront/3.3 Kindle/1.0 (screen 600x800)' => 'Kindle Kindle 1.0',
  'Mozilla/5.0 (Linux; U; en-US) AppleWebKit/528.5+ (KHTML, like Gecko, Safari/528.5+) Version/4.0 Kindle/3.0 (screen 600x800; rotate)' => 'Kindle Kindle 4.0',
  'Mozilla/5.0 (Linux; U; en-US) AppleWebKit/528.5+ (KHTML, like Gecko, Safari/538.5+) Version/4.0 Kindle/3.0 (screen 600x800; rotate)' => 'Kindle Kindle 4.0',
  'Mozilla/5.0 (PlayBook; U; RIM Tablet OS 1.0.0; en-US) AppleWebKit/534.8 (KHTML, like Gecko) Version/0.0.1 Safari/534.8' => 'PlayBook PlayBook 0.0.1',
  'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_7) AppleWebKit/534.24 (KHTML, like Gecko) RockMelt/0.9.58.494 Chrome/11.0.696.71 Safari/534.24' => 'Macintosh RockMelt 0.9.58.494',
  'Mozilla/5.0 (Linux; U; Android 3.1; en-us; GT-P7510 Build/HMJ37) AppleWebKit/534.13 (KHTML, like Gecko) Version/4.0 Safari/534.13' => 'Android Safari 4.0',
);

$pass = $fail = 0;
$fails = array();

foreach ( $tests as $ua => $assert ) {
	$parsed = browsehappy_parse_user_agent( $ua );
	$result = $parsed['platform'] . ' ' . $parsed['name'] . ' ' . $parsed['version'];
	if ( $assert === $result ) {
		++$pass;
	} else {
		++$fail;
		$fails[ $ua ] = array( $assert, $result );
	}
}

if ( 'cli' != php_sapi_name() )
	echo '<pre>';

echo "$pass passes, $fail failures.\n\n";

foreach ( $fails as $ua => $data ) {
	list( $assert, $result ) = $data;
	echo "$ua\n should be  : $assert\n detected as: $result\n\n";
}
