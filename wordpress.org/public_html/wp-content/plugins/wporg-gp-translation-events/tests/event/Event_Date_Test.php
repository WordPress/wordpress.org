<?php
/**
 * Tests for the Event_Date class.
 *
 * @package wporg-gp-translation-events
 */

use PHPUnit\Framework\Attributes\DataProvider;
use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Event\Event_Start_Date;

/**
 * Tests for the Event_Date class.
 */
class Event_Date_Test extends TestCase {
	/**
	 * An event date reports its own timezone while converting to UTC on demand.
	 */
	public function test_timezone() {
		$start = new Event_Start_Date( '2024-03-07 12:00:00', new \DateTimeZone( 'UTC' ) );
		$this->assertEquals( 'UTC', $start->timezone()->getName() );
		$this->assertEquals( 'UTC', $start->utc()->getTimezone()->getName() );
		$this->assertEquals( '2024-03-07 12:00:00', $start->utc()->format( 'Y-m-d H:i:s' ) );
		$this->assertEquals( '2024-03-07 12:00:00', $start->format( 'Y-m-d H:i:s' ) );
		$this->assertEquals( '2024-03-07 12:00:00', strval( $start ) );
		$this->assertEquals( 'Thursday, March 7, 2024', $start->utc()->format( 'l, F j, Y' ) );
		$this->assertEquals( 'Thursday, March 7, 2024', $start->format( 'l, F j, Y' ) );

		$start = new Event_Start_Date( '2024-03-07 12:00:00', new \DateTimeZone( 'Asia/Taipei' ) );
		$this->assertEquals( 'Asia/Taipei', $start->timezone()->getName() );
		$this->assertEquals( 'UTC', $start->utc()->getTimezone()->getName() );
		$this->assertEquals( '2024-03-07 20:00:00', $start->format( 'Y-m-d H:i:s' ) );
		$this->assertEquals( '2024-03-07 12:00:00', $start->utc()->format( 'Y-m-d H:i:s' ) );
		$this->assertEquals( '2024-03-07 12:00:00', strval( $start ) );
	}

	/**
	 * The time element's datetime attribute is an ISO 8601 UTC timestamp, so browsers convert it unambiguously.
	 *
	 * @param string $method The print method to call.
	 */
	#[DataProvider( 'data_print_methods' )]
	public function test_time_element_datetime_is_iso_8601_utc( string $method ) {
		$start = new Event_Start_Date( '2025-04-05 11:30:00', new \DateTimeZone( 'Europe/Berlin' ) );

		ob_start();
		$start->$method();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'datetime="2025-04-05T11:30:00Z"', $html );
	}

	/**
	 * The methods that print a time element.
	 *
	 * @return array[]
	 */
	public static function data_print_methods(): array {
		return array(
			'relative'              => array( 'print_relative_time_html' ),
			'absolute and relative' => array( 'print_absolute_and_relative_time_html' ),
			'absolute'              => array( 'print_time_html' ),
		);
	}
}
