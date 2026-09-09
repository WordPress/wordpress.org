<?php
/**
 * Tests that trashing, restoring, and deleting an event leave a notice
 * behind for the page the user is redirected to.
 *
 * @package wporg-gp-translation-events
 */

declare( strict_types = 1 );

use Wporg\Tests\Base_Test;
use Wporg\TranslationEvents\Event\Event_Form_Handler;
use Wporg\TranslationEvents\Routes\Event\Delete_Route;
use Wporg\TranslationEvents\Routes\Event\Trash_Route;
use Wporg\TranslationEvents\Tests\Event_Factory;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Trashing, restoring, and permanently deleting an event all redirect away from
 * the page the action was triggered from. Each one should leave a GlotPress
 * notice behind so the user is told what happened after the redirect, rather
 * than landing on a new page with no feedback (see issue #372).
 *
 * GlotPress notices are carried across the redirect in `_gp_notice_<key>`
 * cookies. These tests capture those cookies through the `gp_set_cookie`
 * filter, which also suppresses the real `setcookie()` call so it does not
 * warn about headers already sent under PHPUnit.
 */
class Event_Notices_Test extends Base_Test {
	/**
	 * The notice messages captured from the `gp_set_cookie` filter, keyed by
	 * the GlotPress notice key ('notice' or 'error').
	 *
	 * @var array<string, string>
	 */
	private array $notices = array();

	/**
	 * The `gp_set_cookie` filter callback that captures notice cookies.
	 *
	 * @var callable
	 */
	private $cookie_capture;

	/**
	 * The `wp_redirect` filter callback that stops redirect headers under PHPUnit.
	 *
	 * @var callable
	 */
	private $redirect_guard;

	/**
	 * Registers the cookie capture and redirect guard before each test.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Any logged-in user can manage events, so the capability checks pass
		// and the redirect path under test is reached.
		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );

		$this->cookie_capture = function ( $args ) {
			// GlotPress stores notices in `_gp_notice_<key>` cookies; capture
			// the message under its key so both 'notice' and 'error' are seen.
			if ( isset( $args[0], $args[1] ) && 0 === strpos( (string) $args[0], '_gp_notice_' ) ) {
				$key                   = substr( (string) $args[0], strlen( '_gp_notice_' ) );
				$this->notices[ $key ] = $args[1];
			}

			// Returning false stops GlotPress from actually calling setcookie().
			return false;
		};
		add_filter( 'gp_set_cookie', $this->cookie_capture );

		// Stop the redirect from emitting a header under PHPUnit.
		$this->redirect_guard = '__return_false';
		add_filter( 'wp_redirect', $this->redirect_guard );
	}

	/**
	 * Removes the filters registered in setUp().
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_filter( 'wp_redirect', $this->redirect_guard );
		remove_filter( 'gp_set_cookie', $this->cookie_capture );
		remove_filter( 'gp_translation_events_can_crud_event', '__return_true' );
		unset( $_GET['_wpnonce'] );

		parent::tearDown();
	}

	/**
	 * Trashing an event through the trash route sets a success notice.
	 *
	 * @return void
	 */
	public function test_trashing_an_event_sets_a_notice() {
		$event_id = ( new Event_Factory() )->create_active( $this->now );

		$this->run_route( Trash_Route::class, $event_id, 'trash_translation_event_' . $event_id );

		$this->assertSame( 'Event moved to the trash.', $this->notices['notice'] ?? null );
	}

	/**
	 * Restoring a trashed event through the trash route sets a success notice.
	 *
	 * @return void
	 */
	public function test_restoring_an_event_sets_a_notice() {
		$factory  = new Event_Factory();
		$event_id = $factory->create_active( $this->now );
		Translation_Events::get_event_repository()->trash_event(
			Translation_Events::get_event_repository()->get_event( $event_id )
		);

		// The trash route toggles: a trashed event is restored.
		$this->run_route( Trash_Route::class, $event_id, 'trash_translation_event_' . $event_id );

		$this->assertSame( 'Event restored.', $this->notices['notice'] ?? null );
	}

	/**
	 * Permanently deleting a trashed event sets a success notice.
	 *
	 * @return void
	 */
	public function test_permanently_deleting_an_event_sets_a_notice() {
		$event_id = ( new Event_Factory() )->create_active( $this->now );
		Translation_Events::get_event_repository()->trash_event(
			Translation_Events::get_event_repository()->get_event( $event_id )
		);

		$this->run_route( Delete_Route::class, $event_id, 'delete_translation_event_' . $event_id );

		$this->assertSame( 'Event permanently deleted.', $this->notices['notice'] ?? null );
	}

	/**
	 * The "Delete event" button on the edit form trashes over AJAX and then
	 * redirects the browser to My Events, so the same notice must be set there.
	 *
	 * @return void
	 */
	public function test_trashing_via_the_form_handler_sets_a_notice() {
		$event_id = ( new Event_Factory() )->create_active( $this->now );
		wp_set_current_user( $this->factory->user->create() );

		$form_data = array(
			'form_name'    => 'trash_event',
			'event_id'     => (string) $event_id,
			'_event_nonce' => wp_create_nonce( '_event_nonce' ),
		);

		// The handler ends in wp_send_json_success(), which calls wp_die().
		// Route that through the AJAX die handler and make it throw so the
		// notice set just before it can be asserted.
		add_filter( 'wp_doing_ajax', '__return_true' );
		$die_handler = function () {
			return function () {
				throw new Exception( 'wp_send_json' );
			};
		};
		add_filter( 'wp_die_ajax_handler', $die_handler );

		$handler = new Event_Form_Handler( $this->now, Translation_Events::get_event_repository() );

		ob_start();
		try {
			$handler->handle( $form_data );
		} catch ( Exception $e ) {
			// Expected: wp_send_json_success() reached wp_die().
			$this->assertSame( 'wp_send_json', $e->getMessage() );
		} finally {
			ob_end_clean();
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			remove_filter( 'wp_doing_ajax', '__return_true' );
		}

		$this->assertSame( 'Event moved to the trash.', $this->notices['notice'] ?? null );
	}

	/**
	 * When the trash fails, the user is still redirected to My Events, so the
	 * failure must be surfaced there as an error notice.
	 *
	 * @return void
	 */
	public function test_a_failed_trash_via_the_form_handler_sets_an_error_notice() {
		$event_id = ( new Event_Factory() )->create_active( $this->now );
		wp_set_current_user( $this->factory->user->create() );

		$form_data = array(
			'form_name'    => 'trash_event',
			'event_id'     => (string) $event_id,
			'_event_nonce' => wp_create_nonce( '_event_nonce' ),
		);

		// Force the trash to fail so the error branch is exercised.
		add_filter( 'pre_trash_post', '__return_false' );

		add_filter( 'wp_doing_ajax', '__return_true' );
		$die_handler = function () {
			return function () {
				throw new Exception( 'wp_send_json' );
			};
		};
		add_filter( 'wp_die_ajax_handler', $die_handler );

		$handler = new Event_Form_Handler( $this->now, Translation_Events::get_event_repository() );

		ob_start();
		try {
			$handler->handle( $form_data );
		} catch ( Exception $e ) {
			$this->assertSame( 'wp_send_json', $e->getMessage() );
		} finally {
			ob_end_clean();
			remove_filter( 'wp_die_ajax_handler', $die_handler );
			remove_filter( 'wp_doing_ajax', '__return_true' );
			remove_filter( 'pre_trash_post', '__return_false' );
		}

		$this->assertSame( 'Failed to delete event.', $this->notices['error'] ?? null );
		$this->assertArrayNotHasKey( 'notice', $this->notices );
	}

	/**
	 * Run a route to completion with a valid nonce as a logged-in user.
	 *
	 * @param string $route_class  Route to exercise.
	 * @param int    $event_id     Event the route acts on.
	 * @param string $nonce_action Nonce action the route verifies.
	 * @return void
	 */
	private function run_route( string $route_class, int $event_id, string $nonce_action ) {
		wp_set_current_user( $this->factory->user->create() );
		$_GET['_wpnonce'] = wp_create_nonce( $nonce_action );

		$route               = new $route_class();
		$route->fake_request = true;

		// The route renders a redirect template on success, which would
		// otherwise garble PHPUnit's output. On success it also calls exit_(),
		// which throws under a faked request to halt the way a real exit would.
		ob_start();
		try {
			$route->handle( $event_id );
		} catch ( GP_Route_Exit_Exception $e ) {
			// Expected: the route reached its exit after redirecting.
			$this->assertInstanceOf( GP_Route_Exit_Exception::class, $e );
		} finally {
			ob_end_clean();
		}
	}
}
