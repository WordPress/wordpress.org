<?php
/**
 * Tests for logging in through WordPress.org.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Tests;

use App\User;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider;
use Modules\WPOrgSSO\Services\Saml;
use Modules\WPOrgSSO\Tests\Support\IdentityProvider;

require_once __DIR__ . '/SsoTestCase.php';

/**
 * Covers the SAML login, and that it's the only way in.
 */
final class LoginTest extends SsoTestCase {

	/**
	 * User connected to the "rita" account.
	 *
	 * @var User
	 */
	private $user;

	/**
	 * Creates a connected user.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->create_user( User::ROLE_USER );
		Account::connect( (int) $this->user->id, 'rita' );
	}

	/**
	 * The login page only offers WordPress.org.
	 *
	 * @return void
	 */
	public function test_login_page_has_no_password_form(): void {
		$html = (string) $this->get( route( 'login' ) )->assertStatus( 200 )->getContent();

		$this->assertStringContainsString( route( 'wporgsso.start' ), $html );
		$this->assertStringNotContainsString( 'name="password"', $html );
	}

	/**
	 * A connected user logs in through the identity provider.
	 *
	 * @return void
	 */
	public function test_logs_in_a_connected_user(): void {
		$location = $this->post_to_acs( $this->start_login( 'rita' ) );

		$this->complete( $location )->assertRedirect( route( 'dashboard' ) );

		$this->assertAuthenticatedAs( $this->user );
		$this->assertSame( 'rita', session( WPOrgSSOServiceProvider::SESSION_USERNAME ) );
	}

	/**
	 * A user who was invited before WordPress.org was enforced is active once they log in with it.
	 *
	 * @return void
	 */
	public function test_login_activates_invited_user(): void {
		$this->user->invite_state = User::INVITE_STATE_SENT;
		$this->user->save();

		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) );

		$this->assertEquals( User::INVITE_STATE_ACTIVATED, $this->user->fresh()->invite_state );
	}

	/**
	 * The certificate may keep its BEGIN/END markers, as long as it's on one line.
	 *
	 * @return void
	 */
	public function test_accepts_certificate_with_markers(): void {
		config( array( 'wporgsso.idp.cert' => '-----BEGIN CERTIFICATE-----' . $this->idp->certificate_body() . '-----END CERTIFICATE-----' ) );

		$location = $this->post_to_acs( $this->start_login( 'rita' ) );

		$this->complete( $location )->assertRedirect( route( 'dashboard' ) );
		$this->assertAuthenticatedAs( $this->user );
	}

	/**
	 * A certificate pasted with its line breaks written as \n works, since .env values are one line.
	 *
	 * @return void
	 */
	public function test_accepts_certificate_with_escaped_line_breaks(): void {
		config( array( 'wporgsso.idp.cert' => '-----BEGIN CERTIFICATE-----\n' . chunk_split( $this->idp->certificate_body(), 64, '\n' ) . '-----END CERTIFICATE-----' ) );

		$location = $this->post_to_acs( $this->start_login( 'rita' ) );

		$this->complete( $location )->assertRedirect( route( 'dashboard' ) );
		$this->assertAuthenticatedAs( $this->user );
	}

	/**
	 * An unreadable certificate, like the first line of one pasted over several, is logged as such.
	 *
	 * @return void
	 */
	public function test_logs_unreadable_certificate(): void {
		\Log::spy();

		foreach ( array( '-----BEGIN CERTIFICATE-----', '-----BEGIN PUBLIC KEY-----' . $this->idp->certificate_body(), substr( $this->idp->certificate_body(), 0, 64 ) ) as $cert ) {
			config( array( 'wporgsso.idp.cert' => $cert ) );

			$this->assertStringNotContainsString( 'key=', $this->post_to_acs( $this->start_login( 'rita' ) ) );
		}

		\Log::shouldHaveReceived( 'error' )->with( \Mockery::pattern( '/WPORG_SSO_IDP_CERT is not a readable/' ) )->times( 3 );
		$this->assertGuest();
	}

	/**
	 * Logged-in users are sent on from the login page, as before.
	 *
	 * @return void
	 */
	public function test_login_page_redirects_logged_in_users(): void {
		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) );

		$this->get( route( 'login' ) )->assertRedirect();
	}

	/**
	 * A response can't be redeemed twice.
	 *
	 * @return void
	 */
	public function test_handoff_is_single_use(): void {
		$location = $this->post_to_acs( $this->start_login( 'rita' ) );
		$this->complete( $location );
		$this->post( route( 'logout' ) );

		$this->complete( $location )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * A key sent as an array gets the login page's error, not an error page.
	 *
	 * @return void
	 */
	public function test_array_key_is_refused(): void {
		$this->get( '/wporgsso/complete?key[]=x' )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * A response to another browser's login request doesn't log this one in.
	 *
	 * @return void
	 */
	public function test_rejects_response_to_another_request(): void {
		$this->start_login( 'rita' );

		$location = $this->post_to_acs(
			$this->idp->response(
				array(
					'username'       => 'rita',
					'acs'            => $this->acs_url(),
					'audience'       => Saml::from_config()->entity_id(),
					'in_response_to' => '_someone-elses-request',
				)
			)
		);

		$this->complete( $location )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * Responses not signed by the configured identity provider are refused at the ACS.
	 *
	 * @return void
	 */
	public function test_rejects_response_from_another_identity_provider(): void {
		$real_idp  = $this->idp;
		$this->idp = IdentityProvider::generate( self::IDP_ENTITY_ID );

		$location  = $this->post_to_acs( $this->start_login( 'rita' ) );
		$this->idp = $real_idp;

		$this->assertStringNotContainsString( 'key=', $location );
		$this->complete( $location )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * Changing the username after signing breaks the signature.
	 *
	 * @return void
	 */
	public function test_rejects_tampered_response(): void {
		$xml = (string) base64_decode( $this->start_login( 'rita' ), true );
		$xml = str_replace( '>rita</saml:NameID>', '>admin</saml:NameID>', $xml );

		$this->assertStringNotContainsString( 'key=', $this->post_to_acs( base64_encode( $xml ) ) );
	}

	/**
	 * Expired responses are refused.
	 *
	 * @return void
	 */
	public function test_rejects_expired_response(): void {
		$location = (string) $this->get( '/wporgsso/start' )->headers->get( 'Location' );
		parse_str( (string) parse_url( $location, PHP_URL_QUERY ), $query );
		$request = IdentityProvider::read_request( (string) $query['SAMLRequest'] );

		$response = $this->idp->response(
			array(
				'username'       => 'rita',
				'acs'            => $request['acs'],
				'audience'       => $request['issuer'],
				'in_response_to' => $request['id'],
				'issued_at'      => time() - 600,
			)
		);

		$this->assertStringNotContainsString( 'key=', $this->post_to_acs( $response ) );
	}

	/**
	 * WordPress.org accounts without a FreeScout user can't log in.
	 *
	 * @return void
	 */
	public function test_refuses_unconnected_account(): void {
		Account::for_user( (int) $this->user->id )->delete();

		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) )->assertRedirect( route( 'login' ) );

		$this->assertGuest();
		$this->assertStringContainsString( 'rita', (string) session( WPOrgSSOServiceProvider::SESSION_ERROR ) );
	}

	/**
	 * Disabled users can't log in.
	 *
	 * @return void
	 */
	public function test_refuses_disabled_user(): void {
		$this->user->status = User::STATUS_DISABLED;
		$this->user->save();

		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * Accounts without two-factor authentication can't log in.
	 *
	 * @return void
	 */
	public function test_refuses_account_without_two_factor(): void {
		$this->accounts['rita']['two_factor'] = false;

		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) )->assertRedirect( route( 'login' ) );

		$this->assertGuest();
		$this->assertStringContainsString( 'two-factor', (string) session( WPOrgSSOServiceProvider::SESSION_ERROR ) );
	}

	/**
	 * Blocked accounts can't log in.
	 *
	 * @return void
	 */
	public function test_refuses_blocked_account(): void {
		$this->accounts['rita']['blocked'] = true;

		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * If two-factor status can't be checked, nobody gets in.
	 *
	 * @return void
	 */
	public function test_refuses_login_when_api_is_down(): void {
		$location       = $this->post_to_acs( $this->start_login( 'rita' ) );
		$this->api_down = true;

		$this->complete( $location )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * Password logins are closed.
	 *
	 * @return void
	 */
	public function test_password_login_is_closed(): void {
		$this->post(
			route( 'login' ),
			array(
				'email'    => $this->user->email,
				'password' => 'password',
			)
		)->assertRedirect( route( 'login' ) );

		$this->assertGuest();
	}

	/**
	 * Invite links can't be used to set a password and log in.
	 *
	 * @return void
	 */
	public function test_user_setup_is_closed(): void {
		$this->get( '/user-setup/' . str_repeat( 'a', User::INVITE_HASH_LENGTH ) . '/' . time() )->assertRedirect( route( 'login' ) );
	}

	/**
	 * Sessions that didn't come through WordPress.org are ended.
	 *
	 * @return void
	 */
	public function test_keeps_sessions_from_before_wordpress_org_was_enforced(): void {
		$this->actingAs( $this->user )->get( route( 'dashboard' ) )->assertStatus( 200 );
		$this->assertAuthenticatedAs( $this->user );
	}

	/**
	 * A day after WordPress.org was enforced, a session without its marks is from while the module was off, and ends.
	 *
	 * @return void
	 */
	public function test_logs_out_unmarked_sessions_after_the_cutover(): void {
		\Option::set( WPOrgSSOServiceProvider::OPTION_ENFORCED_SINCE, time() - 86400 );

		$this->actingAs( $this->user )->get( route( 'dashboard' ) )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * A login without WordPress.org once it's enforced ends on the next request.
	 *
	 * @return void
	 */
	public function test_logs_out_logins_without_wordpress_org(): void {
		$this->actingAs( $this->user )
			->withSession( array( WPOrgSSOServiceProvider::SESSION_REFUSED => true ) )
			->get( route( 'dashboard' ) )
			->assertRedirect( route( 'login' ) );

		$this->assertGuest();
	}

	/**
	 * Disconnecting an account ends its sessions.
	 *
	 * @return void
	 */
	public function test_logs_out_users_whose_account_was_disconnected(): void {
		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) );
		Account::for_user( (int) $this->user->id )->delete();

		$this->get( route( 'dashboard' ) )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * Connecting a user to another account ends their sessions too.
	 *
	 * @return void
	 */
	public function test_logs_out_users_whose_account_was_changed(): void {
		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) );
		$this->get( route( 'dashboard' ) )->assertStatus( 200 );

		Account::connect( (int) $this->user->id, 'someone-else' );

		$this->get( route( 'dashboard' ) )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * The connection is checked on every request, from the cache rather than the database.
	 *
	 * @return void
	 */
	public function test_checks_connection_from_cache(): void {
		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) );
		$this->get( route( 'dashboard' ) );

		\DB::enableQueryLog();
		$this->get( route( 'dashboard' ) )->assertStatus( 200 );

		$this->assertEmpty(
			array_filter(
				\DB::getQueryLog(),
				static function ( array $query ): bool {
					return str_contains( $query['query'], 'wporgsso_accounts' );
				}
			)
		);
	}

	/**
	 * With the break-glass switch on, only administrators' accounts can use the password pages.
	 *
	 * @return void
	 */
	public function test_password_pages_are_for_administrators_only(): void {
		config( array( 'wporgsso.password_login' => true ) );

		// Core logs them in; the session ends on the next request.
		$this->post(
			route( 'login' ),
			array(
				'email'    => $this->user->email,
				'password' => 'secret',
			)
		);
		$this->get( route( 'dashboard' ) )->assertRedirect( route( 'login' ) );
		$this->assertGuest();

		$this->post( route( 'password.email' ), array( 'email' => $this->user->email ) )->assertRedirect( route( 'login' ) );
		$this->get( '/user-setup/' . str_repeat( 'a', User::INVITE_HASH_LENGTH ) . '/' . time() )->assertRedirect( route( 'login' ) );
	}

	/**
	 * Even with the break-glass switch on, reset links aren't sent to administrators: their inbox isn't two-factor.
	 *
	 * @return void
	 */
	public function test_password_resets_stay_closed_in_break_glass(): void {
		config( array( 'wporgsso.password_login' => true ) );
		$admin = $this->create_user( User::ROLE_ADMIN );

		$this->get( route( 'password.request' ) )->assertRedirect( route( 'login' ) );
		$this->post( route( 'password.email' ), array( 'email' => $admin->email ) )->assertRedirect( route( 'login' ) );
		$this->assertSame( 0, \DB::table( 'password_resets' )->where( 'email', $admin->email )->count() );
	}

	/**
	 * With the break-glass switch on, administrators may use their password; nobody else may.
	 *
	 * @return void
	 */
	public function test_password_login_is_for_administrators_only(): void {
		config( array( 'wporgsso.password_login' => true ) );

		$this->assertStringContainsString( 'name="password"', $this->core_login_form( route( 'login', array( 'password' => 1 ) ) ) );

		$admin = $this->create_user( User::ROLE_ADMIN );
		$this->post(
			route( 'login' ),
			array(
				'email'    => $admin->email,
				'password' => 'secret',
			)
		);
		$this->get( route( 'dashboard' ) )->assertStatus( 200 );
	}

	/**
	 * In break-glass mode, a failed password login doesn't tell administrators' email addresses from others.
	 *
	 * @return void
	 */
	public function test_failed_password_login_does_not_reveal_administrators(): void {
		config( array( 'wporgsso.password_login' => true ) );
		$admin = $this->create_user( User::ROLE_ADMIN );

		foreach ( array( $admin->email, $this->user->email, 'nobody@example.org' ) as $email ) {
			$this->post(
				route( 'login' ),
				array(
					'email'    => $email,
					'password' => 'wrong',
				)
			)->assertSessionHasErrors( array( 'email' => trans( 'auth.failed' ) ) );
		}
	}

	/**
	 * In break-glass mode, anyone else's right password gets the answer to a wrong one, and counts as a failed attempt.
	 *
	 * @return void
	 */
	public function test_password_login_does_not_confirm_others_passwords(): void {
		config( array( 'wporgsso.password_login' => true ) );

		$this->post(
			route( 'login' ),
			array(
				'email'    => $this->user->email,
				'password' => 'secret',
			)
		)->assertSessionHasErrors( array( 'email' => trans( 'auth.failed' ) ) );

		$this->assertGuest();
		$this->assertSame( 1, app( \Illuminate\Cache\RateLimiter::class )->attempts( strtolower( $this->user->email ) . '|127.0.0.1' ) );
	}

	/**
	 * In break-glass mode, administrators stay logged in only after a password login, not after any other login
	 * without WordPress.org, like one from a reset or invite link.
	 *
	 * @return void
	 */
	public function test_other_logins_are_refused_in_break_glass(): void {
		config( array( 'wporgsso.password_login' => true ) );

		// Like a login in a request, which has a session.
		$this->app['request']->setLaravelSession( $this->app['session.store'] );
		\Auth::login( $this->create_user( User::ROLE_ADMIN ) );

		$this->get( route( 'dashboard' ) )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * Only the account that logged in counts, not one account.php found some other way.
	 *
	 * @return void
	 */
	public function test_refuses_account_with_another_username(): void {
		$this->accounts['rita']['username'] = 'someone-else';

		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * FreeScout's app can't restore a session behind WordPress.org's back; the user logs in again.
	 *
	 * @return void
	 */
	public function test_app_session_restore_needs_a_new_login(): void {
		// Core's app token: base64 of "user ID:expiry:HMAC", keyed with the app key and the password hash.
		$expiry = time() + 60;
		$token  = base64_encode( $this->user->id . ':' . $expiry . ':' . hash_hmac( 'sha256', $this->user->id . ':' . $expiry, config( 'app.key' ) . $this->user->password ) );

		$this->call( 'GET', route( 'dashboard' ), array( 'auth_token' => $token ), array( 'in_app' => '1' ) )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}


	/**
	 * Sessions are checked against WordPress.org again after an hour.
	 *
	 * @return void
	 */
	public function test_ends_session_of_account_blocked_since(): void {
		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) );
		$this->accounts['rita']['blocked'] = true;

		$this->get( route( 'dashboard' ) )->assertStatus( 200 );

		session( array( WPOrgSSOServiceProvider::SESSION_CHECKED_AT => time() - 3601 ) );
		$this->get( route( 'dashboard' ) )->assertRedirect( route( 'login' ) );
		$this->assertGuest();
	}

	/**
	 * If WordPress.org can't be reached for the check, the session goes on.
	 *
	 * @return void
	 */
	public function test_keeps_session_when_check_fails(): void {
		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) );
		$this->api_down = true;

		session( array( WPOrgSSOServiceProvider::SESSION_CHECKED_AT => time() - 3601 ) );
		$this->get( route( 'dashboard' ) )->assertStatus( 200 );
	}

	/**
	 * Logins started in two tabs both work.
	 *
	 * @return void
	 */
	public function test_parallel_logins(): void {
		$first  = $this->start_login( 'rita' );
		$second = $this->start_login( 'rita' );

		$this->complete( $this->post_to_acs( $first ) )->assertRedirect( route( 'dashboard' ) );
		$this->assertAuthenticatedAs( $this->user );

		\Auth::logout();
		$this->complete( $this->post_to_acs( $second ) )->assertRedirect( route( 'dashboard' ) );
		$this->assertAuthenticatedAs( $this->user );
	}

	/**
	 * Without the API, WordPress.org can't vouch for anyone, so logins stay as they are.
	 *
	 * @return void
	 */
	public function test_does_nothing_without_api_secret(): void {
		$this->app->instance( \Modules\WPOrgSSO\Services\Client::class, new \Modules\WPOrgSSO\Services\Client( 'https://api.wordpress.test/', '' ) );

		$this->assertStringContainsString( 'name="password"', $this->core_login_form( route( 'login' ) ) );
		$this->actingAs( $this->user )->get( route( 'dashboard' ) )->assertStatus( 200 );
	}

	/**
	 * An ended session answers polls with a 401, and brings pages back after the next login.
	 *
	 * @return void
	 */
	public function test_ended_session_keeps_the_page(): void {
		$refused = array( WPOrgSSOServiceProvider::SESSION_REFUSED => true );

		$this->actingAs( $this->user )->withSession( $refused )->getJson( route( 'dashboard' ) )->assertStatus( 401 );

		$this->actingAs( $this->user )->withSession( $refused )->get( route( 'users.profile', array( 'id' => $this->user->id ) ) )->assertRedirect( route( 'login' ) );
		$this->assertSame( route( 'users.profile', array( 'id' => $this->user->id ) ), session( 'url.intended' ) );
	}

	/**
	 * Without the break-glass switch, the password pages point to WordPress.org, not to administrators.
	 *
	 * @return void
	 */
	public function test_password_pages_point_to_wordpress_org(): void {
		$this->post( route( 'password.email' ), array( 'email' => $this->user->email ) )->assertRedirect( route( 'login' ) );

		$this->assertStringContainsString( 'WordPress.org', (string) session( WPOrgSSOServiceProvider::SESSION_ERROR ) );
	}

	/**
	 * Without an identity provider, logins stay as they are.
	 *
	 * @return void
	 */
	public function test_does_nothing_until_configured(): void {
		config( array( 'wporgsso.idp.cert' => '' ) );

		$this->assertStringContainsString( 'name="password"', $this->core_login_form( route( 'login' ) ) );
	}

	/**
	 * Renders core's password form.
	 *
	 * @param string $url Login URL.
	 * @return string HTML.
	 */
	private function core_login_form( string $url ): string {
		// Core escapes old('email'), which is null on a first visit; PHP 8 deprecates that, and tests turn it into an exception.
		return (string) $this->withSession( array( '_old_input' => array( 'email' => '' ) ) )->get( $url )->getContent();
	}

	/**
	 * The metadata systems registers FreeScout with.
	 *
	 * @return void
	 */
	public function test_serves_metadata(): void {
		$xml = (string) $this->get( '/wporgsso/metadata' )->assertStatus( 200 )->getContent();

		$this->assertStringContainsString( 'Location="' . $this->acs_url() . '"', $xml );
	}
}
