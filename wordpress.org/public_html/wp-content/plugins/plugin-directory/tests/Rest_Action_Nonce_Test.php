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

/**
 * Covers {@see Base::verify_action_nonce()} and the tokens the routes mint for it.
 *
 * @group api
 */
#[Group( 'api' )]
class Rest_Action_Nonce_Test extends TestCase {

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

		parent::tearDown();
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
}
