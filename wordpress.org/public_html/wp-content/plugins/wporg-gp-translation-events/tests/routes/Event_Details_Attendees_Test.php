<?php
/**
 * Tests that the attendees section on the event details page is only shown
 * while the event is active.
 *
 * @package wporg-gp-translation-events
 */

declare( strict_types = 1 );

use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Routes\Event\Details_Route;
use Wporg\TranslationEvents\Tests\Event_Factory;

/**
 * The event details page lists attendees who pledged to help but did not end
 * up contributing. Once an event is over, that list should no longer be
 * shown, so a no-show is not called out publicly after the fact. This change
 * hides the list in both the template renderer and the 2024 block theme
 * renderer.
 *
 * These tests render the details route for an event that is still active and
 * for one that has ended, and assert the attendees section is present only
 * while the event is active. The current user is granted event management
 * rights so the capability gate on the section is satisfied and the event
 * state is the only variable under test.
 */
class Event_Details_Attendees_Test extends TestCase {
	/**
	 * Factory used to create events for the tests.
	 *
	 * @var Event_Factory
	 */
	private Event_Factory $event_factory;

	/**
	 * Set up the factory and a user who is allowed to see the section.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->event_factory = new Event_Factory();

		wp_set_current_user( $this->factory->user->create() );
		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );
	}

	/**
	 * Remove the capability filter.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'gp_translation_events_can_crud_event', '__return_true' );
		parent::tearDown();
	}

	/**
	 * The attendees section is shown while the event is active.
	 *
	 * @return void
	 */
	public function test_shows_attendees_for_active_event() {
		$event_id = $this->event_factory->create_active( $this->now, array( $this->factory->user->create() ) );

		$this->assertStringContainsString( 'event-attendees', $this->render_event_details( $event_id ) );
	}

	/**
	 * The attendees section is hidden once the event has ended.
	 *
	 * @return void
	 */
	public function test_hides_attendees_for_past_event() {
		$event_id = $this->event_factory->create_inactive_past( $this->now, array( $this->factory->user->create() ) );

		$this->assertStringNotContainsString( 'event-attendees', $this->render_event_details( $event_id ) );
	}

	/**
	 * Render the details route for an event and return the generated HTML.
	 *
	 * @param int $event_id Event to render.
	 * @return string
	 */
	private function render_event_details( int $event_id ): string {
		wp_cache_flush();

		$route               = new Details_Route();
		$route->fake_request = true;

		ob_start();
		$route->handle( get_post( $event_id )->post_name );
		return (string) ob_get_clean();
	}
}
