<?php
/**
 * Tests that state-changing routes reject missing and forged nonces.
 *
 * @package wporg-gp-translation-events
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Routes\Attendee\Remove_Attendee_Route;
use Wporg\TranslationEvents\Routes\Event\Delete_Route;
use Wporg\TranslationEvents\Routes\Event\Trash_Route;
use Wporg\TranslationEvents\Routes\User\Attend_Event_Route;
use Wporg\TranslationEvents\Routes\User\Attendance_Mode_Route;
use Wporg\TranslationEvents\Routes\User\Host_Event_Route;
use Wporg\TranslationEvents\Tests\Event_Factory;

/**
 * Every state-changing route must refuse a request whose nonce is missing or forged.
 *
 * These handlers report failure through die_with_error(). Under GP_Route::$fake_request,
 * GlotPress 4.1 halts the request by throwing GP_Route_Exit_Exception, while older versions
 * fall through, so each guard is followed by an explicit `return;`. Asserting only on the
 * 403 would pass even if that return were removed and the destructive work ran anyway, so
 * every case also asserts that no redirect was issued — reaching the redirect means the
 * handler ran to completion.
 */
class Nonce_Guards_Test extends TestCase {
	/**
	 * Records the redirect target, and suppresses the header() call that would
	 * otherwise error out under PHPUnit.
	 *
	 * @var callable
	 */
	private $redirect_guard;

	/**
	 * Where the handler tried to redirect, or null if it never got that far.
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

		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );

		$this->redirect_guard = function ( $location ) {
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
		remove_filter( 'gp_translation_events_can_crud_event', '__return_true' );
		unset( $_GET['_wpnonce'], $_POST['_wpnonce'], $_POST['_attendee_nonce'] );

		parent::tearDown();
	}

	/**
	 * Route class, the nonce field it reads, which superglobal carries it, and whether
	 * its handle() takes a user id in addition to the event id.
	 *
	 * @return array
	 */
	public static function guarded_routes(): array {
		return array(
			'trash'           => array( Trash_Route::class, '_wpnonce', 'GET', false ),
			'delete'          => array( Delete_Route::class, '_wpnonce', 'GET', false ),
			'remove attendee' => array( Remove_Attendee_Route::class, '_wpnonce', 'GET', true ),
			'attendance mode' => array( Attendance_Mode_Route::class, '_wpnonce', 'GET', true ),
			'host'            => array( Host_Event_Route::class, '_wpnonce', 'POST', true ),
			'attend'          => array( Attend_Event_Route::class, '_attendee_nonce', 'POST', false ),
		);
	}

	/**
	 * A request without a nonce is refused before any work is done.
	 *
	 * @param string $route_class Route to exercise.
	 * @param string $field       Nonce field the route reads.
	 * @param string $method      Superglobal carrying the field.
	 * @param bool   $needs_user  Whether handle() takes a user id.
	 * @return void
	 */
	#[DataProvider( 'guarded_routes' )]
	public function test_rejects_missing_nonce( string $route_class, string $field, string $method, bool $needs_user ) {
		$this->assert_route_rejects( $route_class, $field, $method, $needs_user, null );
	}

	/**
	 * A request with a forged nonce is refused before any work is done.
	 *
	 * @param string $route_class Route to exercise.
	 * @param string $field       Nonce field the route reads.
	 * @param string $method      Superglobal carrying the field.
	 * @param bool   $needs_user  Whether handle() takes a user id.
	 * @return void
	 */
	#[DataProvider( 'guarded_routes' )]
	public function test_rejects_forged_nonce( string $route_class, string $field, string $method, bool $needs_user ) {
		$this->assert_route_rejects( $route_class, $field, $method, $needs_user, 'not-a-real-nonce' );
	}

	/**
	 * Run a route with a bad nonce and assert it refused before doing any work.
	 *
	 * @param string      $route_class Route to exercise.
	 * @param string      $field       Nonce field the route reads.
	 * @param string      $method      Superglobal carrying the field.
	 * @param bool        $needs_user  Whether handle() takes a user id.
	 * @param string|null $nonce       Nonce to send, or null to send none at all.
	 * @return void
	 */
	private function assert_route_rejects( string $route_class, string $field, string $method, bool $needs_user, ?string $nonce ) {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$event_id = ( new Event_Factory() )->create_active( $this->now );

		if ( Delete_Route::class === $route_class ) {
			wp_trash_post( $event_id );
		}
		unset( $_GET[ $field ], $_POST[ $field ] );
		if ( null !== $nonce ) {
			if ( 'GET' === $method ) {
				$_GET[ $field ] = $nonce;
			} else {
				$_POST[ $field ] = $nonce;
			}
		}

		$route               = new $route_class();
		$route->fake_request = true;

		/*
		 * The handler renders an error page on failure, which would otherwise
		 * garble PHPUnit's output.
		 */
		ob_start();
		try {
			if ( $needs_user ) {
				$route->handle( $event_id, $user_id );
			} else {
				$route->handle( $event_id );
			}
		} catch ( GP_Route_Exit_Exception $e ) {
			$route->exited = true;
		} finally {
			ob_end_clean();
		}

		$this->assertSame( 403, $route->http_status );
		$this->assertTrue( $route->exited );
		$this->assertNull( $this->redirect_to, 'Handler continued past the nonce guard.' );
	}
}
