<?php
/**
 * Tests that the event list routes accept any page number from the query string.
 *
 * @package wporg-gp-translation-events
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Routes\Event\List_Route;
use Wporg\TranslationEvents\Routes\Event\List_Trashed_Route;
use Wporg\TranslationEvents\Routes\User\My_Events_Route;

/**
 * A page number below 1 is treated as page 1 instead of reaching the repository,
 * which throws on it.
 */
class Pagination_Params_Test extends TestCase {
	/**
	 * Query parameters set by the current test.
	 *
	 * @var string[]
	 */
	private array $params = array();

	/**
	 * Cleans up after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		foreach ( $this->params as $param ) {
			unset( $_GET[ $param ] );
		}
		remove_filter( 'gp_translation_events_can_crud_event', '__return_true' );
		parent::tearDown();
	}

	/**
	 * Routes, the query parameter each reads its page from, and the page numbers to try.
	 *
	 * @return array<string, array{0: class-string, 1: string, 2: string}>
	 */
	public static function data_page_params(): array {
		$routes = array(
			'current events'        => array( List_Route::class, 'current_events_paged' ),
			'upcoming events'       => array( List_Route::class, 'upcoming_events_paged' ),
			'past events'           => array( List_Route::class, 'past_events_paged' ),
			'user attending events' => array( List_Route::class, 'user_attending_events_paged' ),
			'my events'             => array( My_Events_Route::class, 'page' ),
			'trashed events'        => array( List_Trashed_Route::class, 'page' ),
		);

		$cases = array();
		foreach ( $routes as $name => $route ) {
			foreach ( array( '0', '-1' ) as $page ) {
				$cases[ "$name, page $page" ] = array( $route[0], $route[1], $page );
			}
		}
		return $cases;
	}

	/**
	 * The route renders its page instead of throwing.
	 *
	 * @param string $route_class Route to render.
	 * @param string $param       Query parameter holding the page number.
	 * @param string $page        Page number to request.
	 * @return void
	 */
	#[DataProvider( 'data_page_params' )]
	public function test_page_below_one_renders( string $route_class, string $param, string $page ): void {
		$this->set_normal_user_as_current();
		add_filter( 'gp_translation_events_can_crud_event', '__return_true' );

		$this->params[] = $param;
		$_GET[ $param ] = $page;

		$route               = new $route_class();
		$route->fake_request = true;

		ob_start();
		try {
			$route->handle();
		} catch ( GP_Route_Exit_Exception $e ) {
			$route->exited = true;
		}
		$output = (string) ob_get_clean();

		$this->assertFalse( $route->exited );
		$this->assertNotSame( '', $output );
	}
}
