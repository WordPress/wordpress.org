<?php
/**
 * Tests for the event form handler.
 *
 * @package wporg-gp-translation-events
 */

declare( strict_types = 1 );

use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Form_Handler;
use Wporg\TranslationEvents\Event\Event_Repository;
use Wporg\TranslationEvents\Tests\Event_Factory;
use Wporg\TranslationEvents\Tests\Stats_Factory;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Only the trash_event branch may move an event to trash: it carries the trash capability
 * check and the stats guard, and it never parses the form. The create and edit branches parse
 * the submitted status, so they must refuse trash rather than apply it.
 */
class Event_Form_Handler_Test extends TestCase {
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
	 * The event repository the handler under test uses.
	 *
	 * @var Event_Repository
	 */
	private Event_Repository $event_repository;

	/**
	 * Filter callback that makes wp_die() throw instead of exiting.
	 *
	 * @var callable
	 */
	private $die_handler_filter;

	/**
	 * Sets up the test case before each test runs.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->event_factory    = new Event_Factory();
		$this->stats_factory    = new Stats_Factory();
		$this->event_repository = new Event_Repository( $this->now, new Attendee_Repository() );

		// wp_send_json() only goes through wp_die() when serving Ajax; otherwise it calls die.
		add_filter( 'wp_doing_ajax', '__return_true' );
		$this->die_handler_filter = function (): callable {
			return function (): void {
				throw new RuntimeException( 'wp_die' );
			};
		};
		add_filter( 'wp_die_ajax_handler', $this->die_handler_filter );
	}

	/**
	 * Cleans up after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'wp_die_ajax_handler', $this->die_handler_filter );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'gp_translation_events_can_crud_event', '__return_true' );
		$this->stats_factory->clean();
		parent::tearDown();
	}

	/**
	 * A user who may edit an event but not trash it must not reach trash through the edit form.
	 *
	 * @return void
	 */
	public function test_edit_form_does_not_trash_event(): void {
		$this->set_normal_user_as_current();
		$event_id = $this->event_factory->create_active( $this->now );
		$this->create_stats_for_event( $event_id );

		$this->assertTrue( current_user_can( 'edit_translation_event', $event_id ) );
		$this->assertFalse( current_user_can( 'trash_translation_event', $event_id ) );

		$response = $this->submit( $this->form_data( 'edit_event', 'trash', $event_id ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid status.', $response['data'] );
		$this->assertSame( 'publish', get_post_status( $event_id ) );
	}

	/**
	 * The create form cannot create an event straight into the trash.
	 *
	 * @return void
	 */
	public function test_create_form_does_not_create_trashed_event(): void {
		$this->set_normal_user_as_current();
		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );

		$response = $this->submit( $this->form_data( 'create_event', 'trash' ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid status.', $response['data'] );

		$trashed = get_posts(
			array(
				'post_type'   => Translation_Events::CPT,
				'post_status' => 'trash',
				'fields'      => 'ids',
			)
		);
		$this->assertSame( array(), $trashed );
	}

	/**
	 * Confirms the fixture is otherwise valid, so the rejections above are about the status alone.
	 *
	 * @return void
	 */
	public function test_edit_form_accepts_publish(): void {
		$this->set_normal_user_as_current();
		$event_id = $this->event_factory->create_active( $this->now );
		$this->create_stats_for_event( $event_id );

		$response = $this->submit( $this->form_data( 'edit_event', 'publish', $event_id ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'publish', get_post_status( $event_id ) );
	}

	/**
	 * The timezone select is disabled for users who may not change it, and disabled fields are
	 * not submitted, so an edit without a timezone must keep the stored one rather than fail.
	 *
	 * @return void
	 */
	public function test_edit_form_keeps_timezone_when_field_is_not_submitted(): void {
		$this->set_normal_user_as_current();
		$event_id = $this->event_factory->create_active( $this->now );
		$this->create_stats_for_event( $event_id );
		$this->assertFalse( current_user_can( 'edit_translation_event_timezone', $event_id ) );

		$form_data = $this->form_data( 'edit_event', 'publish', $event_id );
		unset( $form_data['event_timezone'] );
		$response = $this->submit( $form_data );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Europe/Lisbon', $this->event_repository->get_event( $event_id )->timezone()->getName() );
	}

	/**
	 * A trashed event must be restored through the trash route, which checks the trash capability.
	 *
	 * @return void
	 */
	public function test_edit_form_rejects_trashed_event(): void {
		$this->set_normal_user_as_current();
		$event_id = $this->event_factory->create_active( $this->now );
		wp_trash_post( $event_id );

		$response = $this->submit( $this->form_data( 'edit_event', 'publish', $event_id ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'trash', get_post_status( $event_id ) );
	}

	/**
	 * Unpublishing hides an event exactly as trashing does, so it needs the same capability.
	 *
	 * @return void
	 */
	public function test_edit_form_does_not_unpublish_event_with_stats(): void {
		$this->set_normal_user_as_current();
		$event_id = $this->event_factory->create_active( $this->now );
		$this->create_stats_for_event( $event_id );
		$this->assertFalse( current_user_can( 'trash_translation_event', $event_id ) );

		$response = $this->submit( $this->form_data( 'edit_event', 'draft', $event_id ) );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'publish', get_post_status( $event_id ) );
	}

	/**
	 * A user who may trash the event may also unpublish it through the edit form.
	 *
	 * @return void
	 */
	public function test_edit_form_lets_user_who_can_trash_unpublish(): void {
		$this->set_normal_user_as_current();
		$event_id = $this->event_factory->create_active( $this->now );
		$this->assertTrue( current_user_can( 'trash_translation_event', $event_id ) );

		$response = $this->submit( $this->form_data( 'edit_event', 'draft', $event_id ) );

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'draft', get_post_status( $event_id ) );
	}

	/**
	 * The form refuses an attendance mode the model does not know.
	 *
	 * @return void
	 */
	public function test_form_rejects_unknown_attendance_mode(): void {
		$this->set_normal_user_as_current();
		$event_id = $this->event_factory->create_active( $this->now );

		$form_data                          = $this->form_data( 'edit_event', 'publish', $event_id );
		$form_data['event_attendance_mode'] = 'teleport';
		$response                           = $this->submit( $form_data );

		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Invalid attendance mode.', $response['data'] );
	}

	/**
	 * Unslashed values lose their backslashes because wp_insert_post() expects slashed input.
	 *
	 * @return void
	 */
	public function test_create_form_preserves_backslashes(): void {
		$this->set_normal_user_as_current();
		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );

		$form_data                      = $this->form_data( 'create_event', 'publish' );
		$form_data['event_title']       = 'Path C:\\Events';
		$form_data['event_description'] = 'Escaped \\ backslash';
		// WordPress slashes request data before handlers see it.
		$response = $this->submit( wp_slash( $form_data ) );

		$this->assertTrue( $response['success'] );
		$post = get_post( $response['data']['eventId'] );
		$this->assertSame( 'Path C:\\Events', $post->post_title );
		$this->assertSame( 'Escaped \\ backslash', $post->post_content );
	}

	/**
	 * Run the handler and decode the JSON response it sent.
	 *
	 * @param array $form_data Submitted form fields.
	 * @return array Decoded response.
	 */
	private function submit( array $form_data ): array {
		$handler = new Event_Form_Handler( $this->now, $this->event_repository );
		$exited  = false;

		ob_start();
		try {
			$handler->handle( $form_data );
		} catch ( RuntimeException $e ) {
			$exited = true;
		} finally {
			$output = ob_get_clean();
		}

		$this->assertTrue( $exited, 'Handler returned without sending a response.' );
		return json_decode( $output, true );
	}

	/**
	 * Build a valid form submission, varying only what the test is about.
	 *
	 * @param string $form_name Form branch to exercise.
	 * @param string $status    Value of event_form_action.
	 * @param int    $event_id  Event to edit, or 0 when creating.
	 * @return array
	 */
	private function form_data( string $form_name, string $status, int $event_id = 0 ): array {
		$timezone = new DateTimeZone( 'Europe/Lisbon' );
		$start    = $this->now->setTimezone( $timezone )->modify( '+1 day' );

		return array(
			'form_name'             => $form_name,
			'event_form_action'     => $status,
			'event_id'              => (string) $event_id,
			'_event_nonce'          => wp_create_nonce( '_event_nonce' ),
			'event_title'           => 'Form handler test event',
			'event_description'     => 'Description',
			'event_start'           => $start->format( 'Y-m-d H:i:s' ),
			'event_end'             => $start->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ),
			'event_timezone'        => $timezone->getName(),
			'event_attendance_mode' => 'remote',
		);
	}

	/**
	 * Record one translation against the event by the current user.
	 *
	 * @param int $event_id Event to attach stats to.
	 * @return void
	 */
	private function create_stats_for_event( int $event_id ): void {
		$translation_set = $this->factory->translation_set->create_with_project_and_locale();
		$original        = $this->factory->original->create( array( 'project_id' => $translation_set->project_id ) );
		$this->factory->translation->create(
			array(
				'original_id'        => $original->id,
				'translation_set_id' => $translation_set->id,
				'status'             => 'current',
			)
		);
		$this->stats_factory->create( $event_id, get_current_user_id(), $original->id, 'create', $translation_set->locale );
	}
}
