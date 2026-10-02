<?php
/**
 * Tests for keeping modules as deploys left them.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use App\User;
use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the Modules page actions that are refused.
 */
final class LockModulesTest extends TestCase {

	/**
	 * Registers the module, and logs in an administrator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSiteServiceProvider::class );
		$this->actingAs( $this->create_user() );
	}

	/**
	 * Updating and deleting modules are refused, with a message the page shows.
	 *
	 * @return void
	 */
	public function test_refuses_updates_and_deletes(): void {
		foreach ( array( 'delete', 'update', 'update_all' ) as $action ) {
			$this->post_action( $action )
				->assertStatus( 200 )
				->assertExactJson(
					array(
						'status' => 'error',
						'msg'    => 'Modules are updated and removed on the server, not from this page.',
					)
				);
		}
	}

	/**
	 * Other actions reach FreeScout.
	 *
	 * @return void
	 */
	public function test_lets_other_actions_through(): void {
		$this->assertNotSame( 'Modules are updated and removed on the server, not from this page.', $this->post_action( 'unknown' )->json( 'msg' ) );
	}

	/**
	 * Anyone FreeScout would turn away is still turned away, rather than answered.
	 *
	 * @return void
	 */
	public function test_leaves_others_to_freescout(): void {
		$this->actingAs( $this->create_user( User::ROLE_USER ) );
		$this->post_action( 'delete' )->assertStatus( 403 );

		auth()->logout();
		$this->post_action( 'delete' )->assertRedirect( route( 'login' ) );
	}

	/**
	 * Posts a Modules page action, for a module that doesn't exist, in case one gets through.
	 *
	 * @param string $action Action.
	 * @return \Illuminate\Foundation\Testing\TestResponse
	 */
	private function post_action( string $action ): \Illuminate\Foundation\Testing\TestResponse {
		return $this->post(
			route( 'modules.ajax' ),
			array(
				'_token' => csrf_token(),
				'action' => $action,
				'alias'  => 'wporgsite-missing',
			)
		);
	}
}
