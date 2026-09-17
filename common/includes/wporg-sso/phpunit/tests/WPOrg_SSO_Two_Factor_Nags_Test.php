<?php
/**
 * Tests for the two-factor nags and the session length that goes with them.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

/**
 * Covers what happens straight after a successful login on the SSO host: how
 * long the session lasts, and whether the user is sent to set up two-factor or
 * to replace backup codes before they get where they were going.
 */
class WPOrg_SSO_Two_Factor_Nags_Test extends WPOrg_SSO_TestCase {

	/**
	 * The SSO instance under test.
	 *
	 * @var WPOrg_SSO_Test_Double
	 */
	protected WPOrg_SSO_Test_Double $sso;

	/**
	 * The user logging in.
	 *
	 * @var WP_User
	 */
	protected WP_User $user;

	/**
	 * Where the user was heading before the nags got a say.
	 *
	 * @var string
	 */
	protected string $destination = 'https://make.wordpress.org/core/';

	/**
	 * Puts the SSO on the login host with a user mid-login.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->sso  = $this->make_sso( 'login.wordpress.org', '/wp-login.php', '/wp-login.php' );
		$this->user = new WP_User( $this->factory->user->create() );
	}

	/**
	 * A user with two-factor keeps the session length WordPress asked for.
	 *
	 * @return void
	 */
	public function test_session_length_is_untouched_for_two_factor_users(): void {
		WPOrg_SSO_Two_Factor_State::$should_2fa[ $this->user->ID ] = true;
		WPOrg_SSO_Two_Factor_State::$using_2fa[ $this->user->ID ]  = true;

		$this->assertSame( 14 * DAY_IN_SECONDS, $this->sso->auth_cookie_expiration( 14 * DAY_IN_SECONDS, $this->user->ID ) );
	}

	/**
	 * A user who should have two-factor but does not gets a shorter session.
	 *
	 * @return void
	 */
	public function test_session_is_shortened_without_two_factor(): void {
		WPOrg_SSO_Two_Factor_State::$should_2fa[ $this->user->ID ] = true;

		$this->assertSame( 2 * DAY_IN_SECONDS, $this->sso->auth_cookie_expiration( 14 * DAY_IN_SECONDS, $this->user->ID ) );
	}

	/**
	 * A session already shorter than the cap is not extended to it.
	 *
	 * @return void
	 */
	public function test_short_sessions_are_not_extended(): void {
		WPOrg_SSO_Two_Factor_State::$should_2fa[ $this->user->ID ] = true;

		$this->assertSame( DAY_IN_SECONDS, $this->sso->auth_cookie_expiration( DAY_IN_SECONDS, $this->user->ID ) );
	}

	/**
	 * Users who are not expected to use two-factor keep their session.
	 *
	 * @return void
	 */
	public function test_session_is_untouched_for_everyone_else(): void {
		$this->assertSame( 14 * DAY_IN_SECONDS, $this->sso->auth_cookie_expiration( 14 * DAY_IN_SECONDS, $this->user->ID ) );
	}

	/**
	 * The nags are about the user logging in, not whoever else is around.
	 *
	 * @return void
	 */
	public function test_the_nags_are_about_the_user_logging_in(): void {
		$someone_else = $this->factory->user->create();

		WPOrg_SSO_Two_Factor_State::$should_2fa[ $someone_else ]   = true;
		WPOrg_SSO_Two_Factor_State::$requires_2fa[ $someone_else ] = true;
		WPOrg_SSO_Two_Factor_State::$using_2fa[ $someone_else ]    = true;

		$this->assertSame(
			$this->destination,
			$this->sso->maybe_redirect_to_enable_2fa( $this->destination, $this->destination, $this->user )
		);
		$this->assertSame(
			$this->destination,
			$this->sso->maybe_redirect_to_backup_codes( $this->destination, $this->destination, $this->user )
		);
		$this->assertSame( 14 * DAY_IN_SECONDS, $this->sso->auth_cookie_expiration( 14 * DAY_IN_SECONDS, $this->user->ID ) );
	}

	/**
	 * A session for a user who no longer exists is left as WordPress set it.
	 *
	 * @return void
	 */
	public function test_session_length_is_untouched_for_an_unknown_user(): void {
		$this->assertSame( 14 * DAY_IN_SECONDS, $this->sso->auth_cookie_expiration( 14 * DAY_IN_SECONDS, 99999999 ) );
	}

	/**
	 * A user who must use two-factor is sent to set it up, every time.
	 *
	 * @return void
	 */
	public function test_mandatory_two_factor_always_nags(): void {
		WPOrg_SSO_Two_Factor_State::$should_2fa[ $this->user->ID ]   = true;
		WPOrg_SSO_Two_Factor_State::$requires_2fa[ $this->user->ID ] = true;

		update_user_meta( $this->user->ID, 'last_2fa_nag', time() );

		$redirect = $this->sso->maybe_redirect_to_enable_2fa( $this->destination, $this->destination, $this->user );

		$this->assertStringStartsWith( home_url( '/enable-2fa' ), $redirect );
		$this->assertSame( $this->destination, $this->query_arg( $redirect, 'redirect_to' ) );
	}

	/**
	 * A user who is only encouraged to use two-factor is nagged occasionally.
	 *
	 * @return void
	 */
	public function test_optional_two_factor_nags_when_never_nagged_before(): void {
		WPOrg_SSO_Two_Factor_State::$should_2fa[ $this->user->ID ] = true;

		$this->assertStringStartsWith(
			home_url( '/enable-2fa' ),
			$this->sso->maybe_redirect_to_enable_2fa( $this->destination, $this->destination, $this->user )
		);
	}

	/**
	 * A user nagged recently is left alone until the cooldown passes.
	 *
	 * @return void
	 */
	public function test_optional_two_factor_does_not_nag_inside_the_cooldown(): void {
		WPOrg_SSO_Two_Factor_State::$should_2fa[ $this->user->ID ] = true;

		update_user_meta( $this->user->ID, 'last_2fa_nag', time() );

		$this->assertSame(
			$this->destination,
			$this->sso->maybe_redirect_to_enable_2fa( $this->destination, $this->destination, $this->user )
		);
	}

	/**
	 * A user already using two-factor is never asked to enable it.
	 *
	 * @return void
	 */
	public function test_two_factor_users_are_not_nagged_to_enable_it(): void {
		WPOrg_SSO_Two_Factor_State::$should_2fa[ $this->user->ID ] = true;
		WPOrg_SSO_Two_Factor_State::$using_2fa[ $this->user->ID ]  = true;

		$this->assertSame(
			$this->destination,
			$this->sso->maybe_redirect_to_enable_2fa( $this->destination, $this->destination, $this->user )
		);
	}

	/**
	 * The nag does not stack on top of itself.
	 *
	 * @return void
	 */
	public function test_the_enable_nag_does_not_stack(): void {
		WPOrg_SSO_Two_Factor_State::$should_2fa[ $this->user->ID ]   = true;
		WPOrg_SSO_Two_Factor_State::$requires_2fa[ $this->user->ID ] = true;

		$already = home_url( '/enable-2fa' );

		$this->assertSame( $already, $this->sso->maybe_redirect_to_enable_2fa( $already, $already, $this->user ) );
	}

	/**
	 * A failed login is not nagged about anything.
	 *
	 * @return void
	 */
	public function test_failed_logins_are_not_nagged(): void {
		WPOrg_SSO_Two_Factor_State::$should_2fa[ $this->user->ID ]   = true;
		WPOrg_SSO_Two_Factor_State::$requires_2fa[ $this->user->ID ] = true;

		$error = new WP_Error( 'invalid_username', 'Nope.' );

		$this->assertSame( $this->destination, $this->sso->maybe_redirect_to_enable_2fa( $this->destination, $this->destination, $error ) );
		$this->assertSame( $this->destination, $this->sso->maybe_redirect_to_backup_codes( $this->destination, $this->destination, $error ) );
	}

	/*
	 * @todo A user with a few codes left and no `last_2fa_backup_codes_nag` meta
	 * is never nagged, because `$codes_available >= (int) ''` holds. Left
	 * untested until someone decides whether that is intended.
	 */

	/**
	 * A user who logged in with a backup code is sent to generate new ones.
	 *
	 * @return void
	 */
	public function test_logging_in_with_a_backup_code_nags(): void {
		WPOrg_SSO_Two_Factor_State::$using_2fa[ $this->user->ID ]       = true;
		WPOrg_SSO_Two_Factor_State::$codes_remaining[ $this->user->ID ] = 9;

		$this->log_in_with( 'Two_Factor_Backup_Codes' );

		$redirect = $this->sso->maybe_redirect_to_backup_codes( $this->destination, $this->destination, $this->user );

		$this->assertStringStartsWith( home_url( '/backup-codes' ), $redirect );
		$this->assertSame( $this->destination, $this->query_arg( $redirect, 'redirect_to' ) );
	}

	/**
	 * Dropping below the count last warned about is worth warning about again.
	 *
	 * @return void
	 */
	public function test_spending_codes_since_the_last_nag_nags_again(): void {
		WPOrg_SSO_Two_Factor_State::$using_2fa[ $this->user->ID ]       = true;
		WPOrg_SSO_Two_Factor_State::$codes_remaining[ $this->user->ID ] = 2;

		update_user_meta( $this->user->ID, 'last_2fa_backup_codes_nag', 3 );

		$this->log_in_with( 'Two_Factor_Totp' );

		$this->assertStringStartsWith(
			home_url( '/backup-codes' ),
			$this->sso->maybe_redirect_to_backup_codes( $this->destination, $this->destination, $this->user )
		);
	}

	/**
	 * A user with codes to spare is left alone.
	 *
	 * @return void
	 */
	public function test_ample_backup_codes_do_not_nag(): void {
		WPOrg_SSO_Two_Factor_State::$using_2fa[ $this->user->ID ]       = true;
		WPOrg_SSO_Two_Factor_State::$codes_remaining[ $this->user->ID ] = 9;

		$this->log_in_with( 'Two_Factor_Totp' );

		$this->assertSame(
			$this->destination,
			$this->sso->maybe_redirect_to_backup_codes( $this->destination, $this->destination, $this->user )
		);
	}

	/**
	 * Someone already nagged at this count is not nagged again.
	 *
	 * @return void
	 */
	public function test_repeat_nagging_at_the_same_count_is_skipped(): void {
		WPOrg_SSO_Two_Factor_State::$using_2fa[ $this->user->ID ]       = true;
		WPOrg_SSO_Two_Factor_State::$codes_remaining[ $this->user->ID ] = 2;

		update_user_meta( $this->user->ID, 'last_2fa_backup_codes_nag', 2 );

		$this->log_in_with( 'Two_Factor_Totp' );

		$this->assertSame(
			$this->destination,
			$this->sso->maybe_redirect_to_backup_codes( $this->destination, $this->destination, $this->user )
		);
	}

	/**
	 * A user with no backup codes at all is always sent to generate some.
	 *
	 * @return void
	 */
	public function test_having_no_backup_codes_always_nags(): void {
		WPOrg_SSO_Two_Factor_State::$using_2fa[ $this->user->ID ] = true;

		update_user_meta( $this->user->ID, 'last_2fa_backup_codes_nag', 0 );

		$this->log_in_with( 'Two_Factor_Totp' );

		$this->assertStringStartsWith(
			home_url( '/backup-codes' ),
			$this->sso->maybe_redirect_to_backup_codes( $this->destination, $this->destination, $this->user )
		);
	}

	/**
	 * A user without two-factor has no backup codes to be nagged about.
	 *
	 * @return void
	 */
	public function test_users_without_two_factor_are_not_nagged_about_codes(): void {
		$this->assertSame(
			$this->destination,
			$this->sso->maybe_redirect_to_backup_codes( $this->destination, $this->destination, $this->user )
		);
	}

	/**
	 * Starts a session for the user, recorded as using the given provider.
	 *
	 * The session is read back through the auth cookie the login just set,
	 * because at `login_redirect` time the cookie is not in `$_COOKIE` yet.
	 *
	 * @param string $provider The two-factor provider the login used.
	 * @return void
	 */
	protected function log_in_with( string $provider ): void {
		$manager = WP_Session_Tokens::get_instance( $this->user->ID );
		$token   = $manager->create( time() + HOUR_IN_SECONDS );

		$session = $manager->get( $token );

		$session['two-factor-provider'] = $provider;

		$manager->update( $token, $session );

		$this->sso->record_last_auth_cookie( 'cookie', 0, time() + HOUR_IN_SECONDS, $this->user->ID, 'logged_in', $token );
	}
}
