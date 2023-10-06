<?php

namespace Dotorg\API\Events\Tests;
use PHPUnit\Framework\TestCase;
use Requests_Response;

use function Dotorg\API\Events\{
	get_events, get_location, build_response, is_client_core, pin_one_off_events,
	maybe_add_regional_wordcamps, get_iso_3166_2_country_codes, maybe_add_wp15_promo, remove_duplicate_events
};

/**
 * @group events
 */
class Test_Events extends TestCase {
	public static function setUpBeforeClass() : void {
		require_once dirname( __DIR__ ) . '/index.php';
	}

	/**
	 * Asserts that an HTTP response is valid and contains an event.
	 *
	 * @param Requests_Response $response
	 */
	public function assertResponseHasEvent( $response ) {
		$body  = json_decode( $response->body );
		$event = $body->events[0];

		$this->assertSame( 200, $response->status_code );
		$this->assertNull( $body->error );

		$this->assertIsObject( $body->location );
		$this->assertTrue(
			( isset( $body->location->ip ) && is_numeric( $body->location->ip ) ) ||
			( isset( $body->location->latitude ) && is_numeric( $body->location->latitude ) )
		);

		$this->assertContains( $event->type, array( 'wordcamp', 'meetup' ) );
		$this->assertIsString( $event->title );
		$this->assertIsNumeric( $event->start_unix_timestamp );
		$this->assertSame( $event->url, filter_var( $event->url, FILTER_VALIDATE_URL, FILTER_FLAG_PATH_REQUIRED & FILTER_FLAG_QUERY_REQUIRED ) );
		$this->assertIsNumeric( $event->location->latitude );
	}

	/**
	 * @covers ::main
	 *
	 * @group e2e
	 */
	public function test_get_events_e2e() : void {
		$response = send_request( '/events/1.0/?location=seattle&locale=en_US&timezone=America/Los_Angeles' );
		$body     = json_decode( $response->body );

		$this->assertResponseHasEvent( $response );
		$this->assertSame( 'Seattle', $body->location->description );
	}

	/**
	 * @covers ::get_events
	 *
	 * @group unit
	 *
	 * @dataProvider data_get_events
	 */
	function test_get_events( array $input, array $expected ) : void {
		$actual_result = get_events( $input );

		$this->assertSame( $expected['count'], count( $actual_result ) );
		$this->assertNotEmpty( $actual_result[0]['url'] );
		$this->assertGreaterThan( time() - ( 2 * 24 * 60 * 60 ), strtotime( $actual_result[0]['date'] ) );
		$this->assertSame( $expected['country'], strtoupper( $actual_result[0]['location']['country'] ) );
	}

	function data_get_events() : array {
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
					'number'  => '1',
					'country' => 'AU',
					'restrict_by_country' => true,
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
	 * @covers ::get_events
	 *
	 * @group unit
	 *
	 * @dataProvider data_get_events_country_restriction
	 */
	function test_get_events_country_restriction( array $input, array $expected_countries ) : void {
		$actual_result    = get_events( $input );
		$actual_countries = array_column( array_column( $actual_result, 'location' ), 'country' );
		$actual_countries = array_unique( array_map( 'strtoupper', $actual_countries ) );

		sort( $actual_countries );

		$this->assertSame( $expected_countries, $actual_countries );
	}

	function data_get_events_country_restriction() : array {
		return array(
			'restricted-by-country' => array(
				'input' => array(
					'number'              => '500',
					'country'             => 'CA',
					'restrict_by_country' => true,
				),
				'expected_countries' => array( 'CA' ),
			),

			/*
			 * This assumes there will always be at least 1 upcoming event on both sides of the border, so the
			 * coordinates need to be half-way between two very active groups in different countries, where the
			 * mid-point is less than `$event_distances['meetup']`.
			 *
			 * See https://wordpress.slack.com/archives/C08M59V3P/p1524168308000202.
			 */
			'not-restricted-by-country' => array(
				'input' => array(
					'number'              => '500',
					'restrict_by_country' => false,

					// If one of these stops working, comment it out and try another.
					'nearby' => array(
						// Toronto CA and Buffalo US
						'latitude'  => '43.254372',
						'longitude' => '-79.063746',

						// Belfast GB and Dublin IE
						//'latitude'  => '53.997654',
						//'longitude' => '-6.403377',

						// Seattle US and Victoria CA
						//'latitude'  => '47.986952',
						//'longitude' => '-122.961350',
					),
				),
				'expected_countries' => array(
					'CA', 'US'
					//'GB', 'IE'
				),
			),
		);
	}

	/**
	 * @covers ::maybe_add_regional_wordcamps
	 * @covers ::get_iso_3166_2_country_codes
	 *
	 * @group unit
	 */
	public function test_maybe_add_regional_wordcamps() : void {
		$local_events = get_events( array(
			'number' => '5',
			'nearby' => array(
				// Off the coast of Robben Island, South Africa.
				'latitude'  => '-33.849951',
				'longitude' => '18.426246',
			),
		) );

		$region_data = array(
			'us' => array(
				'regional_countries' => array_merge(
					get_iso_3166_2_country_codes( 'south america' ),
					get_iso_3166_2_country_codes( 'north america' )
				),

				'event' => array(
					'type'       => 'wordcamp',
					'title'      => 'WordCamp US',
					'url'        => 'https://2019.us.wordcamp.org/',
					'meetup'     => '',
					'meetup_url' => '',
					'date'       => '2019-11-01 00:00:00',
					'location'   => array(
						'location'  => 'St. Louis, MO, USA',
						'country'   => 'US',
						'latitude'  => 38.6532135,
						'longitude' => -90.3136733,
					),
				),
			),
		);

		$core_user_agent  = 'WordPress/5.2; https://example.org';
		$other_user_agent = 'Smith';

		$promo_start               = strtotime( $region_data['us']['event']['date'] . ' - 2 months' );
		$time_before_promo         = strtotime( '2019-08-15 00:00:00' );
		$time_during_promo_phase_1 = strtotime( '+ 1 day', $promo_start );
		$time_during_promo_phase_2 = strtotime( '+ 2 weeks + 1 day', $promo_start );
		$time_during_promo_phase_3 = strtotime( '+ 4 weeks + 1 day', $promo_start );
		$time_after_promo          = strtotime( '+ 6 weeks + 1 day', $promo_start );

		$location_country_within_region = array(
			'country' => 'us',
		);

		$location_country_outside_region = array(
			'country' => 'es',
		);

		$location_ip_only = array(
			'ip' => '8.8.8.8',
		);

		// Make sure there's at least one event, otherwise there could be false positives.
		if ( ! $local_events ) {
			$local_events[] = array( 'title' => 'Mock Event' );
		}

		$tests_expect_no_changes = array();
		$tests_expect_changes    = array();

		// No regional camps should be added if before the promo start date or after the promo window is past (6 weeks).
		$tests_expect_no_changes['before-promo'] = maybe_add_regional_wordcamps( $local_events, $region_data, $core_user_agent, $time_before_promo, $location_country_within_region );
		$tests_expect_no_changes['before-promo'] = maybe_add_regional_wordcamps( $local_events, $region_data, $core_user_agent, $time_after_promo, $location_country_within_region );

		// Regional camp should be added if it's within phase 1 of the promo, regardless of location.
		$tests_expect_changes['promo-phase-1-within-region'] = maybe_add_regional_wordcamps( $local_events, $region_data, $core_user_agent, $time_during_promo_phase_1, $location_country_within_region );
		$tests_expect_changes['promo-phase-1-outside-region'] = maybe_add_regional_wordcamps( $local_events, $region_data, $core_user_agent, $time_during_promo_phase_1, $location_country_outside_region );

		// Regional camp should only be added during phase 2 of promo if location is within region.
		$tests_expect_changes['promo-phase-2-within-region'] = maybe_add_regional_wordcamps( $local_events, $region_data, $core_user_agent, $time_during_promo_phase_2, $location_country_within_region );
		$tests_expect_no_changes['promo-phase-2-outside-region'] = maybe_add_regional_wordcamps( $local_events, $region_data, $core_user_agent, $time_during_promo_phase_2, $location_country_outside_region );
		$tests_expect_no_changes['promo-phase-2-ip-only'] = maybe_add_regional_wordcamps( $local_events, $region_data, $core_user_agent, $time_during_promo_phase_2, $location_ip_only );

		// Regional camp should only be added during phase 3 of promo if location is within event country.
		$tests_expect_changes['promo-phase-3-within-event-country'] = maybe_add_regional_wordcamps( $local_events, $region_data, $core_user_agent, $time_during_promo_phase_3, $location_country_within_region );
		$tests_expect_no_changes['promo-phase-3-outside-event-country'] = maybe_add_regional_wordcamps( $local_events, $region_data, $core_user_agent, $time_during_promo_phase_3, $location_country_outside_region );
		$tests_expect_no_changes['promo-phase-3-ip-only'] = maybe_add_regional_wordcamps( $local_events, $region_data, $core_user_agent, $time_during_promo_phase_3, $location_ip_only );

		// Regional camp should only be added if the user agent is Core.
		$tests_expect_no_changes['other-user-agent'] = maybe_add_regional_wordcamps( $local_events, $region_data, $other_user_agent, $time_during_promo_phase_1, $location_country_within_region );
		$tests_expect_changes['core-user-agent'] = maybe_add_regional_wordcamps( $local_events, $region_data, $core_user_agent, $time_during_promo_phase_1, $location_country_within_region );

		foreach ( $tests_expect_no_changes as $name => $result ) {
			$this->assertSame( $local_events, $result );
		}

		$unchanged_count = count( $local_events );
		$expected_count  = $unchanged_count + 1;

		foreach ( $tests_expect_changes as $name => $result ) {
			$actual_count = count( $result );

			$this->assertSame( $expected_count, $actual_count );
		}
	}

	/**
	 * @covers ::get_iso_3166_2_country_codes
	 *
	 * @dataProvider data_get_iso_3166_2_country_codes
	 *
	 * @group unit
	 */
	public function test_get_iso_3166_2_country_codes( $continent, $sample_country ) : void {
		$countries = get_iso_3166_2_country_codes( $continent );

		$this->assertContains( $sample_country, $countries );
	}

	public function data_get_iso_3166_2_country_codes() : array {
		return array(
			array( 'antarctica',    'HM' ),
			array( 'africa',        'KM' ),
			array( 'asia',          'SA' ),
			array( 'europe',        'IM' ),
			array( 'north america', 'MQ' ),
			array( 'oceania',       'MP' ),
			array( 'south america', 'GY' ),
		);
	}

	/**
	 * @covers ::maybe_add_wp15_promo
	 *
	 * @group unit
	 */
	public function test_maybe_add_wp15_promo() : void {
		$local_events_yes_wp15 = array(
			array(
				'type'       => 'meetup',
				'title'      => 'WordPress 15th Anniversary Celebration',
				'url'        => 'https://www.meetup.com/pdx-wp/events/250109566/',
				'meetup'     => 'Portland WordPress Meetup',
				'meetup_url' => 'https://www.meetup.com/pdx-wp/',
				'date'       => '2018-05-27 12:00:00',
				'location'   => array(
					'location'  => 'Portland, OR, USA',
					'country'   => 'us',
					'latitude'  => 45.540115,
					'longitude' => - 122.630699,
				),
			),

			array(
				'type'       => 'wordcamp',
				'title'      => 'WordCamp Portland, Oregon, USA',
				'url'        => 'https://2018.portland.wordcamp.org',
				'meetup'     => '',
				'meetup_url' => '',
				'date'       => '2018-11-03 00:00:00',
				'location'   => array(
					'location'  => 'Portland, OR, USA',
					'country'   => 'us',
					'latitude'  => 45.540115,
					'longitude' => - 122.630699,
				),
			),
		);

		$local_events_no_wp15 = array(
			array(
				'type'       => 'meetup',
				'title'      => 'Kickoff: Meet and greet, roundtable discussion',
				'url'        => 'https://www.meetup.com/Corvallis-WordPress-Meetup/events/250327006/',
				'meetup'     => 'Corvallis WordPress Meetup',
				'meetup_url' => 'https://www.meetup.com/Corvallis-WordPress-Meetup/',
				'date'       => '2018-05-22 18:30:00',
				'location'   => array(
					'location'  => 'Corvallis, OR, USA',
					'country'   => 'us',
					'latitude'  => 44.563564,
					'longitude' => - 123.26095,
				),
			),

			array(
				'type'       => 'wordcamp',
				'title'      => 'WordCamp Portland, Oregon, USA',
				'url'        => 'https://2018.portland.wordcamp.org',
				'meetup'     => '',
				'meetup_url' => '',
				'date'       => '2018-11-03 00:00:00',
				'location'   => array(
					'location'  => 'Portland, OR, USA',
					'country'   => 'us',
					'latitude'  => 45.540115,
					'longitude' => - 122.630699,
				),
			),
		);

		$local_events_added_wp15 = array(
			array(
				'type'       => 'meetup',
				'title'      => 'WP15',
				'url'        => 'https://wordpress.org/news/2018/04/celebrate-the-wordpress-15th-anniversary-on-may-27/',
				'meetup'     => '',
				'meetup_url' => '',
				'date'       => '2018-05-27 12:00:00',
				'location'   => array(
					'location' => 'Everywhere',
				),
			),

			array(
				'type'       => 'meetup',
				'title'      => 'Kickoff: Meet and greet, roundtable discussion',
				'url'        => 'https://www.meetup.com/Corvallis-WordPress-Meetup/events/250327006/',
				'meetup'     => 'Corvallis WordPress Meetup',
				'meetup_url' => 'https://www.meetup.com/Corvallis-WordPress-Meetup/',
				'date'       => '2018-05-22 18:30:00',
				'location'   => array(
					'location'  => 'Corvallis, OR, USA',
					'country'   => 'us',
					'latitude'  => 44.563564,
					'longitude' => - 123.26095,
				),
			),

			array(
				'type'       => 'wordcamp',
				'title'      => 'WordCamp Portland, Oregon, USA',
				'url'        => 'https://2018.portland.wordcamp.org',
				'meetup'     => '',
				'meetup_url' => '',
				'date'       => '2018-11-03 00:00:00',
				'location'   => array(
					'location'  => 'Portland, OR, USA',
					'country'   => 'us',
					'latitude'  => 45.540115,
					'longitude' => - 122.630699,
				),
			),
		);

		$user_agent = 'WordPress/4.9; https://example.org';

		$time_before_date_range = 1523295832;
		$time_during_date_range = 1525887832;

		// Test that the promo is added if there is not already a WP15 event.
		$events_promo_added = maybe_add_wp15_promo( $local_events_no_wp15, $user_agent, $time_during_date_range );
		$this->assertSame( $local_events_added_wp15, $events_promo_added, 'needs-promo' );

		// Test that no promo is added if there is already a WP15 event.
		$events_already_has_one = maybe_add_wp15_promo( $local_events_yes_wp15, $user_agent, $time_during_date_range );

		$this->assertSame( $local_events_yes_wp15, $events_already_has_one, 'already-has-event' );

		// Test that no changes are made if the user agent isn't Core.
		$events_no_user_agent = maybe_add_wp15_promo( $local_events_no_wp15, '', $time_during_date_range );
		$this->assertSame( $local_events_no_wp15, $events_no_user_agent, 'no-user-agent' );

		// Test that no promo is added if the time is outside the date range.
		$events_outside_date_range = maybe_add_wp15_promo( $local_events_no_wp15, $user_agent, $time_before_date_range );
		$this->assertSame( $local_events_no_wp15, $events_outside_date_range, 'outside-date-range' );
	}

	/**
	 * @covers ::remove_duplicate_events
	 *
	 * @group unit
	 */
	public function test_remove_duplicate_events() : void {
		$duplicate_events = array(
			// Each of these represents an event; extraneous fields have been removed for readability.
			array(
				'url' => 'https://2020.us.wordcamp.org/',
			),

			array(
				'url' => 'https://2020.detroit.wordcamp.org/',
			),

			array(
				// Intentionally missing the trailing slash, to account for inconsistencies in data.
				'url' => 'https://2020.us.wordcamp.org',
			),
		);

		$unique_events = array(
			array(
				'url' => 'https://2020.us.wordcamp.org',
			),

			array(
				'url' => 'https://2020.detroit.wordcamp.org/',
			),
		);

		$this->assertSame( $unique_events, remove_duplicate_events( $duplicate_events ) );
	}

	/**
	 * @covers ::get_location
	 *
	 * @group unit
	 *
	 * @dataProvider data_get_location
	 */
	public function test_get_location( array $input, $expected ) : void {
		$actual_result = get_location( $input );

		// Normalize to lowercase to account for inconsistency in the IP database
		if ( isset( $actual_result['description'] ) && is_string( $actual_result['description'] ) ) {
			$actual_result['description'] = strtolower( $actual_result['description'] );
		}

		/*
		 * Normalize coordinates to account for minor differences in the databases.
		 *
		 * Rounding to three decimal places means that we're still accurate within about 110 meters, which is
		 * good enough for our purposes.
		 *
		 * @link https://gis.stackexchange.com/a/8674/49125.
		 */
		if ( isset( $actual_result['latitude'], $actual_result['longitude'] ) ) {
			$actual_result['latitude']  = number_format( round( $actual_result['latitude'],  3 ), 3 );
			$actual_result['longitude'] = number_format( round( $actual_result['longitude'], 3 ), 3 );
		}

		$this->assertSame( $expected, $actual_result );
	}

	public function data_get_location() : array {
		$cases = array(
			/*
			 * Only the country code is given
			 */
			'country-code-australia' => array(
				'input' => array(
					'country' => 'AU',
					'restrict_by_country' => true,
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
					'country'     => 'ID',
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
					'location_name' => 'InvalidCity',
					'ip'            => '127.0.0.1',
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
					'location_name' => 'Doña Ana',
					'locale'        => 'en_US',
					'timezone'      => 'America/Denver',
				),
				'expected' => array(
					'description' => 'doña ana',
					'latitude'    => '32.390',
					'longitude'   => '-106.814',
					'country'     => 'US',
				),
			),

			'city-with-diacritics-in-formal-name-but-not-in-query' => array(
				'input' => array(
					'location_name' => 'Dona Ana',
					'locale'        => 'en_US',
					'timezone'      => 'America/Denver',
				),
				'expected' => array(
					'description' => 'dona ana',
					'latitude'    => '32.390',
					'longitude'   => '-106.814',
					'country'     => 'US',
				),
			),

			'city-with-period-in-query' => array(
				'input' => array(
					'location_name' => 'St. Louis',
					'locale'        => 'en_US',
					'timezone'      => 'America/Chicago',
				),
				'expected' => array(
					'description' => 'st. louis',
					'latitude'    => '38.627',
					'longitude'   => '-90.198',
					'country'     => 'US',
				),
			),

			'city-with-period-in-formal-name-but-not-in-query' => array(
				'input' => array(
					'location_name' => 'St Louis',
					'locale'        => 'en_US',
					'timezone'      => 'America/Chicago',
				),
				'expected' => array(
					'description' => 'st louis',
					'latitude'    => '38.627',
					'longitude'   => '-90.198',
					'country'     => 'US',
				),
			),

			/*
			 * The city endonym, locale, and timezone are given
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
					'latitude'    => '24.861',
					'longitude'   => '67.010',
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

			'city-endonym-ideographic-municipal-unit-asia' => array(
				'input' => array(
					'location_name' => '大阪',
					'locale'        => 'ja',
					'timezone'      => 'Asia/Tokyo',
				),
				'expected' => array(
					'description' => '大阪',
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
					'latitude'    => '59.939',
					'longitude'   => '30.314',
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
					'latitude'    => '59.939',
					'longitude'   => '30.314',
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
					'latitude'    => '19.283',
					'longitude'   => '-99.133',
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

			// https://meta.trac.wordpress.org/ticket/3367
			// The following tests ensure that the West/East Portlands return correctly with inversed timezones.
			'usa-city-disambiguation' => array(
				'input' => array(
					'location_name' => 'Portland',
					'locale'        => 'en_US',
					'timezone'      => 'America/Los_Angeles',
				),
				'expected' => array(
					'description' => 'portland',
					'latitude'    => '45.523',
					'longitude'   => '-122.676',
					'country'     => 'US',
				),
			),
			'usa-city-disambiguation-2' => array(
				'input' => array(
					'location_name' => 'Portland, OR',
					'locale'        => 'en_US',
					'timezone'      => 'America/New_York',
				),
				'expected' => array(
					'description' => 'portland, or',
					'latitude'    => '45.523',
					'longitude'   => '-122.676',
					'country'     => 'US',
				),
			),
			'usa-city-disambiguation-3' => array(
				'input' => array(
					'location_name' => 'Portland',
					'locale'        => 'en_US',
					'timezone'      => 'America/New_York',
				),
				'expected' => array(
					'description' => 'portland',
					'latitude'    => '43.657',
					'longitude'   => '-70.259',
					'country'     => 'US',
				),
			),
			'usa-city-disambiguation-4' => array(
				'input' => array(
					'location_name' => 'Portland, ME',
					'locale'        => 'en_US',
					'timezone'      => 'America/Los_Angeles',
				),
				'expected' => array(
					'description' => 'portland, me',
					'latitude'    => '43.657',
					'longitude'   => '-70.259',
					'country'     => 'US',
				),
			),

			'usa-city-disambiguation-by-preferred-name' => array(
				'input' => array(
					'location_name' => 'Chicago',
					'locale'        => 'en_US',
					'timezone'      => 'America/Chicago',
				),
				'expected' => array(
					'description' => 'chicago',
					'latitude'    => '41.850',
					'longitude'   => '-87.650',
					'country'     => 'US',
				),
			),
			'usa-city-disambiguation-by-preferred-name-2' => array(
				'input' => array(
					'location_name' => 'Chicago',
					'locale'        => 'en_US',
					'timezone'      => 'America/Los_Angeles',
				),
				'expected' => array(
					'description' => 'chicago',
					'latitude'    => '41.850',
					'longitude'   => '-87.650',
					'country'     => 'US',
				),
			),

			// Calcutta, Ohio, USA is a preferred name, but the country is more relevant here.
			'city-disambiguation-by-country' => array(
				'input' => array(
					'location_name' => 'Calcutta',
					'locale'        => 'hi_IN',
					'timezone'      => 'Asia/Kolkata',
				),
				'expected' => array(
					'description' => 'calcutta',
					'latitude'    => '22.563',
					'longitude'   => '88.363',
					'country'     => 'IN',
				),
			),

			// Genova, IT is the native name of a populated city, and it's English name is Genoa. There's also a
			// small village in IT whose English name is Genova. Geonames considers the English name to be the
			// primary one, and native names are considered alternate names. We rank primary names higher than
			// alternate names, and that usually works, but in this case people are more likely to be looking for
			// the city.
			'rural-match-less-likely-than-city-match' => array(
				'input' => array(
					'location_name' => 'Genova',
					'locale'        => 'it_IT',
					'timezone'      => 'Europe/Rome',
				),
				'expected' => array(
					'description' => 'genova',
					'latitude'    => '44.413',
					'longitude'   => '8.957',
					'country'     => 'IT',
				),
			),

			'rural-match-less-likely-than-city-match-with-accent' => array(
				'input' => array(
					'location_name' => 'Gènova',
					'locale'        => 'it_IT',
					'timezone'      => 'Europe/Rome',
				),
				'expected' => array(
					'description' => 'genova',
					'latitude'    => '44.413',
					'longitude'   => '8.957',
					'country'     => 'IT',
				),
			),

			'city-match-more-likely-than-rural-match' => array(
				'input' => array(
					'location_name' => 'Genoa',
					'locale'        => 'it_IT',
					'timezone'      => 'Europe/Rome',
				),
				'expected' => array(
					'description' => 'genoa',
					'latitude'    => '44.405',
					'longitude'   => '8.944',
					'country'     => 'IT',
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
					'latitude'    => '43.657',
					'longitude'   => '-70.259',
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
					'country'     => 'CA',
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
					'country'     => 'DO',
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
					'country'     => 'CF',
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
					'country'     => 'GB',
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
					'country'     => 'BI',
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
			 * Only the IPv4 address is given.
			 *
			 * Note that IP locations change frequently, so some of these expected results will inevitably become outdated
			 * and cause tests to fail.
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
					'description' => 'belfast',
					'latitude'    => '54.583',
					'longitude'   => '-5.933',
					'country'     => 'GB',
					'internal'    => true,
				),
			),

			'ip-north-america' => array(
				'input' => array( 'ip' => '189.147.186.0' ),
				'expected' => array(
					'description' => 'mexico city',
					'latitude'    => '19.428',
					'longitude'   => '-99.128',
					'country'     => 'MX',
					'internal'    => true,
				),
			),

			'ip-seattle' => array(
				'input' => array( 'ip' => '97.113.10.69' ),
				'expected' => array(
					'description' => 'tukwila',
					'latitude'    => '47.474',
					'longitude'   => '-122.261',
					'country'     => 'US',
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
			 * Only an IPv6 address is given.
			 *
			 * Note that IP locations change frequently, so some of these expected results will inevitably become outdated
			 * and cause tests to fail.
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
					'latitude'    => '19.076',
					'longitude'   => '72.877',
					'country'     => 'IN',
					'internal'    => true,
				),
			),

			'ipv6-europe-anonymized' => array(
				'input'    => array( 'ip' => '2a02:578:1000::' ),
				'expected' => array(
					'description' => 'brussels',
					'latitude'    => '50.851',
					'longitude'   => '4.349',
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
					'description' => 'lima',
					'latitude'    => '-12.043',
					'longitude'   => '-77.028',
					'country'     => 'PE',
					'internal'    => true,
				),
			),
		);

		return $cases;
	}

	/**
	 * @covers ::build_response
	 *
	 * @todo It might be better to do more abstracted tests of `main()`, or e2e tests, rather than coupling to the
	 * internals of `build_request()`.
	 *
	 * @group unit
	 *
	 * @dataProvider data_build_response
	 */
	function test_build_response( array $input, array $expected ) : void {
		$actual_result = build_response( $input['location'], $input['location_args'] );

		$this->assertSame( $expected['location'], $actual_result['location'] );
		$this->assertSame( isset( $expected['error'] ), isset( $actual_result['error'] ) );

		if ( $expected['events'] ) {
			$this->assertNotEmpty( $actual_result['events'] );
			$this->assertNotEmpty( $actual_result['events'][0]['url'] );
			$this->assertGreaterThan( time() - ( 2 * 24 * 60 * 60 ), strtotime( $actual_result['events'][0]['date'] ) );
		}

		if ( isset( $expected['error'] ) ) {
			$this->assertSame( $expected['error'], $actual_result['error'] );
		}
	}

	function data_build_response() : array {
		return array(
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
					'location_args' => array(
						'restrict_by_country' => true,
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
	}

	/**
	 * @covers ::pin_one_off_events
	 *
	 * @group unit
	 */
	function test_pin_one_off_events() {
		$seed_events = array();

		// Don't forget to update the values here when they're updated in the FUT.
		$actual_events_before_start      = pin_one_off_events( $seed_events, strtotime( 'December 12, 2022' ) );
		$actual_events_before_expiration = pin_one_off_events( $seed_events, strtotime( 'December 17, 2022' ) );
		$actual_events_after_expiration  = pin_one_off_events( $seed_events, strtotime( 'December 19, 2022' ) );

		$this->assertEmpty( $actual_events_before_start );
		$this->assertIsArray( $actual_events_after_expiration );
		$this->assertEmpty( $actual_events_after_expiration );

		$this->assertIsArray( $actual_events_before_expiration );
		$this->assertSame( 'State of the Word', $actual_events_before_expiration[0]['title'] );
	}

	/**
	 * @covers ::is_client_core
	 *
	 * @group unit
	 *
	 * @dataProvider data_is_client_core
	 */
	function test_is_client_core( string $user_agent, bool $expected_result ) : void {
		$actual_result = is_client_core( $user_agent );

		$this->assertSame( $expected_result, $actual_result );
	}

	public function data_is_client_core() : array {
		return array(
			'Empty string'                    => array( '', false ),
			'Mentions WP but not Core format' => array( 'Contains WordPress but no slash', false ),
			'Core old version'                => array( 'WordPress/4.9; https://example.org', true ),
			'Core future version, no URL'     => array( 'WordPress/10.0', true ),
		);
	}
}
