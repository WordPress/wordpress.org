<?php
/**
 * Tests for the translation event capabilities.
 *
 * @package wporg-gp-translation-events
 */

use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository;
use Wporg\TranslationEvents\Tests\Event_Factory;
use Wporg\TranslationEvents\Tests\Stats_Factory;

/**
 * Tests for the translation event capability mapping.
 */
class Event_Capabilities_Test extends TestCase {
	/**
	 * Factory used to create events for the tests.
	 *
	 * @var Event_Factory
	 */
	private Event_Factory $event_factory;

	/**
	 * Factory used to create stats rows for the tests.
	 *
	 * @var Stats_Factory
	 */
	private Stats_Factory $stats_factory;

	/**
	 * Repository used to add attendees and hosts to events.
	 *
	 * @var Attendee_Repository
	 */
	private Attendee_Repository $attendee_repository;

	/**
	 * Repository used to load and update events.
	 *
	 * @var Event_Repository
	 */
	private Event_Repository $event_repository;

	/**
	 * Sets up the test case before each test runs.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->event_factory       = new Event_Factory();
		$this->stats_factory       = new Stats_Factory();
		$this->attendee_repository = new Attendee_Repository();
		$this->event_repository    = new Event_Repository( $this->now, $this->attendee_repository );

		$this->set_normal_user_as_current();
	}

	/**
	 * A user without CRUD permission cannot manage translation events.
	 */
	public function test_cannot_manage_if_no_crud_permission() {
		add_filter( 'gp_translation_events_can_crud_event', '__return_false' );
		$this->assertFalse( current_user_can( 'manage_translation_events' ) );
	}

	/**
	 * A user with CRUD permission can manage translation events.
	 */
	public function test_can_manage_if_crud_permission() {
		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );
		$this->assertTrue( current_user_can( 'manage_translation_events' ) );
	}

	/**
	 * A user without CRUD permission cannot create a translation event.
	 */
	public function test_cannot_create_if_no_crud_permission() {
		add_filter( 'gp_translation_events_can_crud_event', '__return_false' );
		$this->assertFalse( current_user_can( 'create_translation_event' ) );
	}

	/**
	 * A user with CRUD permission can create a translation event.
	 */
	public function test_can_create_if_crud_permission() {
		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );
		$this->assertTrue( current_user_can( 'create_translation_event' ) );
	}

	/**
	 * A regular user cannot view a non-published event.
	 */
	public function test_cannot_view_non_published_events() {
		$event_id = $this->event_factory->create_active( $this->now );
		$event    = $this->event_repository->get_event( $event_id );
		$event->set_status( 'draft' );
		$this->event_repository->update_event( $event );

		$this->assertFalse( current_user_can( 'view_translation_event', $event_id ) );
	}

	/**
	 * A GlotPress admin can view a non-published event.
	 */
	public function test_gp_admin_can_view_non_published_events() {
		$event_id = $this->event_factory->create_active( $this->now );
		$event    = $this->event_repository->get_event( $event_id );
		$event->set_status( 'draft' );
		$this->event_repository->update_event( $event );

		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );
		$this->assertTrue( current_user_can( 'view_translation_event', $event_id ) );
	}

	/**
	 * Capability checks accept the event ID passed as a string.
	 */
	public function test_event_id_as_string() {
		$event_id = $this->event_factory->create_active( $this->now );
		$this->assertTrue( current_user_can( 'edit_translation_event', (string) $event_id ) );
	}

	/**
	 * The event author can edit their own event.
	 */
	public function test_author_can_edit() {
		$event_id = $this->event_factory->create_active( $this->now );
		$this->assertTrue( current_user_can( 'edit_translation_event', $event_id ) );
	}

	/**
	 * A user who is neither the author nor a host cannot edit the event.
	 */
	public function test_non_author_cannot_edit() {
		$non_author_user_id = get_current_user_id();
		$this->set_normal_user_as_current(); // This user is the author.

		$event_id = $this->event_factory->create_active( $this->now );
		$this->assertFalse( user_can( $non_author_user_id, 'edit_translation_event', $event_id ) );
	}

	/**
	 * A host who is not the author can edit the event.
	 */
	public function test_host_can_edit() {
		$this->set_normal_user_as_current();
		$non_author_user_id = get_current_user_id();
		$this->set_normal_user_as_current(); // This user is the author.

		$event_id = $this->event_factory->create_active( $this->now );

		$attendee = new Attendee( $event_id, $non_author_user_id, true );
		$this->attendee_repository->insert_attendee( $attendee );

		$this->assertTrue( user_can( $non_author_user_id, 'edit_translation_event', $event_id ) );
	}

	/**
	 * A GlotPress admin can edit an event they did not author.
	 */
	public function test_gp_admin_can_edit() {
		$non_author_user_id = get_current_user_id();
		$this->set_normal_user_as_current(); // This user is the author.

		$event_id = $this->event_factory->create_active( $this->now );
		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );

		$this->assertTrue( user_can( $non_author_user_id, 'edit_translation_event', $event_id ) );
	}

	/**
	 * The author can still edit an event that is already in the past.
	 */
	public function test_can_edit_past_event() {
		$this->set_normal_user_as_current();

		$event_id = $this->event_factory->create_inactive_past( $this->now );

		$this->assertTrue( current_user_can( 'edit_translation_event', $event_id ) );
	}

	/**
	 * The author can edit an event that already has recorded stats.
	 */
	public function test_can_edit_event_with_stats() {
		$this->set_normal_user_as_current();
		$author_user_id = get_current_user_id();

		$event_id        = $this->event_factory->create_active( $this->now );
		$translation_set = $this->factory->translation_set->create_with_project_and_locale();
		$original        = $this->factory->original->create( array( 'project_id' => $translation_set->project_id ) );
		$this->factory->translation->create(
			array(
				'original_id'        => $original->id,
				'translation_set_id' => $translation_set->id,
				'status'             => 'current',
			)
		);
		$this->stats_factory->create( $event_id, $author_user_id, $original->id, 'create', $translation_set->locale );
		$this->assertTrue( current_user_can( 'edit_translation_event', $event_id ) );
	}

	/**
	 * The author cannot trash an event that already has recorded stats.
	 */
	public function test_cannot_trash_event_with_stats() {
		$author_user_id  = get_current_user_id();
		$event_id        = $this->event_factory->create_active( $this->now );
		$translation_set = $this->factory->translation_set->create_with_project_and_locale();
		$original        = $this->factory->original->create( array( 'project_id' => $translation_set->project_id ) );
		$this->factory->translation->create(
			array(
				'original_id'        => $original->id,
				'translation_set_id' => $translation_set->id,
				'status'             => 'current',
			)
		);
		$this->stats_factory->create( $event_id, $author_user_id, $original->id, 'create', $translation_set->locale );
		$this->assertFalse( current_user_can( 'trash_translation_event', $event_id ) );
	}

	/**
	 * An admin can trash an event that already has recorded stats.
	 */
	public function test_admin_can_trash_event_with_stats() {
		$this->set_admin_user_as_current();
		$author_user_id  = get_current_user_id();
		$event_id        = $this->event_factory->create_active( $this->now );
		$translation_set = $this->factory->translation_set->create_with_project_and_locale();
		$original        = $this->factory->original->create( array( 'project_id' => $translation_set->project_id ) );
		$this->factory->translation->create(
			array(
				'original_id'        => $original->id,
				'translation_set_id' => $translation_set->id,
				'status'             => 'current',
			)
		);
		$this->stats_factory->create( $event_id, $author_user_id, $original->id, 'create', $translation_set->locale );
		$this->assertTrue( current_user_can( 'trash_translation_event', $event_id ) );
	}

	/**
	 * The author can trash their own event that has no stats.
	 */
	public function test_author_can_trash() {
		$event_id = $this->event_factory->create_active( $this->now );
		$this->assertTrue( current_user_can( 'trash_translation_event', $event_id ) );
	}

	/**
	 * A user who is neither the author nor a host cannot trash the event.
	 */
	public function test_non_author_cannot_trash() {
		$non_author_user_id = get_current_user_id();
		$this->set_normal_user_as_current(); // This user is the author.

		$event_id = $this->event_factory->create_active( $this->now );
		$this->assertFalse( user_can( $non_author_user_id, 'trash_translation_event', $event_id ) );
	}

	/**
	 * A host who is not the author can trash the event.
	 */
	public function test_host_can_trash() {
		$non_author_user_id = get_current_user_id();
		$this->set_normal_user_as_current(); // This user is the author.

		$event_id = $this->event_factory->create_active( $this->now );

		$attendee = new Attendee( $event_id, $non_author_user_id, true );
		$this->attendee_repository->insert_attendee( $attendee );

		$this->assertTrue( user_can( $non_author_user_id, 'trash_translation_event', $event_id ) );
	}

	/**
	 * A GlotPress admin can trash an event they did not author.
	 */
	public function test_gp_admin_can_trash() {
		$event_id = $this->event_factory->create_active( $this->now );
		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );
		$this->assertTrue( current_user_can( 'trash_translation_event', $event_id ) );
	}

	/**
	 * An event that has not been trashed cannot be deleted, even with CRUD permission.
	 */
	public function test_cannot_delete_if_not_trashed() {
		$event_id = $this->event_factory->create_active( $this->now );

		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );
		$this->assertFalse( current_user_can( 'delete_translation_event', $event_id ) );
	}

	/**
	 * A user without CRUD permission cannot delete a trashed event.
	 */
	public function test_non_gp_admin_cannot_delete() {
		$event_id = $this->event_factory->create_active( $this->now );
		$event    = $this->event_repository->get_event( $event_id );

		$event->set_status( 'trash' );
		$this->event_repository->update_event( $event );

		add_filter( 'gp_translation_events_can_crud_event', '__return_false' );
		$this->assertFalse( current_user_can( 'delete_translation_event', $event_id ) );
	}

	/**
	 * A GlotPress admin can delete a trashed event.
	 */
	public function test_gp_admin_can_delete() {
		$event_id = $this->event_factory->create_active( $this->now );
		$event    = $this->event_repository->get_event( $event_id );

		$event->set_status( 'trash' );
		$this->event_repository->update_event( $event );

		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );
		$this->assertTrue( current_user_can( 'delete_translation_event', $event_id ) );
	}

	/**
	 * Every editable field is editable before the event starts.
	 */
	public function test_editable_fields_before_event_start() {
		$this->set_normal_user_as_current();

		$event_id = $this->event_factory->create_inactive_future( $this->now );

		$this->assertTrue( current_user_can( 'edit_translation_event_title', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_description', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_start', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_end', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_timezone', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_attendance_mode', $event_id ) );
	}

	/**
	 * Every editable field remains editable after the event starts while it has no stats.
	 */
	public function test_editable_fields_after_event_start_no_stats() {
		$this->set_normal_user_as_current();

		$event_id = $this->event_factory->create_active( $this->now );

		$this->assertTrue( current_user_can( 'edit_translation_event_title', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_description', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_start', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_end', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_timezone', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_attendance_mode', $event_id ) );
	}

	/**
	 * Once an active event has stats, its start, timezone, and attendance mode become locked.
	 */
	public function test_editable_fields_after_event_start_with_stats() {
		$this->set_normal_user_as_current();
		$author_user_id = get_current_user_id();

		$event_id        = $this->event_factory->create_active( $this->now );
		$translation_set = $this->factory->translation_set->create_with_project_and_locale();
		$original        = $this->factory->original->create( array( 'project_id' => $translation_set->project_id ) );
		$this->factory->translation->create(
			array(
				'original_id'        => $original->id,
				'translation_set_id' => $translation_set->id,
				'status'             => 'current',
			)
		);
		$this->stats_factory->create( $event_id, $author_user_id, $original->id, 'create', $translation_set->locale );

		$this->assertTrue( current_user_can( 'edit_translation_event_title', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_description', $event_id ) );
		$this->assertFalse( current_user_can( 'edit_translation_event_start', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_end', $event_id ) );
		$this->assertFalse( current_user_can( 'edit_translation_event_timezone', $event_id ) );
		$this->assertFalse( current_user_can( 'edit_translation_event_attendance_mode', $event_id ) );
	}

	/**
	 * Within one hour of an event ending, the title, description, and end remain editable.
	 */
	public function test_editable_fields_within_one_hour_after_event_ends() {
		$this->set_normal_user_as_current();

		$timezone = new DateTimeZone( 'Europe/Lisbon' );
		$event_id = $this->event_factory->create_event(
			$this->now->modify( '-2 hours' ),
			$this->now->modify( '-59 minutes' ),
			$timezone,
			array(),
		);

		$this->assertTrue( current_user_can( 'edit_translation_event_title', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_description', $event_id ) );
		$this->assertFalse( current_user_can( 'edit_translation_event_start', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_end', $event_id ) );
		$this->assertFalse( current_user_can( 'edit_translation_event_timezone', $event_id ) );
		$this->assertFalse( current_user_can( 'edit_translation_event_attendance_mode', $event_id ) );
	}

	/**
	 * More than one hour after an event ends, only the description remains editable.
	 */
	public function test_editable_fields_more_than_one_hour_after_event_ends() {
		$this->set_normal_user_as_current();

		$timezone = new DateTimeZone( 'Europe/Lisbon' );
		$event_id = $this->event_factory->create_event(
			$this->now->modify( '-3 hours' ),
			$this->now->modify( '-1 hours  -1 minutes' ),
			$timezone,
			array(),
		);

		$this->assertFalse( current_user_can( 'edit_translation_event_title', $event_id ) );
		$this->assertTrue( current_user_can( 'edit_translation_event_description', $event_id ) );
		$this->assertFalse( current_user_can( 'edit_translation_event_start', $event_id ) );
		$this->assertFalse( current_user_can( 'edit_translation_event_end', $event_id ) );
		$this->assertFalse( current_user_can( 'edit_translation_event_timezone', $event_id ) );
		$this->assertFalse( current_user_can( 'edit_translation_event_attendance_mode', $event_id ) );
	}
}
