<?php
/**
 * Tests that routes check the capability that matches the action they perform.
 *
 * @package wporg-gp-translation-events
 */

declare( strict_types = 1 );

use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Routes\Attendee\Remove_Attendee_Route;
use Wporg\TranslationEvents\Routes\Event\Image_Route;
use Wporg\TranslationEvents\Routes\Event\Translations_Route;
use Wporg\TranslationEvents\Routes\User\Attend_Event_Route;
use Wporg\TranslationEvents\Routes\User\Attendance_Mode_Route;
use Wporg\TranslationEvents\Routes\User\Host_Event_Route;
use Wporg\TranslationEvents\Tests\Event_Factory;
use Wporg\TranslationEvents\Tests\Stats_Factory;

/**
 * Every case sends a valid nonce and asserts the route refuses on authorization alone.
 * A refusal is a 403 with the route exited and no redirect issued; reaching the redirect
 * means the state change ran.
 */
class Authorization_Guards_Test extends TestCase {
	/**
	 * Factory used to create events for the tests.
	 *
	 * @var Event_Factory
	 */
	private Event_Factory $event_factory;

	/**
	 * Repository used to seed and inspect attendees.
	 *
	 * @var Attendee_Repository
	 */
	private Attendee_Repository $attendee_repository;

	/**
	 * Factory used to create stats rows for the tests.
	 *
	 * @var Stats_Factory
	 */
	private Stats_Factory $stats_factory;

	/**
	 * Records the redirect target and suppresses the header() call.
	 *
	 * @var callable
	 */
	private $redirect_guard;

	/**
	 * Where the route tried to redirect, or null if it never got that far.
	 *
	 * @var string|null
	 */
	private ?string $redirect_to = null;

	/**
	 * Sets up the test case before each test runs.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->event_factory       = new Event_Factory();
		$this->attendee_repository = new Attendee_Repository();
		$this->stats_factory       = new Stats_Factory();

		$this->redirect_guard = function ( string $location ): bool {
			$this->redirect_to = $location;
			return false;
		};
		add_filter( 'wp_redirect', $this->redirect_guard );
	}

	/**
	 * Cleans up after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'wp_redirect', $this->redirect_guard );
		unset( $_GET['_wpnonce'], $_POST['_wpnonce'], $_POST['_attendee_nonce'] );
		$this->stats_factory->clean();
		parent::tearDown();
	}

	/**
	 * The translations route refuses a viewer who cannot see the event.
	 *
	 * @return void
	 */
	public function test_translations_route_requires_view_capability(): void {
		$this->set_normal_user_as_current();
		$event_id = $this->event_factory->create_draft( $this->now );
		wp_set_current_user( 0 );

		$route = new Translations_Route();
		$slug  = get_post( $event_id )->post_name;
		$this->run_route( $route, fn() => $route->handle( $slug, 'aa' ) );

		$this->assert_refused( $route );
	}

	/**
	 * The attend route refuses a user who cannot see the event.
	 *
	 * @return void
	 */
	public function test_attend_route_requires_view_capability(): void {
		$this->set_normal_user_as_current();
		$event_id = $this->event_factory->create_draft( $this->now );
		$user_id  = $this->set_normal_user_as_current();

		$_POST['_attendee_nonce'] = wp_create_nonce( 'attend_translation_event_' . $event_id );
		$route                    = new Attend_Event_Route();
		$this->run_route( $route, fn() => $route->handle( $event_id ) );

		$this->assert_refused( $route );
		$this->assertNull( $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $user_id ) );
	}

	/**
	 * A host cannot remove the event author from the attendee list.
	 *
	 * @return void
	 */
	public function test_host_cannot_remove_event_author(): void {
		$author_id = $this->set_normal_user_as_current();
		$host_id   = $this->factory->user->create();
		$event_id  = $this->event_factory->create_active( $this->now, array( $author_id ) );
		$this->attendee_repository->insert_attendee( new Attendee( $event_id, $host_id, true ) );
		wp_set_current_user( $host_id );

		$_GET['_wpnonce'] = wp_create_nonce( "remove_translation_event_attendee_{$event_id}_{$author_id}" );
		$route            = new Remove_Attendee_Route();
		$this->run_route( $route, fn() => $route->handle( $event_id, $author_id ) );

		$this->assert_refused( $route );
		$this->assertInstanceOf( Attendee::class, $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $author_id ) );
	}

	/**
	 * A host cannot remove another host from the attendee list.
	 *
	 * @return void
	 */
	public function test_host_cannot_remove_another_host(): void {
		$this->set_normal_user_as_current();
		$host_id       = $this->factory->user->create();
		$other_host_id = $this->factory->user->create();
		$event_id      = $this->event_factory->create_active( $this->now );
		$this->attendee_repository->insert_attendee( new Attendee( $event_id, $host_id, true ) );
		$this->attendee_repository->insert_attendee( new Attendee( $event_id, $other_host_id, true ) );
		wp_set_current_user( $host_id );

		$_GET['_wpnonce'] = wp_create_nonce( "remove_translation_event_attendee_{$event_id}_{$other_host_id}" );
		$route            = new Remove_Attendee_Route();
		$this->run_route( $route, fn() => $route->handle( $event_id, $other_host_id ) );

		$this->assert_refused( $route );
		$this->assertInstanceOf( Attendee::class, $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $other_host_id ) );
	}

	/**
	 * A WordPress editor passes edit_translation_event through edit_post but is not allowed to manage attendees.
	 *
	 * @return void
	 */
	public function test_editor_cannot_manage_hosts(): void {
		$this->set_normal_user_as_current();
		$attendee_id = $this->factory->user->create();
		$event_id    = $this->event_factory->create_active( $this->now, array( $attendee_id ) );
		$this->become_editor();
		$this->assertTrue( current_user_can( 'edit_translation_event', $event_id ) );
		$this->assertFalse( current_user_can( 'edit_translation_event_attendees', $event_id ) );

		$_POST['_wpnonce'] = wp_create_nonce( "toggle_translation_event_host_{$event_id}_{$attendee_id}" );
		$route             = new Host_Event_Route();
		$this->run_route( $route, fn() => $route->handle( $event_id, $attendee_id ) );

		$this->assert_refused( $route );
		$this->assertFalse( $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $attendee_id )->is_host() );
	}

	/**
	 * A WordPress editor cannot change an attendee's attendance mode.
	 *
	 * @return void
	 */
	public function test_editor_cannot_change_attendance_mode(): void {
		$this->set_normal_user_as_current();
		$attendee_id = $this->factory->user->create();
		$event_id    = $this->event_factory->create_active( $this->now, array( $attendee_id ) );
		$this->become_editor();

		$_GET['_wpnonce'] = wp_create_nonce( "toggle_translation_event_attendance_mode_{$event_id}_{$attendee_id}" );
		$route            = new Attendance_Mode_Route();
		$this->run_route( $route, fn() => $route->handle( $event_id, $attendee_id ) );

		$this->assert_refused( $route );
	}

	/**
	 * A host cannot demote the event author from hosting.
	 *
	 * @return void
	 */
	public function test_host_cannot_demote_event_author(): void {
		$author_id = $this->set_normal_user_as_current();
		$host_id   = $this->factory->user->create();
		$event_id  = $this->event_factory->create_active( $this->now, array( $author_id ) );
		$this->attendee_repository->insert_attendee( new Attendee( $event_id, $host_id, true ) );
		$author = $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $author_id );
		$author->mark_as_host();
		$this->attendee_repository->update_attendee( $author );
		wp_set_current_user( $host_id );

		$_POST['_wpnonce'] = wp_create_nonce( "toggle_translation_event_host_{$event_id}_{$author_id}" );
		$route             = new Host_Event_Route();
		$this->run_route( $route, fn() => $route->handle( $event_id, $author_id ) );

		$this->assert_refused( $route );
		$this->assertTrue( $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $author_id )->is_host() );
	}

	/**
	 * The event author can step down as host.
	 *
	 * @return void
	 */
	public function test_author_can_step_down_as_host(): void {
		$author_id = $this->set_normal_user_as_current();
		$event_id  = $this->event_factory->create_active( $this->now, array( $author_id ) );
		$author    = $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $author_id );
		$author->mark_as_host();
		$this->attendee_repository->update_attendee( $author );

		$_POST['_wpnonce'] = wp_create_nonce( "toggle_translation_event_host_{$event_id}_{$author_id}" );
		$route             = new Host_Event_Route();
		$this->run_route( $route, fn() => $route->handle( $event_id, $author_id ) );

		$this->assertNotNull( $this->redirect_to );
		$this->assertFalse( $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $author_id )->is_host() );
	}

	/**
	 * Demoting a co-host would let a host remove them next, so only administrators may.
	 *
	 * @return void
	 */
	public function test_host_cannot_demote_another_host(): void {
		$this->set_normal_user_as_current();
		$host_id       = $this->factory->user->create();
		$other_host_id = $this->factory->user->create();
		$event_id      = $this->event_factory->create_active( $this->now );
		$this->attendee_repository->insert_attendee( new Attendee( $event_id, $host_id, true ) );
		$this->attendee_repository->insert_attendee( new Attendee( $event_id, $other_host_id, true ) );
		wp_set_current_user( $host_id );

		$_POST['_wpnonce'] = wp_create_nonce( "toggle_translation_event_host_{$event_id}_{$other_host_id}" );
		$route             = new Host_Event_Route();
		$this->run_route( $route, fn() => $route->handle( $event_id, $other_host_id ) );

		$this->assert_refused( $route );
		$this->assertTrue( $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $other_host_id )->is_host() );
	}

	/**
	 * A host can step down as host.
	 *
	 * @return void
	 */
	public function test_host_can_step_down_as_host(): void {
		$this->set_normal_user_as_current();
		$host_id  = $this->factory->user->create();
		$event_id = $this->event_factory->create_active( $this->now );
		$this->attendee_repository->insert_attendee( new Attendee( $event_id, $host_id, true ) );
		wp_set_current_user( $host_id );

		$_POST['_wpnonce'] = wp_create_nonce( "toggle_translation_event_host_{$event_id}_{$host_id}" );
		$route             = new Host_Event_Route();
		$this->run_route( $route, fn() => $route->handle( $event_id, $host_id ) );

		$this->assertNotNull( $this->redirect_to );
		$this->assertFalse( $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $host_id )->is_host() );
	}

	/**
	 * Removing a contributor deletes their recorded translations, so only administrators may.
	 *
	 * @return void
	 */
	public function test_host_cannot_remove_contributor(): void {
		$this->set_normal_user_as_current();
		$host_id        = $this->factory->user->create();
		$contributor_id = $this->factory->user->create();
		$event_id       = $this->event_factory->create_active( $this->now, array( $contributor_id ) );
		$this->attendee_repository->insert_attendee( new Attendee( $event_id, $host_id, true ) );
		$this->create_stats_for_event( $event_id, $contributor_id );
		$this->assertTrue( $this->attendee_repository->get_attendee_for_event_for_user( $event_id, $contributor_id )->is_contributor() );
		wp_set_current_user( $host_id );

		$_GET['_wpnonce'] = wp_create_nonce( "remove_translation_event_attendee_{$event_id}_{$contributor_id}" );
		$route            = new Remove_Attendee_Route();
		$this->run_route( $route, fn() => $route->handle( $event_id, $contributor_id ) );

		$this->assert_refused( $route );
		$this->assertCount( 1, $this->stats_factory->get_by_event_id( $event_id ) );
	}

	/**
	 * The image route refuses a viewer who cannot see the event.
	 *
	 * @return void
	 */
	public function test_image_route_requires_view_capability(): void {
		$this->set_normal_user_as_current();
		$event_id = $this->event_factory->create_draft( $this->now );
		wp_set_current_user( 0 );

		$route = new Image_Route();
		$this->run_route( $route, fn() => $route->handle( $event_id ) );

		$this->assert_refused( $route );
	}

	/**
	 * Record one translation against the event by the given user.
	 *
	 * @param int $event_id Event to attach stats to.
	 * @param int $user_id  Contributor.
	 * @return void
	 */
	private function create_stats_for_event( int $event_id, int $user_id ): void {
		$translation_set = $this->factory->translation_set->create_with_project_and_locale();
		$original        = $this->factory->original->create( array( 'project_id' => $translation_set->project_id ) );
		$this->factory->translation->create(
			array(
				'original_id'        => $original->id,
				'translation_set_id' => $translation_set->id,
				'status'             => 'current',
			)
		);
		$this->stats_factory->create( $event_id, $user_id, $original->id, 'create', $translation_set->locale );
	}

	/**
	 * Make a fresh WordPress editor the current user.
	 *
	 * @return int The editor's user id.
	 */
	private function become_editor(): int {
		$editor_id = $this->factory->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		return $editor_id;
	}

	/**
	 * Run a route request under a fake request, swallowing the exit GlotPress throws.
	 *
	 * @param GP_Route $route   Route under test.
	 * @param callable $request Invokes the route method.
	 * @return void
	 */
	private function run_route( GP_Route $route, callable $request ): void {
		$route->fake_request = true;

		ob_start();
		try {
			$request();
		} catch ( GP_Route_Exit_Exception $e ) {
			$route->exited = true;
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Assert that the route refused the request on authorization grounds.
	 *
	 * @param GP_Route $route Route that was run.
	 * @return void
	 */
	private function assert_refused( GP_Route $route ): void {
		$this->assertSame( 403, $route->http_status );
		$this->assertTrue( (bool) $route->exited );
		$this->assertNull( $this->redirect_to, 'Route continued past the authorization guard.' );
	}
}
