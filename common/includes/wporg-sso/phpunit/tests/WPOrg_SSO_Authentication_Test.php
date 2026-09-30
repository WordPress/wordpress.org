<?php
/**
 * Tests for the accounts the SSO refuses to log in.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

/**
 * Covers the `authenticate` filters that turn away the reserved `admin` login,
 * accounts blocked in the support forums, and accounts barred from the web.
 */
class WPOrg_SSO_Authentication_Test extends WPOrg_SSO_TestCase {

	/**
	 * The password the fixture accounts log in with.
	 *
	 * @var string
	 */
	const PASSWORD = 'correct-horse-battery-staple';

	/**
	 * The SSO instance under test.
	 *
	 * @var WPOrg_SSO_Test_Double
	 */
	protected WPOrg_SSO_Test_Double $sso;

	/**
	 * Puts the SSO on the login host, where authentication happens.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->sso = $this->make_sso( 'login.wordpress.org', '/wp-login.php', '/wp-login.php' );
	}

	/**
	 * Nobody logs in as `admin`; the name belongs to their own site, not this one.
	 *
	 * @return void
	 */
	public function test_admin_login_is_turned_away(): void {
		$user = $this->sso->authenticate_admin_check( null, 'admin' );

		$this->assert_wp_error( $user );
		$this->assertSame( 'admin_wrong_place', $user->get_error_code() );
	}

	/**
	 * The check is on the name, whatever case it arrives in.
	 *
	 * @return void
	 */
	public function test_admin_login_is_turned_away_whatever_the_case(): void {
		$this->assert_wp_error( $this->sso->authenticate_admin_check( null, 'AdMiN' ) );
	}

	/**
	 * A later `authenticate` callback cannot undo the refusal.
	 *
	 * Returning a WP_Error from the filter is not enough on its own, because a
	 * later callback can still hand back a user.
	 *
	 * @return void
	 */
	public function test_admin_refusal_cannot_be_overridden(): void {
		$this->make_account( 'admin' );

		add_filter( 'authenticate', '__return_true', 100 );

		$this->assert_wp_error( wp_authenticate( 'admin', self::PASSWORD ) );
	}

	/**
	 * Everybody else passes through untouched.
	 *
	 * @return void
	 */
	public function test_other_logins_pass_the_admin_check(): void {
		$user = new WP_User( $this->factory->user->create( array( 'user_login' => 'administrator-jane' ) ) );

		$this->assertSame( $user, $this->sso->authenticate_admin_check( $user, 'administrator-jane' ) );
	}

	/**
	 * An account blocked in the support forums cannot log in anywhere.
	 *
	 * @return void
	 */
	public function test_forum_blocked_account_cannot_log_in(): void {
		$user = new WP_User( $this->factory->user->create( array( 'user_login' => 'sso-blocked' ) ) );
		$user->add_cap( 'bbp_blocked' );

		$refusal = $this->sso->authenticate_block_check( null, 'sso-blocked' );

		$this->assert_wp_error( $refusal );
		$this->assertSame( 'blocked_account', $refusal->get_error_code() );
	}

	/**
	 * Accounts blocked by breaking their password hash are refused too.
	 *
	 * Older blocks were applied by prefixing the stored hash; see
	 * https://meta.trac.wordpress.org/changeset/10578.
	 *
	 * @return void
	 */
	public function test_account_with_a_broken_password_cannot_log_in(): void {
		global $wpdb;

		$user_id = $this->factory->user->create( array( 'user_login' => 'sso-legacy-blocked' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No API writes a deliberately invalid hash.
		$wpdb->update( $wpdb->users, array( 'user_pass' => 'BLOCKED-no-longer-a-hash' ), array( 'ID' => $user_id ) );
		clean_user_cache( $user_id );

		$refusal = $this->sso->authenticate_block_check( null, 'sso-legacy-blocked' );

		$this->assert_wp_error( $refusal );
		$this->assertSame( 'blocked_account', $refusal->get_error_code() );
	}

	/**
	 * The block is found by email address as well as by login name.
	 *
	 * @return void
	 */
	public function test_forum_blocked_account_is_found_by_email(): void {
		$user = new WP_User(
			$this->factory->user->create(
				array(
					'user_login' => 'sso-blocked-by-email',
					'user_email' => 'blocked@example.org',
				)
			)
		);
		$user->add_cap( 'bbp_blocked' );

		$this->assert_wp_error( $this->sso->authenticate_block_check( null, 'blocked@example.org' ) );
	}

	/**
	 * An account in good standing passes the block check.
	 *
	 * @return void
	 */
	public function test_unblocked_account_passes_the_block_check(): void {
		$user = new WP_User( $this->factory->user->create( array( 'user_login' => 'sso-welcome' ) ) );

		$this->assertSame( $user, $this->sso->authenticate_block_check( $user, 'sso-welcome' ) );
	}

	/**
	 * A login nobody has ever used is not mistaken for a blocked one.
	 *
	 * @return void
	 */
	public function test_unknown_login_passes_the_block_check(): void {
		$this->assertNull( $this->sso->authenticate_block_check( null, 'nobody-by-that-name' ) );
	}

	/**
	 * Accounts on the no-login list cannot log in through the web.
	 *
	 * @return void
	 */
	public function test_nologin_accounts_cannot_log_in(): void {
		$GLOBALS['nologin_accounts'] = array( 'wordpressdotorg' );

		$refusal = $this->sso->authenticate_block_nologin_accounts( null, 'wordpressdotorg' );

		$this->assert_wp_error( $refusal );
		$this->assertSame( 'blocked_account', $refusal->get_error_code() );
	}

	/**
	 * The no-login list is matched exactly, not by prefix or by case.
	 *
	 * @return void
	 */
	public function test_nologin_list_is_matched_exactly(): void {
		$GLOBALS['nologin_accounts'] = array( 'wordpressdotorg' );

		$this->assertNull( $this->sso->authenticate_block_nologin_accounts( null, 'wordpressdotorg-bot' ) );
	}

	/**
	 * With no list configured, nobody is barred.
	 *
	 * @return void
	 */
	public function test_no_nologin_list_bars_nobody(): void {
		$this->assertNull( $this->sso->authenticate_block_nologin_accounts( null, 'anybody' ) );
	}

	/**
	 * A blocked account cannot reset its way back in.
	 *
	 * @return void
	 */
	public function test_blocked_account_cannot_reset_its_password(): void {
		$user = new WP_User( $this->factory->user->create() );
		$user->add_cap( 'bbp_blocked' );

		$this->assertFalse( $this->sso->disable_password_reset_for_blocked_users( true, $user->ID ) );
	}

	/**
	 * Everyone else keeps their password reset.
	 *
	 * @return void
	 */
	public function test_unblocked_account_can_reset_its_password(): void {
		$user_id = $this->factory->user->create();

		$this->assertTrue( $this->sso->disable_password_reset_for_blocked_users( true, $user_id ) );
	}

	/**
	 * A reset already refused elsewhere stays refused.
	 *
	 * @return void
	 */
	public function test_a_reset_refused_elsewhere_stays_refused(): void {
		$user_id = $this->factory->user->create();

		$this->assertFalse( $this->sso->disable_password_reset_for_blocked_users( false, $user_id ) );
	}

	// Unlike the direct calls above, these fail if the instance stops hooking `authenticate` at all.

	/**
	 * Correct credentials still get their user; the filters are not a blanket no.
	 *
	 * @return void
	 */
	public function test_valid_credentials_authenticate(): void {
		$this->make_account( 'sso-good-standing' );

		$this->assertInstanceOf( WP_User::class, wp_authenticate( 'sso-good-standing', self::PASSWORD ) );
	}

	/**
	 * Correct credentials do not get `admin` in.
	 *
	 * @return void
	 */
	public function test_admin_cannot_authenticate(): void {
		$this->make_account( 'admin' );

		$refusal = wp_authenticate( 'admin', self::PASSWORD );

		$this->assert_wp_error( $refusal );
		$this->assertSame( 'admin_wrong_place', $refusal->get_error_code() );
	}

	/**
	 * Correct credentials do not get a blocked account in.
	 *
	 * @return void
	 */
	public function test_blocked_account_cannot_authenticate(): void {
		$this->make_account( 'sso-blocked-for-real' )->add_cap( 'bbp_blocked' );

		$refusal = wp_authenticate( 'sso-blocked-for-real', self::PASSWORD );

		$this->assert_wp_error( $refusal );
		$this->assertSame( 'blocked_account', $refusal->get_error_code() );
	}

	/**
	 * Correct credentials do not get a no-login account in.
	 *
	 * @return void
	 */
	public function test_nologin_account_cannot_authenticate(): void {
		$this->make_account( 'sso-no-web-login' );

		$GLOBALS['nologin_accounts'] = array( 'sso-no-web-login' );

		$refusal = wp_authenticate( 'sso-no-web-login', self::PASSWORD );

		$this->assert_wp_error( $refusal );
		$this->assertSame( 'blocked_account', $refusal->get_error_code() );
	}

	/**
	 * Creates an account whose password is known, so it can really log in.
	 *
	 * @param string $login The login name to create.
	 * @return WP_User
	 */
	protected function make_account( string $login ): WP_User {
		return new WP_User(
			$this->factory->user->create(
				array(
					'user_login' => $login,
					'user_pass'  => self::PASSWORD,
				)
			)
		);
	}

	/**
	 * Asserts that a value is a WP_Error.
	 *
	 * @param mixed $actual The value to check.
	 * @return void
	 */
	protected function assert_wp_error( $actual ): void {
		$this->assertInstanceOf( WP_Error::class, $actual );
	}
}
