<?php

use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Tests\Stats_Factory;
use Wporg\TranslationEvents\Tests\Event_Factory;
use Wporg\TranslationEvents\Tests\Translation_Factory;

class Attendee_Repository_Test extends TestCase {
	private Attendee_Repository $repository;
	private Stats_Factory $stats_factory;
	private Event_Factory $event_factory;
	private Translation_Factory $translation_factory;


	public function setUp(): void {
		parent::setUp();
		$this->repository          = new Attendee_Repository();
		$this->stats_factory       = new Stats_Factory();
		$this->event_factory       = new Event_Factory();
		$this->translation_factory = new Translation_Factory( $this->factory );
	}

	public function test_add_attendee_invalid_event_id() {
		$this->expectExceptionMessage( 'invalid event id' );
		$this->repository->insert_attendee( new Attendee( 0, 1 ) );
	}

	public function test_add_attendee_invalid_user_id() {
		$this->expectExceptionMessage( 'invalid user id' );
		$this->repository->insert_attendee( new Attendee( 1, 0 ) );
	}

	public function test_insert_attendee() {
		$event1_id = 1;
		$event2_id = 2;
		$user_id   = 42;

		$this->repository->insert_attendee( new Attendee( $event1_id, $user_id ) );
		$this->repository->insert_attendee( new Attendee( $event2_id, $user_id, true, true ) );

		$rows = $this->all_table_rows();
		$this->assertCount( 2, $rows );

		$this->assertEquals( $event1_id, $rows[0]->event_id );
		$this->assertEquals( $user_id, $rows[0]->user_id );
		$this->assertEquals( 0, $rows[0]->is_host );
		$this->assertEquals( 0, $rows[0]->is_new_contributor );

		$this->assertEquals( $event2_id, $rows[1]->event_id );
		$this->assertEquals( $user_id, $rows[1]->user_id );
		$this->assertEquals( 1, $rows[1]->is_host );
		$this->assertEquals( 1, $rows[1]->is_new_contributor );
	}

	public function test_remove_attendee() {
		$event1_id = 1;
		$event2_id = 2;
		$user_id   = 42;

		$this->repository->insert_attendee( new Attendee( $event1_id, $user_id ) );
		$this->repository->insert_attendee( new Attendee( $event2_id, $user_id ) );

		$this->repository->remove_attendee( $event1_id, $user_id );

		$rows = $this->all_table_rows();
		$this->assertCount( 1, $rows );
		$this->assertEquals( $event2_id, $rows[0]->event_id );
	}

	public function test_get_attendee_for_event_for_user() {
		$event1_id = 1;
		$event2_id = 2;
		$user1_id  = 42;
		$user2_id  = 43;

		// Host contributor.
		$attendee11 = new Attendee( $event1_id, $user1_id, true, true, array( 'aa' ) );
		$this->stats_factory->create( $event1_id, $user1_id, 1, 'create' );
		$this->repository->insert_attendee( $attendee11 );

		// Non-host, non-contributor.
		$attendee12 = new Attendee( $event1_id, $user2_id );
		$this->repository->insert_attendee( $attendee12 );

		// Add some more attendees to make sure we get the right ones.
		$this->repository->insert_attendee( new Attendee( $event1_id, 84 ) );
		$this->repository->insert_attendee( new Attendee( $event2_id, 84 ) );
		$this->repository->insert_attendee( new Attendee( $event2_id, $user1_id ) );

		$retrieved_attendee_11 = $this->repository->get_attendee_for_event_for_user( $event1_id, $user1_id );
		$this->assertEquals( $attendee11, $retrieved_attendee_11 );
		$this->assertTrue( $retrieved_attendee_11->is_host() );
		$this->assertTrue( $retrieved_attendee_11->is_new_contributor() );
		$this->assertTrue( $retrieved_attendee_11->is_contributor() );

		$retrieved_attendee_12 = $this->repository->get_attendee_for_event_for_user( $event1_id, $user2_id );
		$this->assertEquals( $attendee12, $retrieved_attendee_12 );
		$this->assertFalse( $retrieved_attendee_12->is_host() );
		$this->assertFalse( $retrieved_attendee_12->is_new_contributor() );
		$this->assertFalse( $retrieved_attendee_12->is_contributor() );

		$this->assertNull( $this->repository->get_attendee_for_event_for_user( $event2_id, $user2_id ) );
	}

	public function test_get_attendees_for_user_for_events() {
		$event1_id = 1;
		$event2_id = 2;
		$event3_id = 3;
		$user1_id  = 42;
		$user2_id  = 43;

		// Event 1, Host contributor.
		$attendee11 = new Attendee( $event1_id, $user1_id, true, true, array( 'aa' ) );
		$this->stats_factory->create( $event1_id, $user1_id, 1, 'create' );
		$this->repository->insert_attendee( $attendee11 );

		// Event 1, Non-host, non-contributor.
		$attendee12 = new Attendee( $event1_id, $user2_id );
		$this->repository->insert_attendee( $attendee12 );

		// Event 2, Host non-contributor.
		$attendee21 = new Attendee( $event2_id, $user1_id, true, false );
		$this->repository->insert_attendee( $attendee21 );

		// Event 2, Non-host, contributor.
		$attendee22 = new Attendee( $event2_id, $user2_id, false, false, array( 'aa' ) );
		$this->stats_factory->create( $event2_id, $user2_id, 1, 'create' );
		$this->repository->insert_attendee( $attendee22 );

		// Add some more attendees to make sure we get the right ones.
		$this->repository->insert_attendee( new Attendee( $event1_id, 84 ) );
		$this->repository->insert_attendee( new Attendee( $event2_id, 84 ) );
		$this->repository->insert_attendee( new Attendee( $event2_id, $user1_id ) );

		$retrieved_attendees_1 = $this->repository->get_attendees_for_user_for_events( $user1_id, array( $event1_id, $event2_id ) );
		$this->assertCount( 2, $retrieved_attendees_1 );
		$this->assertEquals( $attendee11, $retrieved_attendees_1[ $event1_id ] );
		$this->assertTrue( $retrieved_attendees_1[ $event1_id ]->is_host() );
		$this->assertTrue( $retrieved_attendees_1[ $event1_id ]->is_new_contributor() );
		$this->assertTrue( $retrieved_attendees_1[ $event1_id ]->is_contributor() );

		$this->assertEquals( $attendee21, $retrieved_attendees_1[ $event2_id ] );
		$this->assertTrue( $retrieved_attendees_1[ $event2_id ]->is_host() );
		$this->assertFalse( $retrieved_attendees_1[ $event2_id ]->is_new_contributor() );
		$this->assertFalse( $retrieved_attendees_1[ $event2_id ]->is_contributor() );

		$retrieved_attendees_2 = $this->repository->get_attendees_for_user_for_events( $user2_id, array( $event1_id, $event2_id ) );
		$this->assertCount( 2, $retrieved_attendees_2 );
		$this->assertEquals( $attendee12, $retrieved_attendees_2[ $event1_id ] );
		$this->assertFalse( $retrieved_attendees_2[ $event1_id ]->is_host() );
		$this->assertFalse( $retrieved_attendees_2[ $event1_id ]->is_new_contributor() );
		$this->assertFalse( $retrieved_attendees_2[ $event1_id ]->is_contributor() );

		$this->assertEquals( $attendee22, $retrieved_attendees_2[ $event2_id ] );
		$this->assertFalse( $retrieved_attendees_2[ $event2_id ]->is_host() );
		$this->assertFalse( $retrieved_attendees_2[ $event2_id ]->is_new_contributor() );
		$this->assertTrue( $retrieved_attendees_2[ $event2_id ]->is_contributor() );

		$this->assertEmpty( $this->repository->get_attendees_for_user_for_events( $user2_id, array( $event3_id ) ) );
	}

	public function test_get_hosts() {
		$event1_id = 1;
		$event2_id = 2;
		$user1_id  = 42;
		$user2_id  = 43;

		$host11 = new Attendee( $event1_id, $user1_id, true );
		$this->repository->insert_attendee( $host11 );

		$host12 = new Attendee( $event1_id, $user2_id, true );
		$this->repository->insert_attendee( $host12 );

		$host21 = new Attendee( $event2_id, $user1_id, true );
		$this->repository->insert_attendee( $host21 );

		// Add some more attendees to make sure we get the right ones.
		$this->repository->insert_attendee( new Attendee( $event1_id, 84 ) );
		$this->repository->insert_attendee( new Attendee( $event1_id, 85 ) );
		$this->repository->insert_attendee( new Attendee( $event2_id, 84 ) );
		$this->repository->insert_attendee( new Attendee( $event2_id, $user1_id ) );

		$hosts = $this->repository->get_hosts( $event1_id );
		$this->assertCount( 2, $hosts );
		$this->assertEquals( $host11, $hosts[ $user1_id ] );
		$this->assertEquals( $host12, $hosts[ $user2_id ] );

		$hosts = $this->repository->get_hosts( $event2_id );
		$this->assertCount( 1, $hosts );
		$this->assertEquals( $host21, $hosts[ $user1_id ] );
	}

	public function test_get_attendees() {
		$event1_id = 1;
		$event2_id = 2;
		$user1_id  = 42;
		$user2_id  = 43;

		// Host, contributor.
		$attendee11 = new Attendee( $event1_id, $user1_id, true, true, array( 'aa' ) );
		$this->stats_factory->create( $event1_id, $user1_id, 1, 'create' );
		$this->repository->insert_attendee( $attendee11 );

		// Non-host, non-contributor.
		$attendee12 = new Attendee( $event1_id, $user2_id, false );
		$this->repository->insert_attendee( $attendee12 );

		// Host, non-contributor.
		$attendee21 = new Attendee( $event2_id, $user1_id, true );
		$this->repository->insert_attendee( $attendee21 );

		// Non-host, contributor.
		$attendee22 = new Attendee( $event2_id, $user2_id, false, true, array( 'aa' ) );
		$this->stats_factory->create( $event2_id, $user2_id, 1, 'create' );
		$this->repository->insert_attendee( $attendee22 );

		$attendees = $this->repository->get_attendees( $event1_id );
		$this->assertCount( 2, $attendees );
		$this->assertEquals( $attendee11, $attendees[ $user1_id ] );
		$this->assertEquals( $attendee12, $attendees[ $user2_id ] );
		$this->assertTrue( $attendees[ $user1_id ]->is_host() );
		$this->assertTrue( $attendees[ $user1_id ]->is_new_contributor() );
		$this->assertFalse( $attendees[ $user2_id ]->is_host() );
		$this->assertFalse( $attendees[ $user2_id ]->is_new_contributor() );
		$this->assertTrue( $attendees[ $user1_id ]->is_contributor() );
		$this->assertFalse( $attendees[ $user2_id ]->is_contributor() );

		$attendees = $this->repository->get_attendees( $event2_id );
		$this->assertCount( 2, $attendees );
		$this->assertEquals( $attendee21, $attendees[ $user1_id ] );
		$this->assertEquals( $attendee22, $attendees[ $user2_id ] );
		$this->assertTrue( $attendees[ $user1_id ]->is_host() );
		$this->assertFalse( $attendees[ $user1_id ]->is_new_contributor() );
		$this->assertFalse( $attendees[ $user2_id ]->is_host() );
		$this->assertTrue( $attendees[ $user2_id ]->is_new_contributor() );
		$this->assertFalse( $attendees[ $user1_id ]->is_contributor() );
		$this->assertTrue( $attendees[ $user2_id ]->is_contributor() );
	}

	public function test_get_attendees_not_contributing() {
		$event1_id = 1;
		$user1_id  = 42;
		$user2_id  = 43;
		$user3_id  = 44;

		// Contributor.
		$attendee11 = new Attendee( $event1_id, $user1_id );
		$this->stats_factory->create( $event1_id, $user1_id, 1, 'create' );
		$this->repository->insert_attendee( $attendee11 );

		// Host non-contributor.
		$attendee12 = new Attendee( $event1_id, $user2_id, true );
		$this->repository->insert_attendee( $attendee12 );

		// Non-host non-contributor.
		$attendee13 = new Attendee( $event1_id, $user3_id );
		$this->repository->insert_attendee( $attendee13 );

		$attendees = $this->repository->get_attendees_not_contributing( $event1_id );
		$this->assertCount( 2, $attendees );
		$this->assertEquals( $attendee12, $attendees[ $user2_id ] );
		$this->assertEquals( $attendee13, $attendees[ $user3_id ] );
		$this->assertTrue( $attendees[ $user2_id ]->is_host() );
		$this->assertFalse( $attendees[ $user3_id ]->is_host() );
	}

	public function test_recheck_new_contributor_status() {
		$user1_id = 42;
		$user2_id = 43;

		$event1_id = $this->event_factory->create_inactive_future( $this->now );

		// Attendee, new contributor.
		$attendee11 = new Attendee( $event1_id, $user1_id, true, true );
		$this->repository->insert_attendee( $attendee11 );

		// Attendee, existing contributor.
		$attendee12 = new Attendee( $event1_id, $user2_id, false, false, array( 'aa' ) );
		$this->stats_factory->create( $event1_id, $user2_id, 1, 'create' );
		$this->repository->insert_attendee( $attendee12 );

		$attendees = $this->repository->get_attendees( $event1_id );
		$this->assertCount( 2, $attendees );
		$this->assertEquals( $attendee11, $attendees[ $user1_id ] );
		$this->assertEquals( $attendee12, $attendees[ $user2_id ] );
		$this->assertTrue( $attendees[ $user1_id ]->is_new_contributor() );
		$this->assertFalse( $attendees[ $user2_id ]->is_new_contributor() );

		for ( $i = 1; $i < 12; $i++ ) {
			$this->translation_factory->create( $user1_id );
		}
		$this->repository->recheck_new_contributor_status( $event1_id );
		$attendees = $this->repository->get_attendees( $event1_id );

		$this->assertFalse( $attendees[ $user1_id ]->is_new_contributor() );
		$this->assertFalse( $attendees[ $user2_id ]->is_new_contributor() );
	}

	private function all_table_rows(): array {
		global $wpdb, $gp_table_prefix;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_results( "select * from {$gp_table_prefix}event_attendees order by event_id, user_id" );
		// phpcs:enable
	}
}
