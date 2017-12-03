<?php

namespace Dotorg\API\Events;

if ( 'cli' !== php_sapi_name() ) {
	die();
}

/**
 * Main entry point
 */
function run_tests() {
	global $wpdb;
	define( 'RUNNING_TESTS', true );
	define( 'SAVEQUERIES',   true );

	require_once( dirname( __DIR__ ) . '/index.php' );

	$tests_failed = 0;
	$tests_failed += test_get_location();
	$tests_failed += test_get_events();
	$tests_failed += test_add_regional_wordcamps();
	$tests_failed += test_build_response();
	$tests_failed += test_is_client_core();
	$query_count  = count( $wpdb->queries );
	$query_time   = array_sum( array_column( $wpdb->queries, 1 ) );

	printf(
		"\n\nFinished running all tests.\n\n* %d tests failed\n* %d queries ran in %f seconds\n* Average time per query: %f seconds\n",
		$tests_failed,
		$query_count,
		$query_time,
		$query_time / $query_count
	);
}

/**
 * Output the results of an individual test
 *
 * @param int   $case_id
 * @param bool  $passed
 * @param mixed $expected_result
 * @param mixed $actual_result
 */
function output_results( $case_id, $passed, $expected_result, $actual_result ) {
	if ( $passed ) {
		return;
	}

	printf(
		"\n* %s: %s",
		$case_id,
		$passed ? 'PASSED' : '_FAILED_'
	);

	if ( ! $passed ) {
		$expected_output = is_scalar( $expected_result ) ? var_export( $expected_result, true ) : print_r( $expected_result, true );
		$actual_output   = is_scalar( $actual_result   ) ? var_export( $actual_result,   true ) : print_r( $actual_result,   true );

		printf(
			"\n\nExpected result: %s\nActual result: %s",
			$expected_output,
			$actual_output
		);
	}
}

/**
 * Test `get_location()`
 *
 * @return bool The number of failures
 */
function test_get_location() {
	$failed = 0;
	$cases  = get_location_test_cases();

	printf( "\nRunning %d location tests\n", count( $cases ) );

	foreach ( $cases as $case_id => $case ) {
		$actual_result = get_location( $case['input'] );

		// Normalize to lowercase to account for inconsistency in the IP database
		if ( isset( $actual_result['description'] ) && is_string( $actual_result['description'] ) ) {
			$actual_result['description'] = strtolower( $actual_result['description'] );
		}

		/*
		 * Normalize coordinates to account for minor differences in the databases
		 *
		 * Rounding to three decimal places means that we're still accurate within about 110 meters, which is
		 * good enough for our purposes.
		 *
		 * See https://gis.stackexchange.com/a/8674/49125
		 */
		if ( isset( $actual_result['latitude'], $actual_result['longitude'] ) ) {
			$actual_result['latitude']  = number_format( round( $actual_result['latitude'],  3 ), 3 );
			$actual_result['longitude'] = number_format( round( $actual_result['longitude'], 3 ), 3 );
		}

		$passed      = $case['expected'] === $actual_result;

		output_results( $case_id, $passed, $case['expected'], $actual_result );

		if ( ! $passed ) {
			$failed++;
		}
	}

	return $failed;
}

/**
 * Get the cases for testing `get_location()`
 *
 * @return array
 */
function get_location_test_cases() {
	 $cases = array(
		/*
		 * Only the country code is given
		 */
		'country-code-australia' => array(
			'input' => array(
				'country' => 'AU',
			),
			'expected' => array(
				'country' => 'AU',
			),
		),


		/*
		 * The country name, locale, and timezone are given
		 */
		'country-exonym-1-word' => array(
			'input' => array(
				'location_name' => 'Indonesia',
				'locale'        => 'id_ID',
				'timezone'      => 'Asia/Jakarta',
			),
			'expected' => array(
				'country' => 'ID',
				'description' => 'indonesia',
			),
		),

		'country-exonym-2-words' => array(
			'input' => array(
				'location_name' => 'Bosnia and Herzegovina',
				'locale'        => 'bs_BA',
				'timezone'      => 'Europe/Sarajevo',
			),
			'expected' => array(
				'country'     => 'BA',
				'description' => 'bosnia and herzegovina',
			),
		),


		/*
		 * A location couldn't be found
		 */
		'city-invalid-private-ip' => array(
			'input' => array(
				'location_name' => 'Rivendell',
				'ip'            => '127.0.0.1'
			),
			'expected' => false,
		),

		/*
		 * No input was provided
		 */
		'input-empty' => array(
			'input'    => array(),
			'expected' => array(),
		),


		/*
		 * The English city exonym, locale, and timezone are given
		 */
		'city-africa' => array(
			'input' => array(
				'location_name' => 'Nairobi',
				'locale'        => 'en_GB',
				'timezone'      => 'Africa/Nairobi',
			),
			'expected' => array(
				'description' => 'nairobi',
				'latitude'    => '-1.283',
				'longitude'   => '36.817',
				'country'     => 'KE',
			),
		),

		'city-asia' => array(
			'input' => array(
				'location_name' => 'Tokyo',
				'locale'        => 'ja',
				'timezone'      => 'Asia/Tokyo',
			),
			'expected' => array(
				'description' => 'tokyo',
				'latitude'    => '35.690',
				'longitude'   => '139.692',
				'country'     => 'JP',
			),
		),

		'city-europe' => array(
			'input' => array(
				'location_name' => 'Berlin',
				'locale'        => 'de_DE',
				'timezone'      => 'Europe/Berlin',
			),
			'expected' => array(
				'description' => 'berlin',
				'latitude'    => '52.524',
				'longitude'   => '13.411',
				'country'     => 'DE',
			),
		),

		'city-north-america' => array(
			'input' => array(
				'location_name' => 'Vancouver',
				'locale'        => 'en_CA',
				'timezone'      => 'America/Vancouver',
			),
			'expected' => array(
				'description' => 'vancouver',
				'latitude'    => '49.250',
				'longitude'   => '-123.119',
				'country'     => 'CA',
			),
		),

		'city-oceania' => array(
			'input' => array(
				'location_name' => 'Brisbane',
				'locale'        => 'en_AU',
				'timezone'      => 'Australia/Brisbane',
			),
			'expected' => array(
				'description' => 'brisbane',
				'latitude'    => '-27.468',
				'longitude'   => '153.028',
				'country'     => 'AU',
			),
		),

		// Many users never change the default `en_US` locale in Core
		'city-oceania-with-en_US' => array(
			'input' => array(
				'location_name' => 'Sydney',
				'locale'        => 'en_US',
				'timezone'      => 'Australia/Sydney',
			),
			'expected' => array(
				'description' => 'sydney',
				'latitude'    => '-33.868',
				'longitude'   => '151.207',
				'country'     => 'AU',
			),
		),

		'city-south-america' => array(
			'input' => array(
				'location_name' => 'Sao Paulo',
				'locale'        => 'pt_BR',
				'timezone'      => 'America/Sao_Paulo',
			),
			'expected' => array(
				'description' => 'sao paulo',
				'latitude'    => '-23.548',
				'longitude'   => '-46.636',
				'country'     => 'BR',
			),
		),

		// Users will often type them without the dash, bypassing an exact match
		'city-with-dashes-in-formal-name' => array(
			'input' => array(
				'location_name' => 'Osakashi',
				'locale'        => 'ja',
				'timezone'      => 'Asia/Tokyo',
			),
			'expected' => array(
				'description' => 'osakashi',
				'latitude'    => '34.694',
				'longitude'   => '135.502',
				'country'     => 'JP',
			),
		),

		// If a location is provided, the fallback search should be attempted before an IP search
		'fallback-with-public-ip' => array(
			'input' => array(
				'location_name' => 'Osakashi',
				'locale'        => 'ja',
				'timezone'      => 'Asia/Tokyo',
				'ip'            => '153.163.68.148', // Tokyo
			),
			'expected' => array(
				'description' => 'osakashi',
				'latitude'    => '34.694',
				'longitude'   => '135.502',
				'country'     => 'JP',
			),
		),

		'city-with-apostrophe-in-formal-name' => array(
			'input' => array(
				'location_name' => "Coeur d'Alene",
				'locale'        => 'en_US',
				'timezone'      => 'America/Los_Angeles',
			),
			'expected' => array(
				'description' => "coeur d'alene",
				'latitude'    => '47.678',
				'longitude'   => '-116.780',
				'country'     => 'US',
			),
		),

		'city-with-diacritics-in-query' => array(
			'input' => array(
				'location_name' => "Doña Ana",
				'locale'        => 'en_US',
				'timezone'      => 'America/Denver',
			),
			'expected' => array(
				'description' => "doña ana",
				'latitude'    => '32.390',
				'longitude'   => '-106.814',
				'country'     => 'US',
			),
		),

		'city-with-diacritics-in-formal-name-but-not-in-query' => array(
			'input' => array(
				'location_name' => "Dona Ana",
				'locale'        => 'en_US',
				'timezone'      => 'America/Denver',
			),
			'expected' => array(
				'description' => "dona ana",
				'latitude'    => '32.390',
				'longitude'   => '-106.814',
				'country'     => 'US',
			),
		),

		'city-with-period-in-query' => array(
			'input' => array(
				'location_name' => "St. Louis",
				'locale'        => 'en_US',
				'timezone'      => 'America/Chicago',
			),
			'expected' => array(
				'description' => "st. louis",
				'latitude'    => '38.627',
				'longitude'   => '-90.198',
				'country'     => 'US',
			),
		),

		'city-with-period-in-formal-name-but-not-in-query' => array(
			'input' => array(
				'location_name' => "St Louis",
				'locale'        => 'en_US',
				'timezone'      => 'America/Chicago',
			),
			'expected' => array(
				'description' => "st louis",
				'latitude'    => '38.627',
				'longitude'   => '-90.198',
				'country'     => 'US',
			),
		),

		/*
		 * The city endonym, locale, and timezone are given
		 *
		 * @todo
		 * This is currently failing. A query from PHP shows row id 2220957 has "Yaound?" instead of
		 * "Yaoundé", but it's correct in the database itself.
		 */
		 'city-endonym-accents-africa' => array(
			'input' => array(
				'location_name' => 'Yaoundé',
				'locale'        => 'fr_FR',
				'timezone'      => 'Africa/Douala',
			),
			'expected' => array(
				'description' => 'yaoundé',
				'latitude'    => '3.867',
				'longitude'   => '11.517',
				'country'     => 'CM',
			),
		),

		'city-endonym-non-latin-africa' => array(
			'input' => array(
				'location_name' => 'አዲስ አበ',
				'locale'        => 'am',
				'timezone'      => 'Africa/Addis_Ababa',
			),
			'expected' => array(
				'description' => 'አዲስ አበባ',
				'latitude'    => '9.025',
				'longitude'   => '38.747',
				'country'     => 'ET',
			),
		),

		'city-endonym-ideographic-asia1' => array(
			'input' => array(
				'location_name' => '白浜町宇佐崎南',
				'locale'        => 'ja',
				'timezone'      => 'Asia/Tokyo',
			),
			'expected' => array(
				'description' => '白浜町宇佐崎南',
				'latitude'    => '34.783',
				'longitude'   => '134.717',
				'country'     => 'JP',
			),
		),

		'city-endonym-ideographic-asia2' => array(
			'input' => array(
				'location_name' => 'تهران',
				'locale'        => 'fa_IR',
				'timezone'      => 'Asia/Tehran',
			),
			'expected' => array(
				'description' => 'تهران',
				'latitude'    => '35.694',
				'longitude'   => '51.422',
				'country'     => 'IR',
			),
		),

		'city-endonym-ideographic-asia3' => array(
			'input' => array(
				'location_name' => 'كراچى',
				'locale'        => 'ur',
				'timezone'      => 'Asia/Karachi',
			),
			'expected' => array(
				'description' => 'كراچى',
				'latitude'    => '24.906',
				'longitude'   => '67.082',
				'country'     => 'PK',
			),
		),

		'city-endonym-ideographic-asia4' => array(
			'input' => array(
				'location_name' => '京都',
				'locale'        => 'ja',
				'timezone'      => 'Asia/Tokyo',
			),
			'expected' => array(
				'description' => '京都',
				'latitude'    => '35.021',
				'longitude'   => '135.754',
				'country'     => 'JP',
			),
		),

		'city-endonym-ideographic-asia5' => array(
			'input' => array(
				'location_name' => '東京',
				'locale'        => 'ja',
				'timezone'      => 'Asia/Tokyo',
			),
			'expected' => array(
				'description' => '東京',
				'latitude'    => '35.690',
				'longitude'   => '139.692',
				'country'     => 'JP',
			),
		),

		// The database only has 大阪市 ("Osaka-shi"), not 大阪 ("Osaka"), so an exact match will for 大阪 will fail
		'city-endonym-ideographic-municipal-unit-asia' => array(
			'input' => array(
				'location_name' => '大阪',
				'locale'        => 'ja',
				'timezone'      => 'Asia/Tokyo',
			),
			'expected' => array(
				'description' => '大阪市',
				'latitude'    => '34.694',
				'longitude'   => '135.502',
				'country'     => 'JP',
			),
		),

		'city-endonym-europe' => array(
			'input' => array(
				'location_name' => 'Wien',
				'locale'        => 'de_DE',
				'timezone'      => 'Europe/Berlin',
			),
			'expected' => array(
				'description' => 'wien',
				'latitude'    => '48.208',
				'longitude'   => '16.372',
				'country'     => 'AT',
			),
		),

		'city-endonym-europe2' => array(
			'input' => array(
				'location_name' => 'Москва',
				'locale'        => 'ru_RU',
				'timezone'      => 'Europe/Moscow',
			),
			'expected' => array(
				'description' => 'Москва',
				'latitude'    => '55.752',
				'longitude'   => '37.616',
				'country'     => 'RU',
			),
		),

		// https://meta.trac.wordpress.org/ticket/3295
		'city-endonym-europe3-uppercase' => array(
			'input' => array(
				'location_name' => 'Санкт-Петербург',
				'locale'        => 'ru_RU',
				'timezone'      => 'Europe/Moscow',
			),
			'expected' => array(
				'description' => 'Санкт-Петербург',
				'latitude'    => '59.894',
				'longitude'   => '30.264',
				'country'     => 'RU',
			),
		),

		// https://meta.trac.wordpress.org/ticket/3295
		'city-endonym-europe3-lowercase' => array(
			'input' => array(
				'location_name' => 'санкт-петербург',
				'locale'        => 'ru_RU',
				'timezone'      => 'Europe/Moscow',
			),
			'expected' => array(
				'description' => 'Санкт-Петербург',
				'latitude'    => '59.894',
				'longitude'   => '30.264',
				'country'     => 'RU',
			),
		),

		'city-endonym-accents-north-america' => array(
			'input' => array(
				'location_name' => 'Ciudad de México',
				'locale'        => 'en_MX',
				'timezone'      => 'America/Mexico_City',
			),
			'expected' => array(
				'description' => 'ciudad de méxico',
				'latitude'    => '19.428',
				'longitude'   => '-99.128',
				'country'     => 'MX',
			),
		),

		'city-endonym-accents-oceania' => array(
			'input' => array(
				'location_name' => 'Hagåtña',
				'locale'        => 'en_US',
				'timezone'      => 'Pacific/Guam',
			),
			'expected' => array(
				'description' => 'hagåtña',
				'latitude'    => '13.476',
				'longitude'   => '144.749',
				'country'     => 'GU',
			),
		),

		'city-endonym-south-america' => array(
			'input' => array(
				'location_name' => 'Bogotá',
				'locale'        => 'es_CO',
				'timezone'      => 'America/Bogota',
			),
			'expected' => array(
				'description' => 'bogotá',
				'latitude'    => '4.610',
				'longitude'   => '-74.082',
				'country'     => 'CO',
			),
		),


		/*
		 * A combination of city, region, and country are given, along with the locale and timezone
		 *
		 * InvalidCity is used in tests that want to bypass the guess_location_from_city() tests and only test the country
		 */
		'1-word-city-region' => array(
			'input' => array(
				'location_name' => 'Portland Maine',
				'locale'        => 'en_US',
				'timezone'      => 'America/New_York',
			),
			'expected' => array(
				'description' => 'portland',
				'latitude'    => '43.661',
				'longitude'   => '-70.255',
				'country'     => 'US',
			),
		),

		'2-word-city-region' => array(
			'input' => array(
				'location_name' => 'São Paulo Brazil',
				'locale'        => 'pt_BR',
				'timezone'      => 'America/Sao_Paulo',
			),
			'expected' => array(
				'description' => 'são paulo',
				'latitude'    => '-23.548',
				'longitude'   => '-46.636',
				'country'     => 'BR',
			),
		),

		'city-1-word-country' => array(
			'input' => array(
				'location_name' => 'InvalidCity Canada',
				'locale'        => 'en_CA',
				'timezone'      => 'America/Vancouver',
			),
			'expected' => array(
				'country' => 'CA',
				'description' => 'canada',
			),
		),

		'city-2-word-country' => array(
			'input' => array(
				'location_name' => 'InvalidCity Dominican Republic',
				'locale'        => 'es_ES',
				'timezone'      => 'America/Santo_Domingo',
			),
			'expected' => array(
				'country' => 'DO',
				'description' => 'dominican republic',
			),
		),

		'city-3-word-country' => array(
			'input' => array(
				'location_name' => 'InvalidCity Central African Republic',
				'locale'        => 'fr_FR',
				'timezone'      => 'Africa/Bangui',
			),
			'expected' => array(
				'country' => 'CF',
				'description' => 'central african republic',
			),
		),

		'country-code' => array(
			'input' => array(
				'location_name' => 'GB',
				'locale'        => 'en_GB',
				'timezone'      => 'Europe/London',
			),
			'expected' => array(
				'country' => 'GB',
				'description' => 'united kingdom',
			),
		),

		'city-country-code' => array(
			'input' => array(
				'location_name' => 'InvalidCity BI',
				'locale'        => 'fr_FR',
				'timezone'      => 'Africa/Bujumbura',
			),
			'expected' => array(
				'country' => 'BI',
				'description' => 'burundi',
			),
		),

		/*
		 * Coordinates should take precedence over IP addresses
		 */
		'coordinates-over-ip-us' => array(
			'input' => array(
				'latitude'  => '47.6062100',
				'longitude' => '-122.3320700',
				'ip'        => '192.0.70.251',  // San Francisco, USA
				'timezone'  => 'America/Los_Angeles',
				'locale'    => 'en_US',
			),
			'expected' => array(
				'description' => false,
				'latitude'    => '47.606',
				'longitude'   => '-122.332',
			),
		),

		'coordinates-over-ip-africa' => array(
			'input' => array(
				'latitude'  => '-19.634233',
				'longitude' => '17.331767',
				'ip'        => '41.190.96.5',   // Tsumeb, Namibia
				'timezone'  => 'Africa/Windhoek',
				'locale'    => 'af',
			),
			'expected' => array(
				'description' => false,
				'latitude'    => '-19.634',
				'longitude'   => '17.332',
			),
		),

		/*
		 * Only the IPv4 address is given
		 *
		 * See https://awebanalysis.com/en/ipv4-directory/
		 */
		'ip-africa' => array(
			'input' => array( 'ip' => '41.191.232.22' ),
			'expected' => array(
				'description' => 'harare',
				'latitude'    => '-17.829',
				'longitude'   => '31.054',
				'country'     => 'ZW',
				'internal'    => true,
			),
		),

		'ip-asia' => array(
			'input' => array( 'ip' => '86.108.55.28' ),
			'expected' => array(
				'description' => 'amman',
				'latitude'    => '31.955',
				'longitude'   => '35.945',
				'country'     => 'JO',
				'internal'    => true,
			),
		),

		'ip-europe' => array(
			'input' => array( 'ip' => '80.95.186.144' ),
			'expected' => array(
				'description' => 'queens island',
				'latitude'    => '54.617',
				'longitude'   => '-5.900',
				'country'     => 'GB',
				'internal'    => true,
			),
		),

		'ip-north-america' => array(
			'input' => array( 'ip' => '189.147.186.0' ),
			'expected' => array(
				'description' => 'cuauhtemoc',
				'latitude'    => '19.417',
				'longitude'   => '-99.157',
				'country'     => 'MX',
				'internal'    => true,
			),
		),

		'ip-oceania' => array(
			'input' => array( 'ip' => '116.12.57.122' ),
			'expected' => array(
				'description' => 'auckland',
				'latitude'    => '-36.867',
				'longitude'   => '174.767',
				'country'     => 'NZ',
				'internal'    => true,
			),
		),

		'ip-south-america' => array(
			'input' => array( 'ip' => '181.66.32.136' ),
			'expected' => array(
				'description' => 'lima',
				'latitude'    => '-12.043',
				'longitude'   => '-77.028',
				'country'     => 'PE',
				'internal'    => true,
			),
		),

		/*
		 * Only an IPv6 address is given
		 *
		 * See https://www.google.com/intl/en/ipv6/statistics.html#tab=per-country-ipv6-adoption&tab=per-country-ipv6-adoption
		 * See https://awebanalysis.com/en/ipv6-directory/
		 * See https://www.google.com/search?q=australia+site%3Ahttps%3A%2F%2Fawebanalysis.com%2Fen%2Fipv6-directory%2F
		 */
		'ipv6-africa' => array(
			'input'    => array( 'ip' => '2c0f:f8f0:ffff:ffff:ffff:ffff:ffff:ffff' ),
			'expected' => array(
				'description' => 'harare',
				'latitude'    => '-17.829',
				'longitude'   => '31.054',
				'country'     => 'ZW',
				'internal'    => true,
			),
		),

		'ipv6-asia-anonymized' => array(
			'input'    => array( 'ip' => '2405:200:1000::' ),
			'expected' => array(
				'description' => 'mumbai',
				'latitude'    => '19.014',
				'longitude'   => '72.848',
				'country'     => 'IN',
				'internal'    => true,
			),
		),

		'ipv6-europe-anonymized' => array(
			'input'    => array( 'ip' => '2a02:578:1000::' ),
			'expected' => array(
				'description' => 'sint-niklaas',
				'latitude'    => '51.165',
				'longitude'   => '4.144',
				'country'     => 'BE',
				'internal'    => true,
			),
		),

		'ipv6-north-america-anonymized' => array(
			'input'    => array( 'ip' => '2605:a600::' ),
			'expected' => array(
				'description' => 'mountain view',
				'latitude'    => '37.386',
				'longitude'   => '-122.084',
				'country'     => 'US',
				'internal'    => true,
			),
		),

		'ipv6-oceania-collapsed-prefix' => array(
			'input'    => array( 'ip' => '::ffff:0190:c500' ),
			'expected' => array(
				'description' => 'sydney',
				'latitude'    => '-33.868',
				'longitude'   => '151.207',
				'country'     => 'AU',
				'internal'    => true,
			),
		),

		'ipv6-south-america' => array(
			'input'    => array( 'ip' => '2001:1388:6643:2736:10f1:897c:428c:1b3b' ),
			'expected' => array(
				'description' => 'ayacucho',
				'latitude'    => '-13.158',
				'longitude'   => '-74.224',
				'country'     => 'PE',
				'internal'    => true,
			),
		),
	);

	return $cases;
}

/**
 * Test `get_events()`
 *
 * @return bool The number of failures
 */
function test_get_events() {
	$failed = 0;
	$cases  = get_events_test_cases();

	printf( "\n\nRunning %d events tests\n", count( $cases ) );

	foreach ( $cases as $case_id => $case ) {
		$actual_result = get_events( $case['input'] );

		$passed = $case['expected']['count'] === count( $actual_result ) &&
			! empty( $actual_result[0]['url'] ) &&
			strtotime( $actual_result[0]['date'] ) > time() - ( 2 * 24 * 60 * 60 ) &&
			$case['expected']['country'] === strtoupper( $actual_result[0]['location']['country'] );

		output_results( $case_id, $passed, $case['expected'], $actual_result );

		if ( ! $passed ) {
			$failed++;
		}
	}

	return $failed;
}

/**
 * Get the cases for testing `get_location()`
 *
 * @return array
 */
function get_events_test_cases() {
	$cases = array(
		// This assumes there will always be at least 2 upcoming events, so it needs to be a very active community.
		'2-near-seattle' => array(
			'input' => array(
				'number' => '2',
				'nearby' => array(
					'latitude'  => '47.609023',
					'longitude' => '-122.335903',
				),
			),
			'expected' => array(
				'count'   => 2,
				'country' => 'US',
			),
		),

		'1-in-australia' => array(
			'input' => array(
				'number' => '1',
				'country' => 'AU',
			),
			'expected' => array(
				'count'   => 1,
				'country' => 'AU',
			),
		),
	);

	return $cases;
}

/**
 * Test `build_response()`
 *
 * @todo It might be better to do more abstracted tests of `main()`, rather than coupling to the
 *       internals of `build_request()`.
 *
 * @return bool The number of failures
 */
function test_build_response() {
	$failed = 0;
	$cases  = build_response_test_cases();

	printf( "\n\nRunning %d build_response() tests\n", count( $cases ) );

	foreach ( $cases as $case_id => $case ) {
		$actual_result = build_response( $case['input']['location'], $case['input']['location_args'] );

		$passed = $case['expected']['location'] === $actual_result['location'] &&
			isset( $case['expected']['error'] ) === isset( $actual_result['error'] );

		if ( $passed && $case['expected']['events'] ) {
			$passed = ! empty( $actual_result['events'] ) &&
				! empty( $actual_result['events'][0]['url'] ) &&
				strtotime( $actual_result['events'][0]['date'] ) > time() - ( 2 * 24 * 60 * 60 );
		}

		if ( $passed && isset( $case['expected']['error'] ) ) {
			$passed = $case['expected']['error'] === $actual_result['error'];
		}

		output_results( $case_id, $passed, $case['expected'], $actual_result );

		if ( ! $passed ) {
			$failed++;
		}
	}

	return $failed;
}

/**
 * Get the cases for testing `build_response()`
 *
 * @return array
 */
function build_response_test_cases() {
	$cases = array(
		'utrecht-ip' => array(
			'input' => array(
				'location' => array(
					'latitude'  => '52.090284',
					'longitude' => '5.124719',
					'internal'  => true,
				),
				'location_args' => array( 'ip' => '84.31.177.21' ),
			),
			'expected' => array(
				'location' => array(
					'ip' => '84.31.177.21',
				),
				'events' => true,
			),
		),

		'canada-country' => array(
			'input' => array(
				'location' => array(
					'country' => 'CA',
				),
			),
			'expected' => array(
				'location' => array(
					'country' => 'CA',
				),
				'events' => true,
			),
		),

		'throttled' => array(
			'input' => array(
				'location' => 'temp-request-throttled',
			),
			'expected' => array(
				'location' => array(),
				'error'    => 'temp-request-throttled',
				'events'   => false,
			),
		),

		'no-location' => array(
			'input' => array(
				'location' => array(),
			),
			'expected' => array(
				'location' => array(),
				'error'    => 'no_location_available',
				'events'   => false,
			),
		),
	);

	return $cases;
}

/**
 * Test `is_client_core()`.
 *
 * @return int
 */
function test_is_client_core() {
	$failed = 0;
	$cases  = array(
		''                                   => false,
		'Contains WordPress but no slash'    => false,
		'WordPress/4.9; https://example.org' => true,
		'WordPress/10.0'                     => true,
	);

	printf( "\n\nRunning %d is_client_core() tests\n", count( $cases ) );

	foreach ( $cases as $user_agent => $expected_result ) {
		$actual_result = is_client_core( $user_agent );

		$passed = $expected_result === $actual_result;

		output_results( $user_agent, $passed, $expected_result, $actual_result );

		if ( ! $passed ) {
			$failed++;
		}
	}

	return $failed;
}

/**
 * Test `add_regional_events()`
 *
 * @return int
 */
function test_add_regional_wordcamps() {
	$failed = 0;

	$local_events = get_events( array(
		'number' => '5',
		'nearby' => array(
			'latitude'  => '-33.849951',
			'longitude' => '18.426246',
		),
	) );

	// Make sure there's at least one event, otherwise there could be false positives.
	if ( ! $local_events ) {
		$local_events[] = array( 'title' => 'Mock Event' );
	}

	printf( "\n\nRunning %d add_regional_wordcamps() tests\n", 2 );

	// Test that no changes were made if the user agent isn't Core.
	$events_no_user_agent = add_regional_wordcamps( $local_events, '' );

	if ( $events_no_user_agent !== $local_events ) {
		$failed++;
		output_results( 'no-user-agent', false, $local_events, $events_no_user_agent );
	}

	/*
	 * Test that local events were unharmed if the user agent is Core.
	 *
	 * There isn't an easy way to mock time(), so this doesn't test that the events were added
	 * correctly. It just makes sure that local events weren't removed.
	 */
	$events_core_user_agent = add_regional_wordcamps( $local_events, 'WordPress/4.9; https://example.org' );

	if (
		count( $events_core_user_agent ) < count( $local_events ) ||
		! in_array( $local_events[0], $events_core_user_agent, true )
	) {
		$failed++;
		output_results( 'core-user-agent', false, 'local events were not affected', $events_core_user_agent );
	}

	return $failed;
}

/**
 * Stub to simulate cache misses, so that the tests always get fresh results
 *
 * @return false
 */
function wp_cache_get() {
	return false;
}

/**
 * Stub to simulate cache misses, so that the tests always get fresh results
 */
function wp_cache_set() {
	// Intentionally empty
}

run_tests();
