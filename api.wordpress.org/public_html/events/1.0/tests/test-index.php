<?php

namespace Dotorg\API\Events;

if ( 'cli' !== php_sapi_name() ) {
	die();
}

/**
 * Main entry point
 */
function run_tests() {
	define( 'RUNNING_TESTS', true );
	require_once( dirname( __DIR__ ) . '/index.php' );

	$failed = 0;
	$failed += test_get_location();

	printf( "\n\nFinished running all tests. %d failed.\n", $failed );
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
		 * Only the country is given
		 */
		'country-australia' => array(
			'input' => array(
				'country' => 'AU',
			),
			'expected' => array(
				'country' => 'AU'
			),
		),


		/*
		 * A location couldn't be found
		 */
		'city-invalid' => array(
			'input' => array(
				'location_name' => 'Rivendell',
				'ip_address'    => '127.0.0.1'
			),
			'expected' => false,
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

		'city-south-america' => array(
			'input' => array(
				'location_name' => 'Sao Paulo',
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


		/*
		 * The city endonym, locale, and timezone are given
		 *
		 * @todo
		 * This is currently failling. A query from PHP shows row id 2220957 has "Yaound?" instead of
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
				'description' => 'addis ababa',
				'latitude'    => '9.025',
				'longitude'   => '38.747',
				'country'     => 'ET',
			),
		),

		'city-endonym-non-latin-length-greater-than-4-asia' => array(
			'input' => array(
				'location_name' => '白浜町宇佐崎南',
				'locale'        => 'ja',
				'timezone'      => 'Asia/Tokyo',
			),
			'expected' => array(
				'description' => 'shirahamachō-usazakiminami',
				'latitude'    => '34.783',
				'longitude'   => '134.717',
				'country'     => 'JP',
			),
		),

		/*
		 * @todo
		 *
		 * This is currently failing, but should pass. It looks like the value is in row 112931.
		 */
		'city-endonym-non-latin-length-greater-than-4-asia2' => array(
			'input' => array(
				'location_name' => 'تهران',
				'locale'        => 'fa_IR',
				'timezone'      => 'Asia/Tehran',
			),
			'expected' => array(
				'description' => 'tehran',
				'latitude'    => '35.694',
				'longitude'   => '51.422',
				'country'     => 'IR',
			),
		),

		/*
		 * @todo
		 *
		 * This is currently failing, but should pass. It looks like the value is in row 1174872
		 */
		'city-endonym-non-latin-length-greater-than-4-asia3' => array(
			'input' => array(
				'location_name' => 'كراچى',
				'locale'        => 'ur',
				'timezone'      => 'Asia/Karachi',
			),
			'expected' => array(
				'description' => 'karachi',
				'latitude'    => '24.906',
				'longitude'   => '67.082',
				'country'     => 'TR',
			),
		),

		/*
		 * @todo
		 *
		 * This is currently failing, but it should pass. One reason it may be failing is that `ft_min_word_len`
		 * is set to `4` and `東京` is only `2`.
		 *
		 * But, there are others that are failing that are >= 4, though, like `シラオカ`, `しらおか`, `Ширахама`, and
		 *`すぎと,スギト`. So, there may be additional reasons too.
		 */
		'city-endonym-non-latin-length-less-than-4-asia' => array(
			'input' => array(
				'location_name' => '東京',
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

		'city-endonym-europe' => array(
			'input' => array(
				'location_name' => 'Wien',
				'locale'        => 'de_DE',
				'timezone'      => 'Europe/Berlin',
			),
			'expected' => array(
				'description' => 'vienna',
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
				'description' => 'moscow',
				'latitude'    => '55.752',
				'longitude'   => '37.616',
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
				'description' => 'mexico city',
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
		 * Only the IP is given
		 */
		'ip-africa' => array(
			'input' => array( 'ip' => '41.191.232.22' ),
			'expected' => array(
				'description' => 'harare',
				'latitude'    => '-17.829',
				'longitude'   => '31.054',
				'country'     => 'ZW',
			),
		),

		'ip-asia' => array(
			'input' => array( 'ip' => '86.108.55.28' ),
			'expected' => array(
				'description' => 'amman',
				'latitude'    => '31.955',
				'longitude'   => '35.945',
				'country'     => 'JO',
			),
		),

		'ip-europe' => array(
			'input' => array( 'ip' => '80.95.186.144' ),
			'expected' => array(
				'description' => 'belfast',
				'latitude'    => '54.583',
				'longitude'   => '-5.933',
				'country'     => 'GB',
			),
		),

		'ip-north-america' => array(
			'input' => array( 'ip' => '189.147.186.0' ),
			'expected' => array(
				'description' => 'mexico city',
				'latitude'    => '19.428',
				'longitude'   => '-99.128',
				'country'     => 'MX',
			),
		),

		'ip-oceania' => array(
			'input' => array( 'ip' => '116.12.57.122' ),
			'expected' => array(
				'description' => 'auckland',
				'latitude'    => '-36.867',
				'longitude'   => '174.767',
				'country'     => 'NZ',
			),
		),

		'ip-south-america' => array(
			'input' => array( 'ip' => '181.66.32.136' ),
			'expected' => array(
				'description' => 'lima',
				'latitude'    => '-12.043',
				'longitude'   => '-77.028',
				'country'     => 'PE',
			),
		),
	);

	 return $cases;
}

run_tests();
