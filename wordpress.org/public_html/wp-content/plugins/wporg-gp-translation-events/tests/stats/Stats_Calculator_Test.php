<?php

use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Stats\Stats_Calculator;
use Wporg\TranslationEvents\Tests\Event_Factory;
use Wporg\TranslationEvents\Tests\Stats_Factory;

class Stats_Calculator_Test extends TestCase {
	private Event_Factory $event_factory;
	private Stats_Factory $stats_factory;
	private Stats_Calculator $calculator;

	public function setUp(): void {
		parent::setUp();
		$this->event_factory = new Event_Factory();
		$this->stats_factory = new Stats_Factory();
		$this->calculator    = new Stats_Calculator();

		$this->set_normal_user_as_current();
	}

	public function test_tells_that_event_has_no_stats() {
		$user_id  = get_current_user_id();
		$event_id = $this->event_factory->create_active( $this->now, array( $user_id ) );
		$this->assertFalse( $this->calculator->event_has_stats( $event_id ) );
	}

	public function test_tells_that_event_has_stats() {
		$user_id = get_current_user_id();

		$event_id        = $this->event_factory->create_active( $this->now, array( $user_id ) );
		$translation_set = $this->factory->translation_set->create_with_project_and_locale();
		$original        = $this->create_original_and_translation( $translation_set );
		$this->stats_factory->create( $event_id, $user_id, $original->id, 'create', $translation_set->locale );

		$this->assertTrue( $this->calculator->event_has_stats( $event_id ) );
	}

	public function test_calculates_stats_for_event() {
		$user1_id = 42;
		$user2_id = 43;
		$user3_id = 44;

		$event1_id = $this->event_factory->create_active( $this->now, array( $user1_id ) );
		$event2_id = $this->event_factory->create_active( $this->now, array( $user1_id ) );

		// For event1, aa locale, multiple users.
		$translation_set_1 = $this->factory->translation_set->create_with_project_and_locale();
		$original_11       = $this->create_original_and_translation( $translation_set_1 );
		$original_12       = $this->create_original_and_translation( $translation_set_1 );
		$original_13       = $this->create_original_and_translation( $translation_set_1 );
		$this->stats_factory->create( $event1_id, $user1_id, $original_11->id, 'create', $translation_set_1->locale );
		$this->stats_factory->create( $event1_id, $user1_id, $original_12->id, 'create', $translation_set_1->locale );
		$this->stats_factory->create( $event1_id, $user2_id, $original_13->id, 'create', $translation_set_1->locale );
		$this->stats_factory->create( $event1_id, $user2_id, $original_11->id, 'approve', $translation_set_1->locale );
		$this->stats_factory->create( $event1_id, $user2_id, $original_12->id, 'reject', $translation_set_1->locale );
		$this->stats_factory->create( $event1_id, $user3_id, $original_13->id, 'request_changes', $translation_set_1->locale );

		// For event1, bb locale, multiple users.
		$translation_set_2 = $this->factory->translation_set->create_with_project_and_locale();
		$original_21       = $this->create_original_and_translation( $translation_set_2 );
		$original_22       = $this->create_original_and_translation( $translation_set_2 );
		$this->stats_factory->create( $event1_id, $user1_id, $original_21->id, 'create', $translation_set_2->locale );
		$this->stats_factory->create( $event1_id, $user2_id, $original_22->id, 'create', $translation_set_2->locale );
		$this->stats_factory->create( $event1_id, $user3_id, $original_21->id, 'approve', $translation_set_2->locale );
		$this->stats_factory->create( $event1_id, $user3_id, $original_22->id, 'request_changes', $translation_set_2->locale );

		// For event2, which should not be included in the stats.

		$this->stats_factory->create( $event2_id, $user1_id, 31, 'create', $translation_set_1->locale );
		$this->stats_factory->create( $event2_id, $user1_id, 32, 'create', $translation_set_1->locale );
		$this->stats_factory->create( $event2_id, $user2_id, 31, 'approve', $translation_set_1->locale );
		$this->stats_factory->create( $event2_id, $user2_id, 32, 'reject', $translation_set_1->locale );

		$event1 = get_post( $event1_id );
		$stats  = $this->calculator->for_event( $event1->ID );
		$this->assertCount( 2, $stats->rows() );

		// $translation_set_1 Locale.
		$this->assertEquals( 3, $stats->rows()[ $translation_set_1->locale ]->created );
		$this->assertEquals( 3, $stats->rows()[ $translation_set_1->locale ]->reviewed );
		$this->assertEquals( 3, $stats->rows()[ $translation_set_1->locale ]->users );

		// $translation_set_2 Locale.
		$this->assertEquals( 2, $stats->rows()[ $translation_set_2->locale ]->created );
		$this->assertEquals( 2, $stats->rows()[ $translation_set_2->locale ]->reviewed );
		$this->assertEquals( 3, $stats->rows()[ $translation_set_2->locale ]->users );

		// Totals.
		$this->assertEquals( 5, $stats->totals()->created );
		$this->assertEquals( 5, $stats->totals()->reviewed );
		$this->assertEquals( 3, $stats->totals()->users );
	}

	private function create_original_and_translation( $translation_set, $status = 'current' ) {
		$original = $this->factory->original->create( array( 'project_id' => $translation_set->project_id ) );
		$this->factory->translation->create(
			array(
				'original_id'        => $original->id,
				'translation_set_id' => $translation_set->id,
				'status'             => $status,
			)
		);
		return $original;
	}
}
