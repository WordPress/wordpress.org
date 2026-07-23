<?php

use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Event\InvalidStart;
use Wporg\TranslationEvents\Event\InvalidEnd;
use Wporg\TranslationEvents\Event\InvalidStatus;
use Wporg\TranslationEvents\Event\Event_End_Date;
use Wporg\TranslationEvents\Event\Event_Start_Date;

class Event_Test extends TestCase {
	public function test_validates_start_and_end() {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );

		$this->expectException( InvalidEnd::class );
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

	public function test_validates_start_and_end_timezone() {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );

		$this->expectException( InvalidStart::class );
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

	public function test_validates_status() {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );

		$this->expectException( InvalidStatus::class );
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
