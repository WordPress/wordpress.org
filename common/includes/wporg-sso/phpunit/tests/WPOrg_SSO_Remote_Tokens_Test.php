<?php
/**
 * Tests for the remote login and logout tokens.
 *
 * These tokens are what lets a login on login.wordpress.org become a session on
 * bbpress.org or wordcamp.org, so every property they carry — who they are for,
 * where they may be spent, how long they live, and that they are spent once — is
 * load-bearing.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

/**
 * Covers minting, validating, claiming, and redeeming remote SSO tokens.
 */
class WPOrg_SSO_Remote_Tokens_Test extends WPOrg_SSO_TestCase {

	/**
	 * The user the tokens are minted for.
	 *
	 * @var WP_User
	 */
	protected WP_User $user;

	/**
	 * Authentication cookies issued during the test, in order.
	 *
	 * @var array[]
	 */
	protected array $issued_cookies = array();

	/**
	 * Creates the user under test and starts watching for issued cookies.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->user = new WP_User( $this->factory->user->create( array( 'user_login' => 'sso-traveller' ) ) );

		$this->issued_cookies = array();

		add_action( 'set_auth_cookie', array( $this, 'record_issued_cookie' ), 10, 6 );
	}

	/**
	 * Records an authentication cookie as it is issued.
	 *
	 * `wp_set_auth_cookie()` only calls `setcookie()`, which does nothing under
	 * CLI, so the action is the only place the cookie can be observed.
	 *
	 * @param string $auth_cookie The cookie value.
	 * @param int    $expire      When the cookie expires, as a timestamp.
	 * @param int    $expiration  When the authentication expires, as a timestamp.
	 * @param int    $user_id     The user the cookie authenticates.
	 * @param string $scheme      The authentication scheme.
	 * @param string $token       The session the cookie belongs to.
	 * @return void
	 */
	public function record_issued_cookie( $auth_cookie, $expire, $expiration, $user_id, $scheme, $token ): void {
		$this->issued_cookies[] = compact( 'auth_cookie', 'expire', 'expiration', 'user_id', 'scheme', 'token' );
	}

	/**
	 * The last authentication cookie issued during the test.
	 *
	 * @return array Empty when no cookie was issued.
	 */
	protected function issued_auth_cookie(): array {
		return $this->issued_cookies ? end( $this->issued_cookies ) : array();
	}

	/**
	 * Gives the user a session, and the request the cookie that names it.
	 *
	 * The SSO reads the session out of the logged-in cookie when it mints a
	 * token, so both halves have to be in place.
	 *
	 * @param int $lifetime How long the session lasts, in seconds. Short of the
	 *                      two days at which a session counts as remember-me.
	 * @return string The session token.
	 */
	protected function log_the_user_in( int $lifetime = DAY_IN_SECONDS ): string {
		$expiration = time() + $lifetime;
		$session    = WP_Session_Tokens::get_instance( $this->user->ID )->create( $expiration );

		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $this->user->ID, $expiration, 'logged_in', $session );

		wp_set_current_user( $this->user->ID );

		return $session;
	}

	/**
	 * Drops everything the SSO host knew, leaving only the token in the URL.
	 *
	 * The destination is a different site: its request carries no cookie from
	 * the login host, and nobody is logged in on it yet.
	 *
	 * @return void
	 */
	protected function arrive_at_the_destination_host(): void {
		$_COOKIE = array();

		wp_set_current_user( 0 );
	}

	/**
	 * Serves a remote-login request, whatever it decides to do about the token.
	 *
	 * A hand-off may write a ticket cookie, and setcookie() warns under CLI.
	 *
	 * @param WPOrg_SSO_Test_Double $sso The instance serving the request.
	 * @return WPOrg_SSO_Redirect_Exception The redirect the request ended in.
	 */
	protected function redeem( WPOrg_SSO_Test_Double $sso ): WPOrg_SSO_Redirect_Exception {
		return $this->without_header_warnings(
			function () use ( $sso ) {
				return $this->catch_redirect( array( $sso, 'maybe_perform_remote_login' ) );
			}
		);
	}


	/**
	 * A token names its user, expiry, remember-me flag, and session.
	 *
	 * @return void
	 */
	public function test_token_carries_the_session_it_was_minted_for(): void {
		$session = $this->log_the_user_in();

		$sso = $this->make_sso( 'login.wordpress.org' );

		$token = $sso->generate_remote_token( $this->user, 'wordcamp.org' );

		list( $user_id, $hash, $valid_until, $remember_me, $session_token ) = explode( '|', $token, 5 );

		$this->assertSame( (string) $this->user->ID, $user_id );
		$this->assertNotEmpty( $hash );
		$this->assertGreaterThan( time(), (int) $valid_until );
		$this->assertLessThanOrEqual( time() + WPOrg_SSO::REMOTE_TOKEN_TIMEOUT, (int) $valid_until );
		$this->assertSame( '', $remember_me );
		$this->assertSame( $session, $session_token );
	}

	/**
	 * A token minted during login carries the session that login just created.
	 *
	 * Revoking that session has to reach the destination too.
	 */
	public function test_token_carries_the_session_a_fresh_login_created(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->log_the_user_in_for_this_request();

		$this->assertSame( '', wp_get_session_token(), 'The session has to be unreadable from the request for this to test the fallback.' );

		$token = $sso->generate_remote_token( $this->user, 'wordcamp.org' );

		$session = $this->issued_auth_cookie()['token'] ?? '';

		$this->assertNotEmpty( $session );
		$this->assertSame( $session, explode( '|', $token, 5 )[4] );
	}

	/**
	 * A token never carries a session belonging to somebody else.
	 *
	 * The record the fallback reads names whoever was last logged in.
	 */
	public function test_token_does_not_borrow_another_accounts_session(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$other = new WP_User( $this->factory->user->create( array( 'user_login' => 'sso-someone-else' ) ) );

		$this->without_header_warnings(
			function () use ( $other ): void {
				wp_set_auth_cookie( $other->ID );
			}
		);

		$token = $sso->generate_remote_token( $this->user, 'wordcamp.org' );

		$this->assertNotEmpty( $this->issued_auth_cookie(), 'A session has to have been recorded for this to test the guard.' );
		$this->assertSame( '', explode( '|', $token, 5 )[4] );
	}

	/**
	 * The session a login hands off is the one destroying it reaches.
	 */
	public function test_a_fresh_login_hands_off_a_session_it_can_destroy(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->log_the_user_in_for_this_request();

		$redirect = $sso->maybe_add_remote_login_bounce( 'https://wordcamp.org/schedule/?sso_bounce=' . $this->bounce_fingerprint(), $this->user );

		$_GET['sso_token'] = (string) $this->query_arg( $redirect, 'sso_token' );

		$this->arrive_at_the_destination_host();
		$this->hold_a_bounce_ticket();
		$this->issued_cookies = array();

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$session = $this->issued_auth_cookie()['token'] ?? '';

		$this->assertNotEmpty( $session );

		WP_Session_Tokens::get_instance( $this->user->ID )->destroy( $session );

		$this->assertFalse( WP_Session_Tokens::get_instance( $this->user->ID )->verify( $session ) );
		$this->assertEmpty( WP_Session_Tokens::get_instance( $this->user->ID )->get_all(), 'A session the hand-off invented would survive here.' );
	}

	/**
	 * The session in the token is the one the hand-off was signed for.
	 *
	 * @return void
	 */
	public function test_token_session_cannot_be_swapped(): void {
		$this->log_the_user_in();

		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		$other = WP_Session_Tokens::get_instance( $this->user->ID )->create( time() + HOUR_IN_SECONDS );

		$parts    = explode( '|', $token );
		$parts[4] = $other;

		$this->assertFalse( $this->make_sso( 'wordcamp.org' )->validate_remote_token( implode( '|', $parts ) )['valid'] );
	}

	/**
	 * The expiry is signed, so it cannot be extended in place.
	 *
	 * The rewritten expiry has to stay inside the acceptance window, or the
	 * clock check refuses the token and says nothing about whether the
	 * signature covers it. Unsigned, this is a token that never expires.
	 *
	 * @return void
	 */
	public function test_the_expiry_is_signed(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		$parts    = explode( '|', $token );
		$parts[2] = (string) ( (int) $parts[2] - 10 );

		$validated = $this->make_sso( 'wordcamp.org' )->validate_remote_token( implode( '|', $parts ) );

		$this->assertGreaterThanOrEqual( time(), (int) $parts[2], 'The rewritten expiry has to stay in the window to test the signature.' );
		$this->assertFalse( $validated['valid'] );
	}

	/**
	 * The remember-me flag is signed, so a short session cannot be promoted.
	 *
	 * @return void
	 */
	public function test_the_remember_me_flag_is_signed(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		$parts    = explode( '|', $token );
		$parts[3] = '1';

		$this->assertSame( '', explode( '|', $token )[3], 'The token under test has to start out not-remembered.' );
		$this->assertFalse( $this->make_sso( 'wordcamp.org' )->validate_remote_token( implode( '|', $parts ) )['valid'] );
	}

	/**
	 * A remember-me login mints a token that says so.
	 *
	 * @return void
	 */
	public function test_token_records_a_remember_me_login(): void {
		$_POST['rememberme'] = 'forever';

		$sso = $this->make_sso( 'login.wordpress.org' );

		$token = $sso->generate_remote_token( $this->user, 'wordcamp.org' );

		$this->assertSame( '1', explode( '|', $token )[3] );
	}

	/**
	 * A long-lived session is a remember-me login, whatever the form posted.
	 *
	 * The hand-off happens on a later request than the login, so the checkbox is
	 * gone by then and the session's own length is what is left to read.
	 *
	 * @return void
	 */
	public function test_token_reads_remember_me_from_a_long_lived_session(): void {
		$this->log_the_user_in( 14 * DAY_IN_SECONDS );

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( '1', explode( '|', $sso->generate_remote_token( $this->user, 'wordcamp.org' ) )[3] );
	}

	/**
	 * A short session is not mistaken for a remember-me login.
	 *
	 * @return void
	 */
	public function test_token_does_not_infer_remember_me_from_a_short_session(): void {
		$this->log_the_user_in( HOUR_IN_SECONDS );

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( '', explode( '|', $sso->generate_remote_token( $this->user, 'wordcamp.org' ) )[3] );
	}

	/**
	 * A token minted for one host validates there.
	 *
	 * @return void
	 */
	public function test_token_validates_on_the_host_it_was_minted_for(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		$validated = $this->make_sso( 'wordcamp.org' )->validate_remote_token( $token );

		$this->assertTrue( $validated['valid'] );
		$this->assertSame( $this->user->ID, $validated['user']->ID );
	}

	/**
	 * Tokens follow a host family through its canonical redirects.
	 *
	 * A WordCamp login lands on 2023.us.wordcamp.org and is redirected to
	 * us.wordcamp.org; the token has to survive that hop.
	 *
	 * @return void
	 */
	public function test_token_validates_across_the_same_host_family(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, '2023.us.wordcamp.org' );

		$this->assertTrue( $this->make_sso( 'us.wordcamp.org' )->validate_remote_token( $token )['valid'] );
	}

	/**
	 * A token for one project is worthless on another.
	 *
	 * @return void
	 */
	public function test_token_does_not_validate_on_another_host_family(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		$this->assertFalse( $this->make_sso( 'bbpress.org' )->validate_remote_token( $token )['valid'] );
		$this->assertFalse( $this->make_sso( 'wordpress.org' )->validate_remote_token( $token )['valid'] );
	}

	/**
	 * The host a token is bound to ignores case and port, as hostnames do.
	 *
	 * @return void
	 */
	public function test_token_host_is_normalized(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		$this->assertSame( 'wordcamp.org', $sso->normalize_token_host( 'WordCamp.org' ) );
		$this->assertSame( 'wordcamp.org', $sso->normalize_token_host( 'wordcamp.org:8888' ) );

		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'WORDCAMP.ORG:443' );

		$this->assertTrue( $sso->validate_remote_token( $token )['valid'] );
	}

	/**
	 * An expired token is refused.
	 *
	 * @return void
	 */
	public function test_expired_token_is_refused(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		$this->assertFalse( $sso->validate_remote_token( $this->forge_token( time() - 1 ) )['valid'] );
	}

	/**
	 * A token dated further ahead than the SSO would ever mint is refused.
	 *
	 * A far-future expiry is the shape a forged or replayed-forward token takes,
	 * so the window is checked at both ends.
	 *
	 * @return void
	 */
	public function test_token_from_beyond_the_minting_window_is_refused(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		$beyond = time() + WPOrg_SSO::REMOTE_TOKEN_TIMEOUT + WPOrg_SSO::REMOTE_TOKEN_CLOCK_SKEW + 60;

		$this->assertFalse( $sso->validate_remote_token( $this->forge_token( $beyond ) )['valid'] );
	}

	/**
	 * A token minted a little ahead of this host's clock still works.
	 *
	 * @return void
	 */
	public function test_token_within_the_clock_skew_is_accepted(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		$skewed = time() + WPOrg_SSO::REMOTE_TOKEN_TIMEOUT + ( WPOrg_SSO::REMOTE_TOKEN_CLOCK_SKEW - 5 );

		$this->assertTrue( $sso->validate_remote_token( $this->forge_token( $skewed ) )['valid'] );
	}

	/**
	 * A tampered signature is refused.
	 *
	 * @return void
	 */
	public function test_tampered_signature_is_refused(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		$parts    = explode( '|', $token );
		$parts[1] = str_repeat( 'a', strlen( $parts[1] ) );

		$this->assertFalse( $this->make_sso( 'wordcamp.org' )->validate_remote_token( implode( '|', $parts ) )['valid'] );
	}

	/**
	 * A token cannot be re-pointed at another user.
	 *
	 * @return void
	 */
	public function test_token_cannot_be_reassigned_to_another_user(): void {
		$other = $this->factory->user->create();

		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		$parts    = explode( '|', $token );
		$parts[0] = (string) $other;

		$validated = $this->make_sso( 'wordcamp.org' )->validate_remote_token( implode( '|', $parts ) );

		$this->assertFalse( $validated['valid'] );
	}

	/**
	 * A token for a user who no longer exists is refused.
	 *
	 * @return void
	 */
	public function test_token_for_a_missing_user_is_refused(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		$parts    = explode( '|', $token );
		$parts[0] = '99999999';

		$validated = $this->make_sso( 'wordcamp.org' )->validate_remote_token( implode( '|', $parts ) );

		$this->assertFalse( $validated['valid'] );
		$this->assertFalse( $validated['user'] );
	}

	/**
	 * Changing the password invalidates tokens already in flight.
	 *
	 * @return void
	 */
	public function test_password_change_invalidates_outstanding_tokens(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		wp_set_password( 'a-brand-new-password', $this->user->ID );
		clean_user_cache( $this->user->ID );

		$this->assertFalse( $this->make_sso( 'wordcamp.org' )->validate_remote_token( $token )['valid'] );
	}

	/**
	 * The signature covers the session, so it cannot be swapped for another.
	 *
	 * @return void
	 */
	public function test_signature_covers_the_session_token(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		$valid_until = time() + 60;

		$this->assertNotSame(
			$sso->generate_remote_token_hash( $this->user, $valid_until, false, 'session-one', 'wordcamp.org' ),
			$sso->generate_remote_token_hash( $this->user, $valid_until, false, 'session-two', 'wordcamp.org' )
		);
	}

	/**
	 * A token that is not five fields is not a token at all.
	 *
	 * @return void
	 */
	public function test_malformed_token_stops_the_request(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		$this->expectException( WPOrg_SSO_Die_Exception::class );

		$sso->validate_remote_token( 'not-a-token' );
	}

	/**
	 * A token may only be claimed once.
	 *
	 * @return void
	 */
	public function test_token_can_only_be_claimed_once(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		$this->assertTrue( $sso->claim_remote_token( 'a-signature' ) );
		$this->assertFalse( $sso->claim_remote_token( 'a-signature' ) );
	}

	/**
	 * Claiming one token does not spend another.
	 *
	 * @return void
	 */
	public function test_claiming_a_token_leaves_others_alone(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		$this->assertTrue( $sso->claim_remote_token( 'a-signature' ) );
		$this->assertTrue( $sso->claim_remote_token( 'another-signature' ) );
	}

	/**
	 * A valid token logs the user in on the host it was issued for.
	 *
	 * The session has to outlive the request, so the auth cookie is what is
	 * asserted on: setting the current user alone would be forgotten the moment
	 * the redirect lands.
	 *
	 * @return void
	 */
	public function test_remote_login_starts_a_session(): void {
		$session = $this->log_the_user_in();

		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $this->bounce_fingerprint() );

		$this->arrive_at_the_destination_host();
		$this->hold_a_bounce_ticket();

		$redirect = $this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( $this->user->ID, get_current_user_id() );
		$this->assertSame( 'https://wordcamp.org/schedule/', $redirect->to );

		$cookie = $this->issued_auth_cookie();

		$this->assertNotEmpty( $cookie, 'No authentication cookie was issued, so nothing would survive the redirect.' );
		$this->assertSame( $this->user->ID, $cookie['user_id'] );
		$this->assertSame( $this->user->ID, wp_validate_auth_cookie( $cookie['auth_cookie'], $cookie['scheme'] ) );

		$this->assertSame( $session, $cookie['token'] );
		$this->assertTrue( WP_Session_Tokens::get_instance( $this->user->ID )->verify( $session ) );
	}

	/**
	 * Replaying a token gets the redirect but no session.
	 *
	 * @return void
	 */
	public function test_replayed_remote_login_does_not_start_a_session(): void {
		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $this->hold_a_bounce_ticket() );

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		wp_set_current_user( 0 );
		$this->issued_cookies = array();

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( 0, get_current_user_id() );
		$this->assertEmpty( $this->issued_auth_cookie() );
	}

	/**
	 * A token that does not validate here logs nobody in.
	 *
	 * @return void
	 */
	public function test_remote_login_with_a_foreign_token_starts_no_session(): void {
		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'bbpress.org', $this->hold_a_bounce_ticket() );

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( 0, get_current_user_id() );
		$this->assertEmpty( $this->issued_auth_cookie() );
	}

	/**
	 * A token is refused by a browser that never got the ticket it was minted for.
	 *
	 * @return void
	 */
	public function test_token_is_refused_without_the_ticket(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $this->bounce_fingerprint() );

		$this->assertFalse( $this->make_sso( 'wordcamp.org' )->validate_remote_token( $token )['valid'] );
	}

	/**
	 * A token is refused by a browser holding some other ticket.
	 *
	 * @return void
	 */
	public function test_token_is_refused_with_another_browsers_ticket(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $this->bounce_fingerprint() );

		$validated = $this->make_sso( 'wordcamp.org' )->validate_remote_token( $token, hash( 'sha256', 'someone-elses-ticket' ) );

		$this->assertFalse( $validated['valid'] );
	}

	/**
	 * A token validates for the browser holding the ticket it was minted against.
	 *
	 * @return void
	 */
	public function test_token_validates_for_the_browser_that_asked_for_it(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $this->bounce_fingerprint() );

		$this->assertTrue( $this->make_sso( 'wordcamp.org' )->validate_remote_token( $token, $this->bounce_fingerprint() )['valid'] );
	}

	/**
	 * The fingerprint the destination sent is signed, and then dropped.
	 *
	 * @return void
	 */
	public function test_the_minted_token_is_bound_to_the_fingerprint_it_was_given(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$redirect = $sso->maybe_add_remote_login_bounce( 'https://wordcamp.org/schedule/?sso_bounce=' . $this->bounce_fingerprint(), $this->user );

		$this->assertNull( $this->query_arg( $redirect, 'sso_bounce' ), 'The destination compares against its own cookie and never needs the fingerprint back.' );

		$token = (string) $this->query_arg( $redirect, 'sso_token' );

		$this->assertTrue( $this->make_sso( 'wordcamp.org' )->validate_remote_token( $token, $this->bounce_fingerprint() )['valid'] );
		$this->assertFalse( $this->make_sso( 'wordcamp.org' )->validate_remote_token( $token )['valid'] );
	}

	/**
	 * A hand-off link passed to somebody else logs them in as nobody.
	 *
	 * The whole point of the ticket: a token is only good in the browser it names.
	 *
	 * @return void
	 */
	public function test_a_shared_hand_off_link_starts_no_session(): void {
		$minted = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', hash( 'sha256', 'the-minting-browsers-ticket' ) );

		// A second browser, holding a ticket of its own.
		$this->hold_a_bounce_ticket();

		$_GET['sso_token'] = $minted;

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( 0, get_current_user_id() );
		$this->assertEmpty( $this->issued_auth_cookie() );
	}

	/**
	 * A token arriving at a browser with no ticket at all starts no session.
	 *
	 * @return void
	 */
	public function test_an_unticketed_browser_starts_no_session(): void {
		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( 0, get_current_user_id() );
		$this->assertEmpty( $this->issued_auth_cookie() );
	}

	/**
	 * Redeeming a hand-off takes the ticket back.
	 */
	public function test_a_redeemed_hand_off_spends_the_ticket(): void {
		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $this->hold_a_bounce_ticket() );

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( $this->user->ID, get_current_user_id(), 'The hand-off has to succeed for this to test the spending.' );
		$this->assertFalse( $this->make_sso( 'wordcamp.org' )->claim_bounce_fingerprint( $this->bounce_fingerprint() ) );
	}

	/**
	 * A hand-off that cannot claim its token leaves the ticket unspent.
	 *
	 * The ticket belongs to whichever hand-off was really minted against it, and
	 * a token nobody can redeem is not that hand-off.
	 */
	public function test_a_hand_off_that_cannot_claim_its_token_leaves_the_ticket(): void {
		$fingerprint = $this->hold_a_bounce_ticket();

		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $fingerprint );

		// Already redeemed elsewhere, so the signature holds but the claim does not.
		$this->make_sso( 'wordcamp.org' )->claim_remote_token( explode( '|', $token, 5 )[1] );

		$_GET['sso_token'] = $token;

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( 0, get_current_user_id(), 'The hand-off has to be refused for this to test anything.' );
		$this->assertTrue( $this->make_sso( 'wordcamp.org' )->claim_bounce_fingerprint( $fingerprint ) );
	}

	/**
	 * A hand-off spends its own ticket, not whichever one it happens to find.
	 *
	 * Two logins can overlap in one browser, sharing the one cookie.
	 */
	public function test_a_stale_hand_off_leaves_a_newer_logins_ticket_alone(): void {
		// A login in flight, whose answer this ticket is waiting for.
		$pending = $this->hold_a_bounce_ticket();

		// An older hand-off, out of restarts, arriving in the meantime.
		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', hash( 'sha256', 'an-older-ticket' ) );
		$_GET['sso_retry'] = '1';

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $pending );

		unset( $_GET['sso_retry'] );

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( $this->user->ID, get_current_user_id() );
	}

	/**
	 * Claiming a ticket leaves the cookie carrying it in place.
	 *
	 * The claim is recorded against the fingerprint, not by clearing the cookie,
	 * which the browser shares between logins and would erase for both.
	 */
	public function test_a_redeemed_hand_off_leaves_the_cookie_in_place(): void {
		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $this->hold_a_bounce_ticket() );

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( $this->user->ID, get_current_user_id(), 'The hand-off has to succeed for this to test the spending.' );
		$this->assertSame( self::BOUNCE_TICKET, $this->held_bounce_ticket() );
	}

	/**
	 * A ticket is claimed even against a cache that keeps its misses.
	 *
	 * Reading the key before claiming it loses the claim on such a cache, and
	 * loses it quietly: the claim fails open, nothing is recorded, and the
	 * ticket goes on being good.
	 */
	public function test_a_ticket_is_claimed_against_a_cache_that_keeps_misses(): void {
		$fingerprint = $this->hold_a_bounce_ticket();

		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $fingerprint );

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Standing in a cache that keeps misses, then putting the real one back.
		$real_cache                 = $GLOBALS['wp_object_cache'];
		$GLOBALS['wp_object_cache'] = new WPOrg_SSO_Negative_Caching_Cache( $real_cache );

		try {
			$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

			$this->assertSame( $this->user->ID, get_current_user_id(), 'The hand-off has to be accepted for its claim to be worth testing.' );

			// A second token against the ticket the first hand-off answered.
			$stranger = new WP_User( $this->factory->user->create( array( 'user_login' => 'sso-second' ) ) );

			$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $stranger, 'wordcamp.org', $fingerprint );
			$_GET['sso_retry'] = '1';

			wp_set_current_user( 0 );
			$this->issued_cookies = array();

			$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );
		} finally {
			$GLOBALS['wp_object_cache'] = $real_cache;
		}
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( 0, get_current_user_id() );
		$this->assertEmpty( $this->issued_auth_cookie() );
	}

	/**
	 * A hand-off that loses the race for a ticket starts no session.
	 *
	 * Both claims are taken before anyone is logged in, so two tokens signed
	 * against one ticket cannot both come out of a redemption authenticated.
	 */
	public function test_a_hand_off_that_loses_the_race_for_a_ticket_starts_no_session(): void {
		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $this->hold_a_bounce_ticket() );
		$_GET['sso_retry'] = '1';

		$sso = $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' );

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Standing in a contended cache, then putting the real one back.
		$real_cache                 = $GLOBALS['wp_object_cache'];
		$GLOBALS['wp_object_cache'] = new WPOrg_SSO_Contended_Cache( $real_cache );

		try {
			$this->redeem( $sso );
		} finally {
			$GLOBALS['wp_object_cache'] = $real_cache;
		}
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertSame( 0, get_current_user_id() );
		$this->assertEmpty( $this->issued_auth_cookie() );
	}

	/**
	 * A cache outage leaves ticketed hand-offs working.
	 *
	 * The ticket claim gates the login, so failing it closed would take every
	 * cross-domain login down with the cache.
	 */
	public function test_a_cache_outage_does_not_block_ticketed_hand_offs(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Standing the cache down for one call, then putting it back.
		$real_cache                 = $GLOBALS['wp_object_cache'];
		$GLOBALS['wp_object_cache'] = new WPOrg_SSO_Unreachable_Cache();

		try {
			$claimed = $sso->claim_bounce_fingerprint( $this->bounce_fingerprint() );
		} finally {
			$GLOBALS['wp_object_cache'] = $real_cache;
		}
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertTrue( $claimed );
	}

	/**
	 * The ticket cookie is sent with a lifetime, not left to the browser session.
	 *
	 * A ticket that never lapsed would be honoured again the moment its claim
	 * aged out of the cache.
	 */
	public function test_the_ticket_cookie_is_sent_with_a_lifetime(): void {
		$sso = $this->make_sso( 'wordcamp.org', '/wp-login.php', '/wp-login.php' );

		$sso->issue_bounce_ticket();

		$this->assertCount( 1, $sso->bounce_cookies );

		$options = $sso->bounce_cookies[0]['options'];

		$this->assertGreaterThan( time(), $options['expires'], 'A ticket left to the browser session outlives every record of it being spent.' );
		$this->assertLessThanOrEqual( time() + WPOrg_SSO::REMOTE_BOUNCE_TIMEOUT, $options['expires'] );
		$this->assertGreaterThan( time() + WPOrg_SSO::REMOTE_TOKEN_TIMEOUT, $options['expires'], 'A ticket has to outlast the login it is waiting for.' );
	}

	/**
	 * A ticket's claim outlives the cookie that carries it.
	 *
	 * The cookie can be presented until it lapses, so the record of it being
	 * spent has to still be there when it is.
	 */
	public function test_a_ticket_claim_outlives_the_ticket(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Watching one call, then putting the cache back.
		$real_cache                 = $GLOBALS['wp_object_cache'];
		$recording                  = new WPOrg_SSO_Recording_Cache( $real_cache );
		$GLOBALS['wp_object_cache'] = $recording;

		try {
			$sso->claim_bounce_fingerprint( $this->bounce_fingerprint() );
		} finally {
			$GLOBALS['wp_object_cache'] = $real_cache;
		}
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$ttl = $recording->expiries[ 'bounce_' . $this->bounce_fingerprint() ] ?? 0;

		$this->assertGreaterThan( WPOrg_SSO::REMOTE_BOUNCE_TIMEOUT, $ttl, 'A claim that lapses first lets the ticket come back unspent.' );
	}

	/**
	 * A fingerprint authorises the one hand-off it was issued for.
	 */
	public function test_a_token_minted_against_a_spent_fingerprint_starts_no_session(): void {
		$fingerprint = $this->hold_a_bounce_ticket();

		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $fingerprint );

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		// A second account, minting against the same fingerprint.
		$stranger = new WP_User( $this->factory->user->create( array( 'user_login' => 'sso-stranger' ) ) );

		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $stranger, 'wordcamp.org', $fingerprint );
		$_GET['sso_retry'] = '1';

		wp_set_current_user( 0 );
		$this->issued_cookies = array();

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( 0, get_current_user_id() );
		$this->assertEmpty( $this->issued_auth_cookie() );
	}

	/**
	 * A restart leaves a ticket behind, rather than spending the one it replaces.
	 */
	public function test_a_restarted_hand_off_swaps_the_ticket_rather_than_spending_it(): void {
		$this->hold_a_bounce_ticket();

		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', hash( 'sha256', 'another-browsers-ticket' ) );

		$redirect = $this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$destination = (string) $this->query_arg( $redirect->to, 'redirect_to' );
		$ticket      = $this->held_bounce_ticket();

		$this->assertNotEmpty( $ticket, 'A restart the browser cannot answer is a round trip for nothing.' );
		$this->assertNotSame( self::BOUNCE_TICKET, $ticket );
		$this->assertSame( hash( 'sha256', $ticket ), $this->query_arg( $destination, 'sso_bounce' ) );
	}

	/**
	 * A hand-off that cannot be accepted is asked for again, with a ticket.
	 *
	 * @return void
	 */
	public function test_an_unaccepted_hand_off_is_restarted_with_a_ticket(): void {
		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		$redirect = $this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertStringStartsWith( 'https://login.wordpress.org/', $redirect->to );

		$destination = (string) $this->query_arg( $redirect->to, 'redirect_to' );

		$this->assertStringStartsWith( 'https://wordcamp.org/schedule/', $destination );
		$this->assertSame( '1', $this->query_arg( $destination, 'sso_retry' ) );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Hashed below, as the SSO does.
		$ticket = wp_unslash( $_COOKIE[ WPOrg_SSO::REMOTE_BOUNCE_COOKIE ] ?? '' );

		$this->assertNotEmpty( $ticket, 'The restart has to leave the browser a ticket to be asked for one.' );
		$this->assertSame( hash( 'sha256', $ticket ), $this->query_arg( $destination, 'sso_bounce' ) );
	}

	/**
	 * A hand-off naming a missing user is restarted like any other.
	 *
	 * Restarting only for real users would make the response reveal which exist.
	 *
	 * @return void
	 */
	public function test_a_hand_off_for_a_missing_user_is_restarted_like_any_other(): void {
		$missing = $this->user->ID + 100000;

		$this->assertFalse( get_user_by( 'id', $missing ), 'The user has to be missing for this to test anything.' );

		$_GET['sso_token'] = implode( '|', array( (string) $missing, str_repeat( 'a', 64 ), (string) ( time() + 60 ), '', '' ) );

		$missing_user = $this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );

		$real_user = $this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertStringStartsWith( 'https://login.wordpress.org/', $missing_user->to );
		$this->assertSame(
			$this->query_arg( $real_user->to, 'from' ),
			$this->query_arg( $missing_user->to, 'from' ),
			'A missing user has to be indistinguishable from a real one.'
		);
	}

	/**
	 * A hand-off headed for another host is landed there, not asked for again.
	 *
	 * The ticket a restart issues is host-only, so it could never reach that host.
	 *
	 * @return void
	 */
	public function test_a_hand_off_bound_for_another_host_is_not_restarted(): void {
		$_GET['sso_token']   = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );
		$_GET['redirect_to'] = 'https://buddypress.org/';

		$redirect = $this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( 'https://buddypress.org/', $redirect->to );
		$this->assertSame( 0, get_current_user_id(), 'A hand-off nobody could redeem has to leave the browser logged out.' );
		$this->assertArrayNotHasKey( WPOrg_SSO::REMOTE_BOUNCE_COOKIE, $_COOKIE );
	}

	/**
	 * A hand-off off the network is not turned into a round trip through the SSO host.
	 *
	 * Where it lands instead is `_safe_redirect()`'s to decide, and this double replaces that.
	 *
	 * @return void
	 */
	public function test_a_hand_off_cannot_be_restarted_off_the_network(): void {
		$_GET['sso_token']   = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );
		$_GET['redirect_to'] = 'https://example.org/';

		$redirect = $this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertStringStartsNotWith( 'https://login.wordpress.org/', $redirect->to );
		$this->assertArrayNotHasKey( WPOrg_SSO::REMOTE_BOUNCE_COOKIE, $_COOKIE );
	}

	/**
	 * A hand-off on wordpress.org is landed; those hosts already share the login cookie.
	 *
	 * @return void
	 */
	public function test_a_hand_off_on_wordpress_org_is_not_restarted(): void {
		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'profiles.wordpress.org' );

		$redirect = $this->redeem( $this->make_sso( 'profiles.wordpress.org', '/index.php', '/me/' ) );

		$this->assertSame( 'https://profiles.wordpress.org/me/', $redirect->to );
		$this->assertArrayNotHasKey( WPOrg_SSO::REMOTE_BOUNCE_COOKIE, $_COOKIE );
	}

	/**
	 * A hand-off this browser already spent is landed, not asked for again.
	 *
	 * Its ticket signs the token, so a restart would only mint what it holds.
	 *
	 * @return void
	 */
	public function test_a_spent_hand_off_is_not_restarted(): void {
		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $this->hold_a_bounce_ticket() );

		$this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		wp_set_current_user( 0 );

		$redirect = $this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( 'https://wordcamp.org/schedule/', $redirect->to );
	}

	/**
	 * A hand-off is restarted once, so a browser that cannot keep the ticket lands.
	 *
	 * @return void
	 */
	public function test_a_restarted_hand_off_is_not_restarted_again(): void {
		$_GET['sso_token'] = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );
		$_GET['sso_retry'] = '1';

		$redirect = $this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( 'https://wordcamp.org/schedule/', $redirect->to );
		$this->assertSame( 0, get_current_user_id() );
	}

	/**
	 * A hand-off nobody could have asked for is not worth a round trip.
	 *
	 * @return void
	 */
	public function test_an_expired_hand_off_is_not_restarted(): void {
		$_GET['sso_token'] = $this->forge_token( time() - 1 );

		$redirect = $this->redeem( $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' ) );

		$this->assertSame( 'https://wordcamp.org/schedule/', $redirect->to );
	}

	/**
	 * The spent token is stripped from the URL the user lands on.
	 *
	 * @return void
	 */
	public function test_remote_login_strips_the_token_from_the_landing_url(): void {
		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org', $this->hold_a_bounce_ticket() );

		$_GET['sso_token'] = $token;

		$sso = $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/?sso_token=' . rawurlencode( $token ) );

		$redirect = $this->redeem( $sso );

		$this->assertStringNotContainsString( 'sso_token', $redirect->to );
	}

	/**
	 * Landing back on a login screen would start the hand-off over again.
	 *
	 * @return void
	 */
	public function test_remote_login_never_lands_on_a_login_screen(): void {
		$_GET['sso_token']   = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'wordcamp.org' );
		$_GET['redirect_to'] = 'https://wordcamp.org/wp-login.php';

		$sso = $this->make_sso( 'wordcamp.org', '/index.php', '/schedule/' );

		$redirect = $this->catch_redirect( array( $sso, 'maybe_perform_remote_login' ) );

		$this->assertStringNotContainsString( 'wp-login.php', $redirect->to );
	}

	/**
	 * A post-login redirect off to a sibling project carries a token.
	 *
	 * @return void
	 */
	public function test_post_login_redirect_to_a_sibling_project_gets_a_token(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$redirect = $sso->maybe_add_remote_login_bounce( 'https://bbpress.org/forums/', $this->user );

		$this->assertNotNull( $this->query_arg( $redirect, 'sso_token' ) );
		$this->assertTrue( $this->make_sso( 'bbpress.org' )->validate_remote_token( (string) $this->query_arg( $redirect, 'sso_token' ) )['valid'] );
	}

	/**
	 * An insecure target is upgraded before the token is attached to it.
	 *
	 * @return void
	 */
	public function test_post_login_redirect_is_upgraded_to_https(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$redirect = $sso->maybe_add_remote_login_bounce( 'http://bbpress.org/forums/', $this->user );

		$this->assertStringStartsWith( 'https://bbpress.org/forums/', $redirect );
	}

	/**
	 * Sites on wordpress.org share the auth cookie, so they need no token.
	 *
	 * @return void
	 */
	public function test_post_login_redirect_within_wordpress_org_gets_no_token(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$redirect = $sso->maybe_add_remote_login_bounce( 'https://make.wordpress.org/core/', $this->user );

		$this->assertSame( 'https://make.wordpress.org/core/', $redirect );
	}

	/**
	 * The SSO host never hands a token to itself.
	 *
	 * Reached on local installs, where every host is the SSO host; in production
	 * the wordpress.org rule gets there first.
	 *
	 * @return void
	 */
	public function test_post_login_redirect_to_the_sso_host_gets_no_token(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		// As a local install is configured: the site is its own SSO host.
		$sso->sso_host = 'wordcamp.org';

		$redirect = $sso->maybe_add_remote_login_bounce( 'https://wordcamp.org/wp-admin/', $this->user );

		$this->assertSame( 'https://wordcamp.org/wp-admin/', $redirect );
	}

	/**
	 * The SSO host is recognised as itself even when it carries a port.
	 *
	 * Local installs put the port in `sso_host`; a parsed redirect host never has
	 * one, so comparing the two raw never matches.
	 *
	 * @return void
	 */
	public function test_post_login_redirect_to_a_ported_sso_host_gets_no_token(): void {
		$sso = $this->make_sso( 'wordcamp.org:8888' );

		$sso->sso_host = 'wordcamp.org:8888';

		$redirect = $sso->maybe_add_remote_login_bounce( 'https://wordcamp.org/wp-admin/', $this->user );

		$this->assertSame( 'https://wordcamp.org/wp-admin/', $redirect );
	}

	/**
	 * A target outside the network never receives a token.
	 *
	 * @return void
	 */
	public function test_post_login_redirect_off_the_network_gets_no_token(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$redirect = $sso->maybe_add_remote_login_bounce( 'https://evil.com/', $this->user );

		$this->assertSame( 'https://evil.com/', $redirect );
	}

	/**
	 * A failed login has no user to mint a token for.
	 *
	 * @return void
	 */
	public function test_failed_login_gets_no_token(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$redirect = $sso->maybe_add_remote_login_bounce( 'https://bbpress.org/forums/', new WP_Error( 'invalid_username', 'Nope.' ) );

		$this->assertSame( 'https://bbpress.org/forums/', $redirect );
	}

	/**
	 * A logout token ends the session it names, on the SSO host.
	 *
	 * @return void
	 */
	public function test_remote_logout_destroys_the_named_session(): void {
		$here    = $this->log_the_user_in();
		$manager = WP_Session_Tokens::get_instance( $this->user->ID );
		$named   = $manager->create( time() + HOUR_IN_SECONDS );

		$sso = $this->make_sso( 'login.wordpress.org' );

		$_GET['sso_logout'] = $this->forge_token( time() + 60, $named, 'login.wordpress.org' );

		$redirect = $this->catch_redirect( array( $sso, 'maybe_perform_remote_logout' ) );

		// The session the token names, and the one holding this request, both end.
		$this->assertFalse( $manager->verify( $named ) );
		$this->assertFalse( $manager->verify( $here ) );
		$this->assertSame( 0, get_current_user_id() );
		$this->assertSame( 'https://login.wordpress.org/loggedout', $redirect->to );
	}

	/**
	 * A logout token logs out the account that asked, and nobody else.
	 */
	public function test_remote_logout_leaves_a_bystander_logged_in(): void {
		$bystander = new WP_User( $this->factory->user->create( array( 'user_login' => 'sso-bystander' ) ) );
		$manager   = WP_Session_Tokens::get_instance( $bystander->ID );
		$session   = $manager->create( time() + HOUR_IN_SECONDS );

		$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $bystander->ID, time() + HOUR_IN_SECONDS, 'logged_in', $session );

		wp_set_current_user( $bystander->ID );

		$_GET['sso_logout'] = $this->forge_token( time() + 60, '', 'login.wordpress.org' );

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->catch_redirect( array( $sso, 'maybe_perform_remote_logout' ) );

		$this->assertSame( $bystander->ID, get_current_user_id() );
		$this->assertTrue( $manager->verify( $session ) );
	}

	/**
	 * A logout token is spent once, so a replay ends no further session.
	 */
	public function test_replayed_remote_logout_destroys_nothing(): void {
		$this->log_the_user_in();

		$manager = WP_Session_Tokens::get_instance( $this->user->ID );
		$named   = $manager->create( time() + HOUR_IN_SECONDS );

		$_GET['sso_logout'] = $this->forge_token( time() + 60, $named, 'login.wordpress.org' );

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->catch_redirect( array( $sso, 'maybe_perform_remote_logout' ) );

		$this->log_the_user_in();

		$replayed = $manager->create( time() + HOUR_IN_SECONDS );

		$sso = $this->make_sso( 'login.wordpress.org' );

		$redirect = $this->catch_redirect( array( $sso, 'maybe_perform_remote_logout' ) );

		$this->assertSame( 'https://login.wordpress.org/loggedout', $redirect->to, 'A refreshed logout has to land where the first one did.' );
		$this->assertTrue( $manager->verify( $replayed ) );
		$this->assertSame( $this->user->ID, get_current_user_id() );
	}

	/**
	 * A logout token is only honoured on the SSO host that minted it.
	 *
	 * @return void
	 */
	public function test_remote_logout_is_ignored_off_the_sso_host(): void {
		$manager = WP_Session_Tokens::get_instance( $this->user->ID );
		$session = $manager->create( time() + HOUR_IN_SECONDS );

		$_GET['sso_logout'] = $this->forge_token( time() + 60, $session, 'wordcamp.org' );

		$sso = $this->make_sso( 'wordcamp.org' );

		$this->assert_no_redirect( $sso, array( $sso, 'maybe_perform_remote_logout' ) );

		$this->assertNotNull( $manager->get( $session ) );
	}

	/**
	 * An unvalidatable logout token logs nobody out.
	 *
	 * @return void
	 */
	public function test_remote_logout_with_an_invalid_token_is_ignored(): void {
		$here    = $this->log_the_user_in();
		$manager = WP_Session_Tokens::get_instance( $this->user->ID );
		$named   = $manager->create( time() + HOUR_IN_SECONDS );

		$_GET['sso_logout'] = $this->forge_token( time() - 60, $named, 'login.wordpress.org' );

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assert_no_redirect( $sso, array( $sso, 'maybe_perform_remote_logout' ) );

		$this->assertTrue( $manager->verify( $named ) );
		$this->assertTrue( $manager->verify( $here ) );
		$this->assertSame( $this->user->ID, get_current_user_id() );
	}

	/**
	 * A logout returns the user to where they asked, when that is safe.
	 *
	 * @return void
	 */
	public function test_remote_logout_returns_to_a_valid_request(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$_GET['sso_logout']      = $this->forge_token( time() + 60, '', 'login.wordpress.org' );
		$_REQUEST['redirect_to'] = 'https://wordpress.org/support/';

		// Added by redirect_all_login_or_signup_to_sso() before it reaches the logout handler.
		add_filter( 'allowed_redirect_hosts', array( $sso, 'add_allowed_redirect_host' ) );

		$redirect = $this->catch_redirect( array( $sso, 'maybe_perform_remote_logout' ) );

		$this->assertSame( 'https://wordpress.org/support/', $redirect->to );
	}

	/**
	 * A logout never returns the user to a destination off the network.
	 *
	 * @return void
	 */
	public function test_remote_logout_refuses_a_foreign_return_target(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$_GET['sso_logout']      = $this->forge_token( time() + 60, '', 'login.wordpress.org' );
		$_REQUEST['redirect_to'] = 'https://evil.com/';

		add_filter( 'allowed_redirect_hosts', array( $sso, 'add_allowed_redirect_host' ) );

		$redirect = $this->catch_redirect( array( $sso, 'maybe_perform_remote_logout' ) );

		$this->assertStringStartsWith( 'https://login.wordpress.org/loggedout', $redirect->to );
	}

	/**
	 * The custom salt is what the tokens are signed with.
	 *
	 * @return void
	 */
	public function test_sso_salt_is_used_for_its_own_scheme(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( WPORG_SSO_SALT, $sso->salt( 'the-default-salt', 'wporg_sso' ) );
		$this->assertSame( 'the-default-salt', $sso->salt( 'the-default-salt', 'auth' ) );
	}

	/**
	 * Logging out anywhere on the network hands a logout token to the SSO host.
	 *
	 * @return void
	 */
	public function test_logout_hands_a_token_to_the_sso_host(): void {
		wp_set_current_user( $this->user->ID );

		$_REQUEST['_wpnonce']    = wp_create_nonce( 'log-out' );
		$_REQUEST['redirect_to'] = home_url( '/support/' );

		$sso = $this->make_sso( 'wordpress.org', '/wp-login.php', '/wp-login.php?action=logout' );

		$redirect = $this->catch_redirect( array( $sso, 'login_form_logout' ) );

		$this->assertStringStartsWith( 'https://login.wordpress.org/wp-login.php', $redirect->to );
		$this->assertSame( 'remote-logout', $this->query_arg( $redirect->to, 'action' ) );
		$this->assertSame( home_url( '/support/' ), $this->query_arg( $redirect->to, 'redirect_to' ) );

		$logout_token = (string) $this->query_arg( $redirect->to, 'sso_logout' );

		$this->assertTrue( $this->make_sso( 'login.wordpress.org' )->validate_remote_token( $logout_token )['valid'] );
	}

	/**
	 * A logout token is only good on the SSO host, not back where it was minted.
	 *
	 * @return void
	 */
	public function test_logout_token_is_bound_to_the_sso_host(): void {
		wp_set_current_user( $this->user->ID );

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'log-out' );

		$sso = $this->make_sso( 'wordcamp.org', '/wp-login.php', '/wp-login.php?action=logout' );

		$redirect = $this->catch_redirect( array( $sso, 'login_form_logout' ) );

		$logout_token = (string) $this->query_arg( $redirect->to, 'sso_logout' );

		$this->assertFalse( $this->make_sso( 'wordcamp.org' )->validate_remote_token( $logout_token )['valid'] );
	}

	/**
	 * A logout without a valid nonce goes nowhere.
	 *
	 * @return void
	 */
	public function test_logout_requires_a_nonce(): void {
		wp_set_current_user( $this->user->ID );

		$sso = $this->make_sso( 'wordpress.org', '/wp-login.php', '/wp-login.php?action=logout' );

		$this->expectException( WPOrg_SSO_Die_Exception::class );

		$sso->login_form_logout();
	}

	/**
	 * The hand-off window stays short.
	 *
	 * Every other expiry test derives its boundary from these, so widening one
	 * would go unnoticed. They are a security parameter, not a tuning knob.
	 *
	 * @return void
	 */
	public function test_the_hand_off_window_stays_short(): void {
		$this->assertSame( 300, WPOrg_SSO::REMOTE_TOKEN_TIMEOUT );
		$this->assertSame( 30, WPOrg_SSO::REMOTE_TOKEN_CLOCK_SKEW );
	}

	/**
	 * A cache outage lets tokens through rather than locking everyone out.
	 *
	 * Deliberate: `wp_cache_add()` also fails when the cache is unreachable, and
	 * treating that as "already spent" would break every hand-off on the network
	 * for as long as the outage lasted.
	 *
	 * @return void
	 */
	public function test_a_cache_outage_does_not_block_hand_offs(): void {
		$sso = $this->make_sso( 'wordcamp.org' );

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Standing the cache down for one call, then putting it back.
		$real_cache                 = $GLOBALS['wp_object_cache'];
		$GLOBALS['wp_object_cache'] = new WPOrg_SSO_Unreachable_Cache();

		try {
			$claimed = $sso->claim_remote_token( 'a-signature' );
		} finally {
			$GLOBALS['wp_object_cache'] = $real_cache;
		}
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->assertTrue( $claimed );
	}

	/**
	 * A token that fails host binding is logged once, then deduped.
	 *
	 * @return void
	 */
	public function test_binding_rejects_are_logged_once_per_host(): void {
		remove_filter( 'wporg_sso_log_binding_rejects', '__return_false' );

		// Only a ticketed hand-off that fails after being restarted is worth hearing about.
		$_GET['sso_retry'] = '1';
		$bounce            = $this->hold_a_bounce_ticket();

		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'bbpress.org' );
		$sso   = $this->make_sso( 'wordcamp.org' );

		$logged = $this->capture_warnings(
			function () use ( $sso, $token, $bounce ) {
				$sso->validate_remote_token( $token, $bounce );
				$sso->validate_remote_token( $token, $bounce );
			}
		);

		$this->assertCount( 1, $logged );
		$this->assertStringContainsString( 'host=wordcamp.org', $logged[0] );
	}

	/**
	 * Tokens that validate, and tokens that merely expired, log nothing.
	 *
	 * @return void
	 */
	public function test_ordinary_tokens_are_not_logged(): void {
		remove_filter( 'wporg_sso_log_binding_rejects', '__return_false' );

		// Set, or the assertion would pass on nothing having been logged yet.
		$_GET['sso_retry'] = '1';
		$bounce            = $this->hold_a_bounce_ticket();

		$sso = $this->make_sso( 'wordcamp.org' );

		$logged = $this->capture_warnings(
			function () use ( $sso, $bounce ) {
				$sso->validate_remote_token( $this->forge_token( time() + 60, '', 'wordcamp.org', $bounce ), $bounce );
				$sso->validate_remote_token( $this->forge_token( time() - 60, '', 'wordcamp.org', $bounce ), $bounce );
			}
		);

		$this->assertSame( array(), $logged );
	}

	/**
	 * A retry marker on its own logs nothing, the marker coming from the URL.
	 *
	 * Otherwise anyone could spend the dedup window and mask a real reject.
	 *
	 * @return void
	 */
	public function test_an_unticketed_retry_is_not_logged(): void {
		remove_filter( 'wporg_sso_log_binding_rejects', '__return_false' );

		$_GET['sso_retry'] = '1';

		$token = $this->make_sso( 'login.wordpress.org' )->generate_remote_token( $this->user, 'bbpress.org' );
		$sso   = $this->make_sso( 'wordcamp.org' );

		$logged = $this->capture_warnings(
			function () use ( $sso, $token ) {
				$sso->validate_remote_token( $token );
			}
		);

		$this->assertSame( array(), $logged );
	}

	/**
	 * Runs a callback and returns the warnings it raised.
	 *
	 * @param callable $callback The callback to run.
	 * @return string[]
	 */
	protected function capture_warnings( callable $callback ): array {
		$warnings = array();

		set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Reading the logger under test.
			function ( $errno, $errstr ) use ( &$warnings ) {
				$warnings[] = (string) $errstr;

				return true;
			},
			E_USER_WARNING
		);

		try {
			$callback();
		} finally {
			restore_error_handler();
		}

		return $warnings;
	}

	/**
	 * Builds a well-formed token for this test's user, signed for a given host.
	 *
	 * The token is signed over whatever it is given, including an expiry in the
	 * past. That makes it the wrong tool for testing what the signature covers —
	 * rewrite a field of a genuine token for that.
	 *
	 * @param int    $valid_until   Timestamp the token expires at.
	 * @param string $session_token The session to bind the token to.
	 * @param string $target_host   The host the token is issued for.
	 * @param string $bounce        Fingerprint of the ticket the browser holds.
	 * @return string
	 */
	protected function forge_token( int $valid_until, string $session_token = '', string $target_host = 'wordcamp.org', string $bounce = '' ): string {
		$hash = $this->make_sso( 'login.wordpress.org' )
			->generate_remote_token_hash( $this->user, $valid_until, false, $session_token, $target_host, $bounce );

		return implode( '|', array( (string) $this->user->ID, $hash, (string) $valid_until, '', $session_token ) );
	}

	/**
	 * The bounce ticket the browser currently holds, if any.
	 *
	 * @return string Empty when the browser holds none.
	 */
	protected function held_bounce_ticket(): string {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Compared as an opaque value, as the SSO does.
		return (string) wp_unslash( $_COOKIE[ WPOrg_SSO::REMOTE_BOUNCE_COOKIE ] ?? '' );
	}

	/**
	 * Logs the user in the way a login request does, leaving $_COOKIE untouched.
	 *
	 * `wp_set_auth_cookie()` only emits headers, so the session it makes is unreadable here.
	 *
	 * @see https://core.trac.wordpress.org/ticket/61874
	 */
	protected function log_the_user_in_for_this_request(): void {
		$this->without_header_warnings(
			function (): void {
				wp_set_auth_cookie( $this->user->ID );
			}
		);

		wp_set_current_user( $this->user->ID );
	}
}
