<?php
/**
 * Tests for keeping administrators to the proxy.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Tests;

use App\User;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider;

require_once __DIR__ . '/SsoTestCase.php';

/**
 * Covers logging administrators out of requests that don't come through the proxy.
 */
final class ProxyTest extends SsoTestCase {

	/**
	 * Administrator connected to the "rita" account.
	 *
	 * @var User
	 */
	private $admin;

	/**
	 * Requires the proxy, and creates a connected administrator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		config( array( 'wporgsso.require_proxy' => true ) );

		$this->admin = $this->create_user( User::ROLE_ADMIN );
		Account::connect( (int) $this->admin->id, 'rita' );
	}

	/**
	 * Marks the following requests as proxied, or not, like nginx does.
	 *
	 * @param string $proxied The param's value.
	 * @return $this
	 */
	private function proxied( string $proxied ): self {
		return $this->withServerVariables( array( WPOrgSSOServiceProvider::SERVER_PROXIED_REQUEST => $proxied ) );
	}

	/**
	 * A proxied administrator stays logged in.
	 *
	 * @return void
	 */
	public function test_keeps_proxied_administrators(): void {
		$this->proxied( '1' )->actingAs( $this->admin )->get( route( 'dashboard' ) )->assertStatus( 200 );

		$this->assertAuthenticatedAs( $this->admin );
	}

	/**
	 * An administrator outside the proxy is logged out, and told why.
	 *
	 * @return void
	 */
	public function test_logs_out_administrators_outside_the_proxy(): void {
		$this->proxied( '0' )->actingAs( $this->admin )->get( route( 'dashboard' ) )->assertRedirect( route( 'login' ) );

		$this->assertGuest();
		$this->assertSame( 'Administrators can only use the helpdesk through the proxy.', session( WPOrgSSOServiceProvider::SESSION_ERROR ) );
	}

	/**
	 * Logging in through WordPress.org from outside the proxy doesn't get an administrator in either.
	 *
	 * @return void
	 */
	public function test_logs_out_administrators_after_logging_in_outside_the_proxy(): void {
		$this->proxied( '0' );
		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) )->assertRedirect( route( 'dashboard' ) );

		$this->get( route( 'dashboard' ) )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * Polls from outside the proxy get a 401, like other ended sessions.
	 *
	 * @return void
	 */
	public function test_answers_polls_outside_the_proxy_with_a_401(): void {
		$this->proxied( '0' )->actingAs( $this->admin )->getJson( route( 'dashboard' ) )->assertStatus( 401 );
	}

	/**
	 * Without the param, nobody counts as proxied.
	 *
	 * @return void
	 */
	public function test_logs_out_administrators_without_the_param(): void {
		$this->actingAs( $this->admin )->get( route( 'dashboard' ) )->assertRedirect( route( 'login' ) );

		$this->assertGuest();
	}

	/**
	 * A header of the same name doesn't count: only nginx sets the param.
	 *
	 * @return void
	 */
	public function test_ignores_the_header(): void {
		$this->proxied( '0' )
			->withHeaders( array( 'WPORG-PROXIED-REQUEST' => '1' ) )
			->actingAs( $this->admin )
			->get( route( 'dashboard' ) )
			->assertRedirect( route( 'login' ) );

		$this->assertGuest();
	}

	/**
	 * Agents don't need the proxy.
	 *
	 * @return void
	 */
	public function test_agents_need_no_proxy(): void {
		$agent = $this->create_user( User::ROLE_USER );

		$this->proxied( '0' )->actingAs( $agent )->get( route( 'dashboard' ) )->assertStatus( 200 );

		$this->assertAuthenticatedAs( $agent );
	}

	/**
	 * Until it's required, administrators don't need the proxy.
	 *
	 * @return void
	 */
	public function test_does_nothing_until_required(): void {
		config( array( 'wporgsso.require_proxy' => false ) );

		$this->proxied( '0' )->actingAs( $this->admin )->get( route( 'dashboard' ) )->assertStatus( 200 );

		$this->assertAuthenticatedAs( $this->admin );
	}
}
