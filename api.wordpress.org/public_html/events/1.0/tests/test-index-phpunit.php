<?php

namespace Dotorg\API\Events\Tests;
use PHPUnit\Framework\TestCase;
use function Dotorg\API\Events\{ get_events };

/**
 * @group events
 */
class Test_Events extends TestCase {
	public static function setUpBeforeClass() : void {
		require_once dirname( __DIR__ ) . '/index.php';
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

		$this->assertSame( $actual_countries, $expected_countries );
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
			 * This assumes there will always be at least an upcoming event on both sides of the border, so the
			 * coordinates need to be half-way between two very active groups in different countries, where the
			 * mid-point is less than `$event_distances['meetup']`.
			 *
			 * If Toronto, CA and Buffalo, US no longer work in the future, then another possible location would be
			 * `53.997654, -6.403377` -- between Belfast, GB and Dublin, IE -- or `47.986952, -122.961350` --
			 * between Seattle, US and Victoria, CA.
			 *
			 * See https://wordpress.slack.com/archives/C08M59V3P/p1524168308000202.
			 */
			'not-restricted-by-country' => array(
				'input' => array(
					'number'              => '500',
					'restrict_by_country' => false,

					'nearby' => array(
						'latitude'  => '43.254372',
						'longitude' => '-79.063746',
					),
				),
				'expected_countries' => array( 'CA', 'US' ),
			),
		);
	}

	function test_port_remaining_tests() {
		$this->markTestIncomplete( 'Not all of the tests from ./test-index.php have been ported to PHPUnit yet. See the notes in that file.' );
	}
}
