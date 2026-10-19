<?php
/**
 * Tests for the FreeScout passwords of users who log in with WordPress.org.
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
 * Covers core's "no password" marker for new users, logins through WordPress.org, and break-glass passwords.
 */
final class PasswordsTest extends SsoTestCase {

	/**
	 * Users added once WordPress.org is enforced have no password.
	 *
	 * @return void
	 */
	public function test_new_user_has_no_password(): void {
		$this->log_in( $this->create_user( User::ROLE_ADMIN ), 'boss' );

		$this->create( 'rita' )->assertRedirect();

		$user = User::query()->where( 'email', 'rita@example.org' )->firstOrFail();
		$this->assertTrue( $user->isDummyPassword() );
		$this->assertFalse( \Hash::check( '', (string) $user->password ) );
	}

	/**
	 * Before WordPress.org is enforced, new users keep the password core gives them.
	 *
	 * @return void
	 */
	public function test_new_user_keeps_password_before_enforced(): void {
		config( array( 'wporgsso.idp.cert' => '' ) );
		$this->log_in( $this->create_user( User::ROLE_ADMIN ), 'boss' );

		$this->create( 'rita' );

		$this->assertFalse( User::query()->where( 'email', 'rita@example.org' )->firstOrFail()->isDummyPassword() );
	}

	/**
	 * Logging in through WordPress.org clears an existing user's password.
	 *
	 * @return void
	 */
	public function test_login_clears_password(): void {
		$user = $this->create_user( User::ROLE_USER );

		$this->log_in_with_wordpress_org( $user );

		$this->assertTrue( $user->refresh()->isDummyPassword() );
	}

	/**
	 * An administrator's password from before WordPress.org was enforced is cleared too.
	 *
	 * @return void
	 */
	public function test_login_clears_administrators_old_password(): void {
		$admin = $this->create_user( User::ROLE_ADMIN );

		$this->log_in_with_wordpress_org( $admin );

		$this->assertTrue( $admin->refresh()->isDummyPassword() );
	}

	/**
	 * A break-glass password from `wporgsso:password` survives logins through WordPress.org.
	 *
	 * @return void
	 */
	public function test_login_keeps_break_glass_password(): void {
		$admin = $this->create_user( User::ROLE_ADMIN );
		\Artisan::call( 'wporgsso:password', array( 'email' => $admin->email ) );
		$password = trim( \Artisan::output() );

		$this->log_in_with_wordpress_org( $admin );

		$this->assertTrue( \Hash::check( $password, (string) $admin->refresh()->password ) );
	}

	/**
	 * Someone who is no longer an administrator loses their break-glass password at their next login.
	 *
	 * @return void
	 */
	public function test_login_clears_break_glass_password_of_former_administrator(): void {
		$admin = $this->create_user( User::ROLE_ADMIN );
		\Artisan::call( 'wporgsso:password', array( 'email' => $admin->email ) );
		$admin->role = User::ROLE_USER;
		$admin->save();

		$this->log_in_with_wordpress_org( $admin );

		$this->assertTrue( $admin->refresh()->isDummyPassword() );
	}

	/**
	 * In break-glass mode, an administrator without a password can't log in with one.
	 *
	 * @return void
	 */
	public function test_no_password_does_not_log_in(): void {
		config( array( 'wporgsso.password_login' => true ) );
		$admin = $this->create_user( User::ROLE_ADMIN );
		$this->log_in_with_wordpress_org( $admin );
		\Auth::logout();
		$marker = (string) $admin->refresh()->password;

		foreach ( array( '', 'secret', $marker, \Crypt::decrypt( $marker ) ) as $password ) {
			$this->post(
				route( 'login' ),
				array(
					'email'    => $admin->email,
					'password' => $password,
				)
			)->assertSessionHasErrors();
			$this->assertGuest();
		}
	}

	/**
	 * Logs a user in through the identity provider, as rita.
	 *
	 * @param User $user User.
	 * @return void
	 */
	private function log_in_with_wordpress_org( User $user ): void {
		Account::connect( (int) $user->id, 'rita' );

		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) )->assertRedirect( route( 'dashboard' ) );
		$this->assertAuthenticatedAs( $user );
	}

	/**
	 * Acts as a user who logged in through WordPress.org.
	 *
	 * @param User   $user     User.
	 * @param string $username WordPress.org username to connect them to.
	 * @return void
	 */
	private function log_in( User $user, string $username ): void {
		Account::connect( (int) $user->id, $username );

		$this->actingAs( $user )->withSession(
			array(
				WPOrgSSOServiceProvider::SESSION_USERNAME => $username,
				WPOrgSSOServiceProvider::SESSION_CHECKED_AT => time(),
			)
		);
	}

	/**
	 * Submits the create form.
	 *
	 * @param string $username WordPress.org username.
	 * @return \Illuminate\Foundation\Testing\TestResponse
	 */
	private function create( string $username ): \Illuminate\Foundation\Testing\TestResponse {
		return $this->post(
			'/users/wizard',
			array(
				'_token'         => csrf_token(),
				'role'           => User::ROLE_USER,
				'wporg_username' => $username,
				'password'       => 'chosen-by-admin',
			)
		);
	}
}
