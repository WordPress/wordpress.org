<?php
/**
 * Tests for the Event class.
 *
 * @package wporg-gp-translation-events
 */

use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Event\Invalid_Start;
use Wporg\TranslationEvents\Event\Invalid_End;
use Wporg\TranslationEvents\Event\Invalid_Status;
use Wporg\TranslationEvents\Event\Event_End_Date;
use Wporg\TranslationEvents\Event\Event_Start_Date;

/**
 * Tests for the Event class.
 */
class Event_Test extends TestCase {
	/**
	 * Constructing an event whose end precedes its start throws Invalid_End.
	 */
	public function test_validates_start_and_end() {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );

		$this->expectException( Invalid_End::class );
		new Event(
			0,
			new Event_Start_Date( 'now' ),
			( new Event_End_Date( 'now' ) )->modify( '-1 hour' ),
			$timezone,
			'publish',
			'Foo title',
			'',
		);
	}

	/**
	 * Constructing an event with start and end dates that already carry a timezone throws Invalid_Start.
	 */
	public function test_validates_start_and_end_timezone() {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );

		$this->expectException( Invalid_Start::class );
		new Event(
			0,
			new Event_Start_Date( 'now', $timezone ),
			( new Event_End_Date( 'now', $timezone ) )->modify( '+1 hour' ),
			$timezone,
			'publish',
			'Foo title',
			'',
		);
	}

	/**
	 * Constructing an event with an empty status throws Invalid_Status.
	 */
	public function test_validates_status() {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );

		$this->expectException( Invalid_Status::class );
		new Event(
			0,
			new Event_Start_Date( 'now' ),
			( new Event_End_Date( 'now' ) )->modify( '+1 hour' ),
			$timezone,
			'',
			'Foo title',
			'',
		);
	}

	/**
	 * Only an event whose window spans the current time reports as active.
	 */
	public function test_is_active() {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );
		$start    = new Event_Start_Date( 'now' );
		$end      = new Event_End_Date( 'now' );

		$past_event = new Event(
			0,
			$start->modify( '-1 hour' ),
			$end,
			$timezone,
			'publish',
			'Foo title',
			'',
		);

		$active_event = new Event(
			0,
			$start,
			$end->modify( '+1 hour' ),
			$timezone,
			'publish',
			'Foo title',
			'',
		);

		$future_event = new Event(
			0,
			$start->modify( '+1 hour' ),
			$end->modify( '+2 hours' ),
			$timezone,
			'publish',
			'Foo title',
			'',
		);

		$this->assertFalse( $past_event->is_active() );
		$this->assertTrue( $active_event->is_active() );
		$this->assertFalse( $future_event->is_active() );
	}

	/**
	 * Only an event whose end is before the current time reports as past.
	 */
	public function test_is_past() {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );
		$start    = new Event_Start_Date( 'now' );
		$end      = new Event_End_Date( 'now' );

		$past_event = new Event(
			0,
			$start->modify( '-1 hour' ),
			$end->modify( '-30 minutes' ),
			$timezone,
			'publish',
			'Foo title',
			'',
		);

		$active_event = new Event(
			0,
			$start,
			$end->modify( '+1 hour' ),
			$timezone,
			'publish',
			'Foo title',
			'',
		);

		$future_event = new Event(
			0,
			$start->modify( '+1 hour' ),
			$end->modify( '+2 hours' ),
			$timezone,
			'publish',
			'Foo title',
			'',
		);

		$this->assertTrue( $past_event->is_past() );
		$this->assertFalse( $active_event->is_past() );
		$this->assertFalse( $future_event->is_past() );
	}
}
