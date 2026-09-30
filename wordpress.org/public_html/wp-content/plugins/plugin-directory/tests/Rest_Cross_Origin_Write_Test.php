<?php
/**
 * Tests that a cookie-authenticated write carries the directory's own origin.
 *
 * The login cookie is shared across the wordpress.org hosts, so the session does not
 * say which host a request came from; `Origin` does.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Covers {@see Base::reject_cross_origin_write()}.
 *
 * @group api
 */
#[Group( 'api' )]
class Rest_Cross_Origin_Write_Test extends TestCase {

	/**
	 * An origin that is not this site's.
	 *
	 * @var string
	 */
	const FOREIGN_ORIGIN = 'https://fr.wordpress.org';

	/**
	 * User IDs created by the running test, removed on tear down.
	 *
	 * @var int[]
	 */
	protected array $user_ids = array();

	/**
	 * Plugin post IDs created by the running test, removed on tear down.
	 *
	 * @var int[]
	 */
	protected array $post_ids = array();

	/**
	 * Counter to give every test plugin a unique slug.
	 *
	 * @var int
	 */
	private static int $plugin_count = 0;

	/**
	 * Signs in: the rule only concerns requests carrying a cookie session.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$user_id = wp_insert_user(
			array(
				'user_login' => 'cross-origin-fixture',
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => 'cross-origin-fixture@example.invalid',
				'role'       => 'administrator',
			)
		);

		$this->assertNotInstanceOf( WP_Error::class, $user_id, 'Could not create the user fixture.' );

		$this->user_ids[] = (int) $user_id;

		wp_set_current_user( (int) $user_id );
	}

	/**
	 * Removes every fixture the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		global $wp_rest_server;

		unset( $_SERVER['HTTP_ORIGIN'] );

		$wp_rest_server = null;

		wp_set_current_user( 0 );

		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}

		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		$this->user_ids = array();
		$this->post_ids = array();

		parent::tearDown();
	}

	/**
	 * Runs the filter for a request with the given method, route and origin.
	 *
	 * @param string      $method  The HTTP method.
	 * @param string      $route   The REST route.
	 * @param string|null $origin  The `Origin` header to send, or null to send none.
	 * @param array       $handler Optional. The matched route handler.
	 * @return mixed Null when the request may proceed, WP_Error when it may not.
	 */
	protected function dispatch( string $method, string $route, ?string $origin, array $handler = array() ) {
		if ( null === $origin ) {
			unset( $_SERVER['HTTP_ORIGIN'] );
		} else {
			$_SERVER['HTTP_ORIGIN'] = $origin;
		}

		$handler = array_merge( array( 'permission_callback' => '__return_false' ), $handler );

		return Base::reject_cross_origin_write( null, $handler, new WP_REST_Request( $method, $route ) );
	}

	/**
	 * This site's own origin.
	 *
	 * @return string The origin the directory's own pages are served from.
	 */
	protected function own_origin(): string {
		$parts = wp_parse_url( home_url() );

		return $parts['scheme'] . '://' . $parts['host'] . ( empty( $parts['port'] ) ? '' : ':' . $parts['port'] );
	}

	/**
	 * A write carrying a sibling host's origin is refused.
	 *
	 * @return void
	 */
	public function test_write_from_another_origin_is_rejected(): void {
		$result = $this->dispatch( 'POST', '/plugins/v1/plugin/jazz-hands/committers', self::FOREIGN_ORIGIN );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_cross_origin_write', $result->get_error_code() );
	}

	/**
	 * A DELETE from another origin is refused the same way.
	 *
	 * @return void
	 */
	public function test_delete_from_another_origin_is_rejected(): void {
		$this->assertInstanceOf(
			WP_Error::class,
			$this->dispatch( 'DELETE', '/plugins/v1/plugin/jazz-hands/committers/someone', self::FOREIGN_ORIGIN )
		);
	}

	/**
	 * The directory's own pages keep working.
	 *
	 * @return void
	 */
	public function test_write_from_own_origin_is_allowed(): void {
		$this->assertNull( $this->dispatch( 'POST', '/plugins/v1/plugin/jazz-hands/committers', $this->own_origin() ) );
	}

	/**
	 * A request with no `Origin` is left alone: there is nothing to judge.
	 *
	 * @return void
	 */
	public function test_write_without_an_origin_is_allowed(): void {
		$this->assertNull( $this->dispatch( 'POST', '/plugins/v1/plugin/jazz-hands/committers', null ) );
	}

	/**
	 * Reads are untouched, cross-origin or not.
	 *
	 * @return void
	 */
	public function test_read_from_another_origin_is_allowed(): void {
		$this->assertNull( $this->dispatch( 'GET', '/plugins/v1/plugin/jazz-hands', self::FOREIGN_ORIGIN ) );
	}

	/**
	 * Other namespaces are not this rule's business.
	 *
	 * @return void
	 */
	public function test_other_namespaces_are_untouched(): void {
		$this->assertNull( $this->dispatch( 'POST', '/wp/v2/posts', self::FOREIGN_ORIGIN ) );
	}

	/**
	 * A bearer-authenticated caller holds no cookie session, so it is left alone.
	 *
	 * @return void
	 */
	public function test_request_without_a_session_is_allowed(): void {
		wp_set_current_user( 0 );

		$this->assertNull(
			$this->dispatch( 'POST', '/plugins/v1/plugin/jazz-hands/gandalf-scan', self::FOREIGN_ORIGIN )
		);
	}

	/**
	 * A route that gates on nothing is not authorized by the session either.
	 *
	 * @return void
	 */
	public function test_public_route_is_allowed(): void {
		$this->assertNull(
			$this->dispatch(
				'POST',
				'/plugins/v1/plugin/jazz-hands/blueprint.json',
				self::FOREIGN_ORIGIN,
				array( 'permission_callback' => '__return_true' )
			)
		);
	}

	/**
	 * A namespace in another case is the same namespace.
	 *
	 * @return void
	 */
	public function test_mixed_case_namespace_is_rejected(): void {
		$result = $this->dispatch( 'POST', '/PLUGINS/v1/plugin/jazz-hands/committers', self::FOREIGN_ORIGIN );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_cross_origin_write', $result->get_error_code() );
	}

	/**
	 * The rule holds through the REST server, not only when called directly.
	 *
	 * Core matches a mixed-case path to the same handler, so anything but
	 * `rest_no_route` proves the request reached this namespace. The plugin has to
	 * exist: args are validated before the filter runs, and a 400 would mask the result.
	 *
	 * @return void
	 */
	public function test_mixed_case_namespace_is_rejected_through_the_dispatcher(): void {
		global $wp_rest_server;

		$slug = $this->create_plugin();

		// A fresh server fires rest_api_init, which registers both routes and the filter.
		$wp_rest_server = null;
		rest_get_server();

		$_SERVER['HTTP_ORIGIN'] = self::FOREIGN_ORIGIN;

		$response = rest_do_request( new WP_REST_Request( 'POST', '/PLUGINS/v1/plugin/' . $slug . '/favorite' ) );

		$this->assertSame( WP_Http::FORBIDDEN, $response->get_status() );
		$this->assertSame( 'rest_cross_origin_write', $response->as_error()->get_error_code() );
	}

	/**
	 * Creates a published plugin and registers it for removal on tear down.
	 *
	 * @return string The new plugin's slug.
	 */
	protected function create_plugin(): string {
		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'cross-origin-fixture-' . ( ++self::$plugin_count ),
				'post_title'  => 'Cross Origin Test Plugin',
				'post_status' => 'publish',
			)
		);

		$this->assertInstanceOf( WP_Post::class, $plugin );

		$this->post_ids[] = $plugin->ID;

		return $plugin->post_name;
	}

	/**
	 * A response another filter already produced is passed through untouched.
	 *
	 * @return void
	 */
	public function test_an_existing_result_is_left_alone(): void {
		$_SERVER['HTTP_ORIGIN'] = self::FOREIGN_ORIGIN;

		$existing = new WP_REST_Response( array( 'handled' => true ) );
		$request  = new WP_REST_Request( 'POST', '/plugins/v1/plugin/jazz-hands/committers' );
		$handler  = array( 'permission_callback' => '__return_false' );

		$this->assertSame( $existing, Base::reject_cross_origin_write( $existing, $handler, $request ) );
	}
}
