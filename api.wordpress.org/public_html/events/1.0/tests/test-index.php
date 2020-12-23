<?php

/*
 * @todo Move these into proper PHPUnit tests in `tests/test-index.php` as have time.
 *
 * When that's done, delete this file and rename that one to `test-index.php`.
 * Also delete the `test_port_remaining_tests()` test in the PHPUnit class.
 */

namespace Dotorg\API\Events;

if ( 'cli' !== php_sapi_name() ) {
	die();
}

/*
 * Don't run these tests from PHPUnit.
 */
if ( defined( 'WPORG_RUNNING_TESTS' ) ) {
	return;
}

/**
 * Main entry point
 */
function run_tests() {
	global $wpdb;

	define( 'RUNNING_TESTS', true );
	define( 'SAVEQUERIES',   true );

	require_once dirname( __DIR__ ) . '/index.php';

	$tests_failed  = 0;
	$tests_failed += test_maybe_add_regional_wordcamps();
	$tests_failed += test_maybe_add_wp15_promo();
	$tests_failed += test_get_iso_3166_2_country_codes();
	$tests_failed += test_remove_duplicate_events();

	$query_count = count( $wpdb->queries );
	$query_time  = array_sum( array_column( $wpdb->queries, 1 ) );

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

	echo "\n\n* $case_id: _FAILED_";

	if ( ! $passed ) {
		$expected_output = is_scalar( $expected_result ) ? var_export( $expected_result, true ) : print_r( $expected_result, true );
		$actual_output   = is_scalar( $actual_result   ) ? var_export( $actual_result,   true ) : print_r( $actual_result,   true );

		if ( VERBOSE_OUTPUT ) {
			printf(
				"\n\nExpected result: %s\nActual result: %s",
				$expected_output,
				$actual_output
			);

		} else {
			$folder = sys_get_temp_dir();
			file_put_contents( $folder . '/events-test-expected.txt', $expected_output);
			file_put_contents( $folder . '/events-test-actual.txt',   $actual_output );

			$diff_results = shell_exec( sprintf(
				'/usr/bin/diff --unified %s --label "Expected" %s --label "Actual"',
				$folder . '/events-test-expected.txt',
				$folder . '/events-test-actual.txt'
			) );

			if ( empty( $diff_results ) ) {
				$diff_results = "Error: diff appears to be empty even though test failed.\n";
			}

			$short_output = sprintf( "\n\n%s
				Rerun with `--verbose` for detailed failure results.\n",
				$diff_results
			);

			printf( str_replace( "\t", '', $short_output ) );
		}
	}
}

/**
 * Test `add_regional_events()`
 *
 * @return int
 */
function test_maybe_add_regional_wordcamps() {
	$failed = 0;

	$local_events = get_events( array(
		'number' => '5',
		'nearby' => array(
			'latitude'  => '-33.849951',
			'longitude' => '18.426246',
		),
	) );

	$region_data = array(
		'us' => array(
			'promo_start' => strtotime( '2019-08-16 00:00:00' ),

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

	$time_before_promo         = strtotime( '2019-08-15 00:00:00' );
	$time_during_promo_phase_1 = strtotime( '+ 1 day', $region_data['us']['promo_start'] );
	$time_during_promo_phase_2 = strtotime( '+ 2 weeks + 1 day', $region_data['us']['promo_start'] );
	$time_during_promo_phase_3 = strtotime( '+ 4 weeks + 1 day', $region_data['us']['promo_start'] );
	$time_after_promo          = strtotime( '+ 6 weeks + 1 day', $region_data['us']['promo_start'] );

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

	printf( "\nRunning %d add_regional_wordcamps() tests", 13 );

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
		if ( $result !== $local_events ) {
			$failed++;
			output_results( $name, false, $local_events, $result );
		}
	}

	$unchanged_count = count( $local_events );
	$expected_count  = $unchanged_count + 1;

	foreach ( $tests_expect_changes as $name => $result ) {
		$actual_count = count( $result );

		if ( $actual_count !== $expected_count ) {
			$failed++;
			output_results( $name, false, $expected_count, $actual_count );
		}
	}

	return $failed;
}

/**
 * Test `maybe_add_wp15_promo()`
 *
 * @return int
 */
function test_maybe_add_wp15_promo() {
	$failed = 0;

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

	printf( "\nRunning %d maybe_add_wp15_promo() tests", 4 );

	// Test that the promo is added if there is not already a WP15 event.
	$events_promo_added = maybe_add_wp15_promo( $local_events_no_wp15, $user_agent, $time_during_date_range );

	if ( $events_promo_added !== $local_events_added_wp15 ) {
		$failed++;
		output_results( 'needs-promo', false, $local_events_added_wp15, $events_promo_added );
	}

	// Test that no promo is added if there is already a WP15 event.
	$events_already_has_one = maybe_add_wp15_promo( $local_events_yes_wp15, $user_agent, $time_during_date_range );

	if ( $events_already_has_one !== $local_events_yes_wp15 ) {
		$failed++;
		output_results( 'already-has-event', false, $local_events_yes_wp15, $events_already_has_one );
	}

	// Test that no changes are made if the user agent isn't Core.
	$events_no_user_agent = maybe_add_wp15_promo( $local_events_no_wp15, '', $time_during_date_range );

	if ( $events_no_user_agent !== $local_events_no_wp15 ) {
		$failed++;
		output_results( 'no-user-agent', false, $local_events_no_wp15, $events_no_user_agent );
	}

	// Test that no promo is added if the time is outside the date range.
	$events_outside_date_range = maybe_add_wp15_promo( $local_events_no_wp15, $user_agent, $time_before_date_range );

	if ( $events_outside_date_range !== $local_events_no_wp15 ) {
		$failed++;
		output_results( 'outside-date-range', false, $local_events_no_wp15, $events_outside_date_range );
	}

	return $failed;
}

/**
 * Test `get_iso_3166_2_country_codes()`.
 */
function test_get_iso_3166_2_country_codes() {
	$failed = 0;
	$cases = array(
		'antarctica'    => 'HM',
		'africa'        => 'KM',
		'asia'          => 'SA',
		'europe'        => 'IM',
		'north america' => 'MQ',
		'oceania'       => 'MP',
		'south america' => 'GY',
	);

	printf( "\nRunning %d get_iso_3166_2_country_codes() tests", count( $cases ) );

	foreach ( $cases as $continent => $sample_country ) {
		$countries = get_iso_3166_2_country_codes( $continent );

		$expected_result = true;
		$actual_result   = in_array( $sample_country, $countries, true );
		$passed          = $expected_result === $actual_result;

		output_results( $continent, $passed, $expected_result, $actual_result );

		if ( ! $passed ) {
			$failed++;
		}
	}

	return $failed;
}

/**
 * Test `remove_duplicate_events()`.
 */
function test_remove_duplicate_events() {
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

	printf( "\nRunning 1 remove_duplicate_events() test" );

	$expected_result = array(
		array(
			'url' => 'https://2020.us.wordcamp.org',
		),

		array(
			'url' => 'https://2020.detroit.wordcamp.org/',
		),
	);

	$actual_result = remove_duplicate_events( $duplicate_events );
	$passed        = $expected_result === $actual_result;

	output_results( 'remove duplicate events', $passed, $expected_result, $actual_result );

	return $passed ? 0 : 1;
}

define( 'VERBOSE_OUTPUT', in_array( '--verbose', $argv, true ) );

run_tests();
