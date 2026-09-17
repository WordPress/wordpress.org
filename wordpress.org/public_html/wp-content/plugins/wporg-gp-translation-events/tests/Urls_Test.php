<?php
/**
 * Tests for the Urls class.
 *
 * @package wporg-gp-translation-events
 */

use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository;
use Wporg\TranslationEvents\Tests\Event_Factory;
use Wporg\TranslationEvents\Urls;
use Wporg\Tests\Base_Test as TestCase;

/**
 * Tests for the Urls class.
 */
class Urls_Test extends TestCase {
	/**
	 * Factory used to create events for the tests.
	 *
	 * @var Event_Factory
	 */
	private Event_Factory $event_factory;

	/**
	 * Repository used to load events created by the factory.
	 *
	 * @var Event_Repository
	 */
	private Event_Repository $event_repository;

	/**
	 * Sets up the test case before each test runs.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->event_factory    = new Event_Factory();
		$this->event_repository = new Event_Repository( $this->now, new Attendee_Repository() );

		$this->set_normal_user_as_current();
	}

	/**
	 * The events home URL points at the events root.
	 */
	public function test_events_home() {
		$expected = '/glotpress/events';
		$this->assertEquals( $expected, Urls::events_home() );
	}

	/**
	 * The event details URL includes the event slug.
	 */
	public function test_event_details() {
		$event_id = $this->event_factory->create_active( $this->now );
		$event    = $this->event_repository->get_event( $event_id );

		$expected = "/glotpress/events/{$event->slug()}";
		$this->assertEquals( $expected, Urls::event_details( $event_id ) );
	}

	/**
	 * The event details URL is built from the slug even for draft events.
	 */
	public function test_event_details_draft() {
		$event_id          = $this->event_factory->create_active( $this->now );
		$post              = get_post( $event_id );
		$post->post_status = 'draft';
		wp_update_post( $post );

		$event = $this->event_repository->get_event( $event_id );

		$expected = "/glotpress/events/{$event->slug()}";
		$this->assertEquals( $expected, Urls::event_details( $event_id ) );
	}

	/**
	 * The absolute event details URL is prefixed with the site URL.
	 */
	public function test_event_details_absolute() {
		$event_id = $this->event_factory->create_active( $this->now );
		$event    = $this->event_repository->get_event( $event_id );

		$expected = site_url() . "/glotpress/events/{$event->slug()}";
		$this->assertEquals( $expected, Urls::event_details_absolute( $event_id ) );
	}

	/**
	 * The event translations URL includes the locale and optional filter segment.
	 */
	public function test_event_translations() {
		$event_id = $this->event_factory->create_active( $this->now );
		$event    = $this->event_repository->get_event( $event_id );

		$expected = "/glotpress/events/{$event->slug()}/translations/pt";
		$this->assertEquals( $expected, Urls::event_translations( $event_id, 'pt' ) );

		$expected = "/glotpress/events/{$event->slug()}/translations/pt/waiting";
		$this->assertEquals( $expected, Urls::event_translations( $event_id, 'pt', 'waiting' ) );
	}

	/**
	 * The event edit URL includes the event ID.
	 */
	public function test_event_edit() {
		$event_id = 42;
		$expected = "/glotpress/events/edit/$event_id";
		$this->assertEquals( $expected, Urls::event_edit( $event_id ) );
	}

	/**
	 * The event trash URL carries a valid trash nonce.
	 */
	public function test_event_trash() {
		$event_id = 42;
		$url      = Urls::event_trash( $event_id );
		$this->assertStringStartsWith( "/glotpress/events/trash/$event_id?_wpnonce=", $url );
		$nonce = wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $nonce, $query );
		$this->assertNotFalse( wp_verify_nonce( $query['_wpnonce'], 'trash_translation_event_' . $event_id ) );
	}

	/**
	 * The event delete URL carries a valid delete nonce.
	 */
	public function test_event_delete() {
		$event_id = 42;
		$url      = Urls::event_delete( $event_id );
		$this->assertStringStartsWith( "/glotpress/events/delete/$event_id?_wpnonce=", $url );
		$nonce = wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $nonce, $query );
		$this->assertNotFalse( wp_verify_nonce( $query['_wpnonce'], 'delete_translation_event_' . $event_id ) );
	}

	/**
	 * The attend toggle URL includes the event ID.
	 */
	public function test_event_toggle_attendee() {
		$event_id = 42;
		$expected = "/glotpress/events/attend/$event_id";
		$this->assertEquals( $expected, Urls::event_toggle_attendee( $event_id ) );
	}

	/**
	 * The host toggle URL includes both the event ID and the user ID.
	 */
	public function test_event_toggle_host() {
		$user_id  = get_current_user_id();
		$event_id = 42;
		$expected = "/glotpress/events/host/$event_id/$user_id";
		$this->assertEquals( $expected, Urls::event_toggle_host( $event_id, $user_id ) );
	}

	/**
	 * The event create URL points at the new event page.
	 */
	public function test_event_create() {
		$expected = '/glotpress/events/new';
		$this->assertEquals( $expected, Urls::event_create() );
	}

	/**
	 * The my events URL points at the current user's events page.
	 */
	public function test_my_events() {
		$expected = '/glotpress/events/my-events';
		$this->assertEquals( $expected, Urls::my_events() );
	}

	/**
	 * This test must be last because once it runs, the GP_URL_BASE constant
	 * will be changed from the default ('/glotpress') to '/'.
	 */
	public function test_custom_gp_url_base() {
		define( 'GP_URL_BASE', '/' );
		$expected = '/events';
		$this->assertEquals( $expected, Urls::events_home() );
	}

	/**
	 * The event image URL is built from the public root and the event ID.
	 */
	public function test_event_image() {
		$event_id = $this->event_factory->create_active( $this->now );
		$expected = trailingslashit( gp_url_public_root() ) . "events/image/$event_id";
		$this->assertEquals( $expected, Urls::event_image( $event_id ) );
	}

	/**
	 * The default event image URL uses ID zero and is an absolute HTTP(S) URL.
	 */
	public function test_event_default_image() {
		$expected = trailingslashit( gp_url_public_root() ) . 'events/image/0';
		$this->assertEquals( $expected, Urls::event_default_image() );
		$this->assertTrue( $this->starts_with_http_or_https( Urls::event_default_image() ), 'URL does not start with http:// or https://' );
	}

	/**
	 * Check if a string starts with http:// or https://
	 *
	 * @param string $url The string to check.
	 *
	 * @return bool
	 */
	private function starts_with_http_or_https( string $url ): bool {
		return 1 === preg_match( '/^https?:\/\//', strtolower( $url ) );
	}
}
