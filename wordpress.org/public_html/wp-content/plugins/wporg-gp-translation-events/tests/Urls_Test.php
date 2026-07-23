<?php

use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository;
use Wporg\TranslationEvents\Tests\Event_Factory;
use Wporg\TranslationEvents\Urls;
use Wporg\Tests\Base_Test as TestCase;

class Urls_Test extends TestCase {
	private Event_Factory $event_factory;
	private Event_Repository $event_repository;

	public function setUp(): void {
		parent::setUp();
		$this->event_factory    = new Event_Factory();
		$this->event_repository = new Event_Repository( $this->now, new Attendee_Repository() );

		$this->set_normal_user_as_current();
	}

	public function test_events_home() {
		$expected = '/glotpress/events';
		$this->assertEquals( $expected, Urls::events_home() );
	}

	public function test_event_details() {
		$event_id = $this->event_factory->create_active( $this->now );
		$event    = $this->event_repository->get_event( $event_id );

		$expected = "/glotpress/events/{$event->slug()}";
		$this->assertEquals( $expected, Urls::event_details( $event_id ) );
	}

	public function test_event_details_draft() {
		$event_id          = $this->event_factory->create_active( $this->now );
		$post              = get_post( $event_id );
		$post->post_status = 'draft';
		wp_update_post( $post );

		$event = $this->event_repository->get_event( $event_id );

		$expected = "/glotpress/events/{$event->slug()}";
		$this->assertEquals( $expected, Urls::event_details( $event_id ) );
	}

	public function test_event_details_absolute() {
		$event_id = $this->event_factory->create_active( $this->now );
		$event    = $this->event_repository->get_event( $event_id );

		$expected = site_url() . "/glotpress/events/{$event->slug()}";
		$this->assertEquals( $expected, Urls::event_details_absolute( $event_id ) );
	}

	public function test_event_translations() {
		$event_id = $this->event_factory->create_active( $this->now );
		$event    = $this->event_repository->get_event( $event_id );

		$expected = "/glotpress/events/{$event->slug()}/translations/pt";
		$this->assertEquals( $expected, Urls::event_translations( $event_id, 'pt' ) );

		$expected = "/glotpress/events/{$event->slug()}/translations/pt/waiting";
		$this->assertEquals( $expected, Urls::event_translations( $event_id, 'pt', 'waiting' ) );
	}

	public function test_event_edit() {
		$event_id = 42;
		$expected = "/glotpress/events/edit/$event_id";
		$this->assertEquals( $expected, Urls::event_edit( $event_id ) );
	}

	public function test_event_trash() {
		$event_id = 42;
		$url      = Urls::event_trash( $event_id );
		$this->assertStringStartsWith( "/glotpress/events/trash/$event_id?_wpnonce=", $url );
		$nonce = wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $nonce, $query );
		$this->assertNotFalse( wp_verify_nonce( $query['_wpnonce'], 'trash_translation_event_' . $event_id ) );
	}

	public function test_event_delete() {
		$event_id = 42;
		$url      = Urls::event_delete( $event_id );
		$this->assertStringStartsWith( "/glotpress/events/delete/$event_id?_wpnonce=", $url );
		$nonce = wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $nonce, $query );
		$this->assertNotFalse( wp_verify_nonce( $query['_wpnonce'], 'delete_translation_event_' . $event_id ) );
	}

	public function test_event_toggle_attendee() {
		$event_id = 42;
		$expected = "/glotpress/events/attend/$event_id";
		$this->assertEquals( $expected, Urls::event_toggle_attendee( $event_id ) );
	}

	public function test_event_toggle_host() {
		$user_id  = get_current_user_id();
		$event_id = 42;
		$expected = "/glotpress/events/host/$event_id/$user_id";
		$this->assertEquals( $expected, Urls::event_toggle_host( $event_id, $user_id ) );
	}

	public function test_event_create() {
		$expected = '/glotpress/events/new';
		$this->assertEquals( $expected, Urls::event_create() );
	}

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

	public function test_event_image() {
		$event_id = $this->event_factory->create_active( $this->now );
		$expected = trailingslashit( gp_url_public_root() ) . "events/image/$event_id";
		$this->assertEquals( $expected, Urls::event_image( $event_id ) );
	}

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
