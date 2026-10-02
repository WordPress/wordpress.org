<?php
/**
 * Tests that a privileged route requires a nonce minted for that exact action.
 *
 * Core's cookie authentication claims `_wpnonce` for the generic `wp_rest` action,
 * so these routes carry a second token that names the action and its subject.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Covers {@see Base::verify_action_nonce()}, the tokens the routes mint for it, and the
 * capability and action each route declares for {@see Base::permission_check_action()}.
 *
 * @group api
 */
#[Group( 'api' )]
class Rest_Action_Nonce_Test extends TestCase {

	/**
	 * A route registered with a declared capability and action.
	 *
	 * @var string
	 */
	const SELF_CLOSE_ROUTE = '/plugins/v1/plugin/(?P<plugin_slug>[^/]+)/self-close';

	/**
	 * The route helper under test.
	 *
	 * @var Base
	 */
	protected Base $base;

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
	 * Distinguishes the plugins a single test run creates.
	 *
	 * @var int
	 */
	protected static int $plugin_count = 0;

	/**
	 * Signs in, because a nonce is bound to the user it was minted for.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->base = new Base();

		wp_set_current_user( $this->create_user( 'nonce-fixture-one' ) );
	}

	/**
	 * Creates an administrator and registers it for removal on tear down.
	 *
	 * @param string $user_login Login for the new account.
	 * @return int User ID.
	 */
	protected function create_user( string $user_login ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => $user_login,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $user_login . '@example.invalid',
				'role'       => 'administrator',
			)
		);

		$this->assertNotInstanceOf( WP_Error::class, $user_id, "Could not create the user '{$user_login}'." );

		$this->user_ids[] = (int) $user_id;

		return (int) $user_id;
	}

	/**
	 * Removes every fixture the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );

		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}

		$this->user_ids = array();

		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		$this->post_ids = array();

		parent::tearDown();
	}

	/**
	 * Creates a published plugin and registers it for removal on tear down.
	 *
	 * @return string The new plugin's slug.
	 */
	protected function create_plugin(): string {
		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'action-nonce-fixture-' . ( ++self::$plugin_count ),
				'post_title'  => 'Action Nonce Test Plugin',
				'post_status' => 'publish',
			)
		);

		$this->assertInstanceOf( WP_Post::class, $plugin );

		$this->post_ids[] = $plugin->ID;

		return $plugin->post_name;
	}

	/**
	 * The handler the REST server registered for a route and method.
	 *
	 * @param string $route  The registered route pattern.
	 * @param string $method The HTTP method the handler serves.
	 * @return array The handler, as the dispatcher hands it to a permission callback.
	 */
	protected function registered_handler( string $route, string $method ): array {
		$routes = rest_get_server()->get_routes( 'plugins/v1' );

		$this->assertArrayHasKey( $route, $routes, "The route '{$route}' is not registered." );

		foreach ( $routes[ $route ] as $handler ) {
			if ( ! empty( $handler['methods'][ $method ] ) ) {
				return $handler;
			}
		}

		$this->fail( "No '{$method}' handler is registered for '{$route}'." );
	}

	/**
	 * Runs a route's registered permission callback against a request for a plugin.
	 *
	 * The dispatcher sets the route's attributes on the request before the callback
	 * runs, which is where the capability and action it checks are declared.
	 *
	 * @param array  $handler The registered route handler.
	 * @param string $slug    The plugin the request is for.
	 * @param string $nonce   The action nonce the request carries.
	 * @return bool|WP_Error What the permission callback returned.
	 */
	protected function check_permission( array $handler, string $slug, string $nonce ) {
		$request = new WP_REST_Request( 'POST', '/plugins/v1/plugin/' . $slug . '/self-close' );

		$request->set_url_params( array( 'plugin_slug' => $slug ) );
		$request->set_param( Base::ACTION_NONCE_PARAM, $nonce );
		$request->set_attributes( $handler );

		return call_user_func( $handler['permission_callback'], $request );
	}

	/**
	 * Builds a request carrying the given action nonce.
	 *
	 * @param string|null $nonce The nonce to send, or null to send none.
	 * @return WP_REST_Request The request to check.
	 */
	protected function request( ?string $nonce ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/plugins/v1/plugin/jazz-hands/committers' );

		if ( null !== $nonce ) {
			$request->set_param( Base::ACTION_NONCE_PARAM, $nonce );
		}

		return $request;
	}

	/**
	 * The nonce the route mints for the action is the one it accepts.
	 *
	 * @return void
	 */
	public function test_matching_action_nonce_is_accepted(): void {
		$request = $this->request( Base::action_nonce( 'add_committer', 'jazz-hands' ) );

		$this->assertTrue( $this->base->verify_action_nonce( $request, 'add_committer', 'jazz-hands' ) );
	}

	/**
	 * The generic `wp_rest` token does not stand in for a route's own.
	 *
	 * @return void
	 */
	public function test_generic_rest_nonce_is_rejected(): void {
		$request = $this->request( wp_create_nonce( 'wp_rest' ) );
		$result  = $this->base->verify_action_nonce( $request, 'add_committer', 'jazz-hands' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_cookie_invalid_nonce', $result->get_error_code() );
	}

	/**
	 * A nonce for one plugin does not carry over to another.
	 *
	 * @return void
	 */
	public function test_nonce_for_another_plugin_is_rejected(): void {
		$request = $this->request( Base::action_nonce( 'add_committer', 'some-other-plugin' ) );

		$this->assertInstanceOf( WP_Error::class, $this->base->verify_action_nonce( $request, 'add_committer', 'jazz-hands' ) );
	}

	/**
	 * A nonce for one action does not carry over to another on the same plugin.
	 *
	 * @return void
	 */
	public function test_nonce_for_another_action_is_rejected(): void {
		$request = $this->request( Base::action_nonce( 'self_close', 'jazz-hands' ) );

		$this->assertInstanceOf( WP_Error::class, $this->base->verify_action_nonce( $request, 'add_committer', 'jazz-hands' ) );
	}

	/**
	 * A request that carries no action nonce at all is refused.
	 *
	 * @return void
	 */
	public function test_missing_action_nonce_is_rejected(): void {
		$this->assertInstanceOf( WP_Error::class, $this->base->verify_action_nonce( $this->request( null ), 'add_committer', 'jazz-hands' ) );
	}

	/**
	 * A nonce minted for one user does not authorize another user's session.
	 *
	 * @return void
	 */
	public function test_nonce_is_bound_to_the_user_it_was_minted_for(): void {
		$request = $this->request( Base::action_nonce( 'add_committer', 'jazz-hands' ) );

		wp_set_current_user( $this->create_user( 'nonce-fixture-two' ) );

		$this->assertInstanceOf( WP_Error::class, $this->base->verify_action_nonce( $request, 'add_committer', 'jazz-hands' ) );
	}

	/**
	 * A route names the capability and action its shared permission callback reads.
	 *
	 * @return void
	 */
	public function test_route_declares_its_capability_and_action(): void {
		$handler = $this->registered_handler( self::SELF_CLOSE_ROUTE, 'POST' );

		$this->assertInstanceOf( Base::class, $handler['permission_callback'][0] );
		$this->assertSame( 'permission_check_action', $handler['permission_callback'][1] );
		$this->assertSame( 'plugin_self_close', $handler['wporg_capability'] );
		$this->assertSame( 'self_close', $handler['wporg_action'] );
	}

	/**
	 * The action the route declares is the one the check requires a token for.
	 *
	 * @return void
	 */
	public function test_declared_action_is_the_one_the_route_verifies(): void {
		$slug   = $this->create_plugin();
		$result = $this->check_permission(
			$this->registered_handler( self::SELF_CLOSE_ROUTE, 'POST' ),
			$slug,
			Base::action_nonce( 'self_transfer', $slug )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'rest_cookie_invalid_nonce', $result->get_error_code() );
	}

	/**
	 * A route that declares nothing is refused rather than checked against nothing.
	 *
	 * @return void
	 */
	public function test_route_without_a_declaration_is_refused(): void {
		$handler = $this->registered_handler( self::SELF_CLOSE_ROUTE, 'POST' );
		$slug    = $this->create_plugin();

		unset( $handler['wporg_capability'], $handler['wporg_action'] );

		$this->assertFalse( $this->check_permission( $handler, $slug, Base::action_nonce( 'self_close', $slug ) ) );
	}

	/**
	 * A token minted for the declared action passes the same check.
	 *
	 * @return void
	 */
	public function test_declared_action_nonce_is_accepted_by_the_route(): void {
		$slug = $this->create_plugin();

		$this->assertTrue(
			$this->check_permission(
				$this->registered_handler( self::SELF_CLOSE_ROUTE, 'POST' ),
				$slug,
				Base::action_nonce( 'self_close', $slug )
			)
		);
	}
}
