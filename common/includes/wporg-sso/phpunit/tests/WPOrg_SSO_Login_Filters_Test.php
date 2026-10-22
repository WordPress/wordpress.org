<?php
/**
 * Tests for the URL and email filters the SSO installs on every network site.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers the filters that point WordPress's own login, registration, and lost
 * password links at the SSO host, and the messages that go out with them.
 */
class WPOrg_SSO_Login_Filters_Test extends WPOrg_SSO_TestCase {

	/**
	 * Login forms post to the SSO host, not the site the form is rendered on.
	 *
	 * @return void
	 */
	public function test_login_forms_post_to_the_sso_host(): void {
		$sso = $this->make_sso( 'wordpress.org' );

		$this->assertSame(
			'https://login.wordpress.org/wp-login.php',
			$sso->login_post_url( 'https://wordpress.org/wp-login.php', 'wp-login.php', 'login_post' )
		);
	}

	/**
	 * URLs built for anything but the login form are left alone.
	 *
	 * @return void
	 */
	public function test_other_urls_are_not_rewritten(): void {
		$sso = $this->make_sso( 'wordpress.org' );

		$this->assertSame(
			'https://wordpress.org/wp-admin/',
			$sso->login_post_url( 'https://wordpress.org/wp-admin/', 'wp-admin/', 'admin' )
		);
	}

	/**
	 * Forms that have to post back to the site they came from keep their URL.
	 *
	 * @param string $url The form URL that must survive the filter.
	 * @return void
	 */
	#[DataProvider( 'get_local_form_urls' )]
	public function test_site_local_forms_keep_their_url( string $url ): void {
		$sso = $this->make_sso( 'wordpress.org' );

		$this->assertSame( $url, $sso->login_post_url( $url, 'wp-login.php', 'login_post' ) );
	}

	/**
	 * Form URLs that only work on the site that rendered them.
	 *
	 * @return array[]
	 */
	public static function get_local_form_urls(): array {
		return array(
			// The post password belongs to a post on this site.
			'post password'    => array( 'https://wordpress.org/wp-login.php?action=postpass' ),
			// Revalidation applies to the session held on this site.
			'2fa revalidation' => array( 'https://wordpress.org/wp-login.php?action=revalidate_2fa' ),
		);
	}

	/**
	 * Registration always happens on the SSO host.
	 *
	 * @return void
	 */
	public function test_registration_url_is_the_sso_one(): void {
		$this->assertSame( 'https://login.wordpress.org/register', $this->make_sso( 'bbpress.org' )->register_url() );
	}

	/**
	 * Lost password links point at the SSO host's own screen.
	 *
	 * @return void
	 */
	public function test_lost_password_url_is_the_sso_one(): void {
		$sso = $this->make_sso( 'wordpress.org' );

		$this->assertSame( 'https://login.wordpress.org/lostpassword', $sso->lostpassword_url( 'https://wordpress.org/wp-login.php?action=lostpassword', '' ) );

		$url = $sso->lostpassword_url( '', 'https://wordpress.org/support/' );

		$this->assertStringStartsWith( 'https://login.wordpress.org/lostpassword', $url );
		$this->assertSame( 'https://wordpress.org/support/', $this->query_arg( $url, 'redirect_to' ) );
	}

	/**
	 * Links off to the SSO host carry the locale of the site they came from.
	 *
	 * @return void
	 */
	public function test_sso_links_carry_the_locale(): void {
		$sso = $this->make_sso( 'wordpress.org' );

		$german = static fn() => 'de_DE';
		add_filter( 'locale', $german );

		$this->assertSame( 'de_DE', $this->query_arg( $sso->add_locale( 'https://login.wordpress.org/' ), 'locale' ) );

		remove_filter( 'locale', $german );
	}

	/**
	 * A logout lands back on the page the user was reading.
	 *
	 * @return void
	 */
	public function test_logout_returns_to_the_page_the_user_came_from(): void {
		$_SERVER['HTTP_REFERER'] = home_url( '/support/' );

		$sso = $this->make_sso( 'wordpress.org' );

		$this->assertSame( home_url( '/support/' ), $sso->logout_redirect( wp_login_url() ) );
	}

	/**
	 * A logout from the admin lands on the front page, not back in the admin.
	 *
	 * @return void
	 */
	public function test_logout_from_the_admin_returns_home(): void {
		$_SERVER['HTTP_REFERER'] = admin_url( 'users.php' );

		$sso = $this->make_sso( 'wordpress.org' );

		$this->assertSame( home_url( '/' ), $sso->logout_redirect( wp_login_url() ) );
	}

	/**
	 * A logout destination that is not a login screen is left alone.
	 *
	 * @return void
	 */
	public function test_logout_to_a_real_page_is_left_alone(): void {
		$sso = $this->make_sso( 'wordpress.org' );

		$this->assertSame( home_url( '/plugins/' ), $sso->logout_redirect( home_url( '/plugins/' ) ) );
	}

	/**
	 * A logout already heading for the SSO host's own screen is left alone.
	 *
	 * @return void
	 */
	public function test_logout_to_the_sso_host_is_left_alone(): void {
		$_SERVER['HTTP_REFERER'] = home_url( '/support/' );

		$sso = $this->make_sso( 'wordpress.org' );

		// The branch is only reachable when the login URL is on the SSO host, as it is in production.
		$sso->sso_host_url = untrailingslashit( home_url() );

		$this->assertSame( wp_login_url(), $sso->logout_redirect( wp_login_url() ) );
	}

	/**
	 * The login form returns the user to where they asked to go.
	 *
	 * @return void
	 */
	public function test_login_form_keeps_the_requested_destination(): void {
		$_GET['redirect_to'] = 'https://make.wordpress.org/core/';

		$sso = $this->make_sso( 'login.wordpress.org' );

		$defaults = $sso->login_form_defaults( array( 'redirect' => '' ) );

		$this->assertSame( 'https://make.wordpress.org/core/', $defaults['redirect'] );
	}

	/**
	 * Without a destination, the page the user came from is remembered.
	 *
	 * @return void
	 */
	public function test_login_form_falls_back_to_the_referer(): void {
		$_SERVER['HTTP_REFERER'] = home_url( '/support/' );

		$sso = $this->make_sso( 'login.wordpress.org' );

		$defaults = $sso->login_form_defaults( array( 'redirect' => '' ) );

		$this->assertSame( home_url( '/support/' ), $defaults['redirect'] );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput -- Reading back what the method under test wrote.
		$this->assertSame( home_url( '/support/' ), $_GET['redirect_to'] ?? '' );
	}

	/**
	 * Account emails come from WordPress.org, not from whichever site sent them.
	 *
	 * @return void
	 */
	public function test_account_emails_come_from_wordpress_org(): void {
		$sso = $this->make_sso( 'wordpress.org' );

		$email = $sso->replace_admin_email_in_change_emails(
			array(
				'headers' => '',
				'message' => 'Contact ###ADMIN_EMAIL### about this.',
			)
		);

		$this->assertSame( "From: WordPress.org <donotreply@wordpress.org>\n", $email['headers'] );
		$this->assertSame( 'Contact forum-password-resets@wordpress.org about this.', $email['message'] );
	}

	/**
	 * The policy interstitial keeps the destination the user was heading to.
	 *
	 * @return void
	 */
	public function test_policy_interstitial_keeps_the_destination(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$redirect = $sso->redirect_to_policy_update( 'https://make.wordpress.org/core/' );

		$this->assertStringStartsWith( home_url( '/updated-policies' ), $redirect );
		$this->assertSame( 'https://make.wordpress.org/core/', $this->query_arg( $redirect, 'redirect_to' ) );
	}

	/**
	 * A destination already on the interstitial is not wrapped in itself.
	 *
	 * @return void
	 */
	public function test_policy_interstitial_does_not_nest(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$already = home_url( '/updated-policies' );

		$this->assertSame( $already, $sso->redirect_to_policy_update( $already ) );
	}

	/**
	 * The terms of service gate is currently open, and lets cookies through.
	 *
	 * Its `has_agreed_to_tos()` returns true for everyone while the interstitial
	 * is switched off; this is what would have to change first if it came back.
	 *
	 * @return void
	 */
	public function test_terms_of_service_gate_is_open(): void {
		$user_id = $this->factory->user->create();

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertTrue( $sso->user_has_agreed_to_tos( $user_id ) );
		$this->assertTrue( $sso->maybe_block_auth_cookies( true, 0, time() + HOUR_IN_SECONDS, $user_id, 'a-token' ) );
		$this->assertFalse( has_filter( 'login_redirect', array( $sso, 'redirect_to_policy_update' ) ) );
	}

	/**
	 * A user who has not accepted the terms gets no cookies, and an interstitial.
	 *
	 * @return void
	 */
	public function test_terms_of_service_gate_holds_cookies_back_when_pending(): void {
		$user_id = $this->factory->user->create();

		$sso = $this->make_sso( 'login.wordpress.org', '/wp-login.php', '/wp-login.php', WPOrg_SSO_TOS_Pending_Double::class );

		$sends_cookies = $this->without_header_warnings(
			function () use ( $sso, $user_id ) {
				return $sso->maybe_block_auth_cookies( true, 0, time() + HOUR_IN_SECONDS, $user_id, 'a-token' );
			}
		);

		$this->assertFalse( $sends_cookies );

		$redirect = apply_filters( 'login_redirect', 'https://make.wordpress.org/core/', '', new WP_User( $user_id ) );

		$this->assertStringStartsWith( home_url( '/updated-policies' ), $redirect );
		$this->assertSame( 'https://make.wordpress.org/core/', $this->query_arg( $redirect, 'redirect_to' ) );
	}

	/**
	 * Whether registration is open is answered by the main network, not locally.
	 *
	 * The tell is the fallback: a caller asking for the setting gets the main
	 * network's `none` rather than the default it passed, because the answer
	 * never comes from the local network in the first place.
	 *
	 * @return void
	 */
	public function test_registration_status_comes_from_the_main_network(): void {
		// The instance has to exist for its `pre_site_option_registration` filter to be in place.
		$this->make_sso( 'login.wordpress.org' );

		delete_network_option( 1, 'registration' );

		$this->assertSame( 'none', get_site_option( 'registration', 'a-local-answer' ) );

		update_network_option( 1, 'registration', 'all' );

		$this->assertSame( 'all', get_site_option( 'registration', 'a-local-answer' ) );
	}

	/**
	 * The network asked for is the main one, whichever network is current.
	 *
	 * Single-site WordPress serves every network ID from the same row, so the
	 * value alone cannot show which network was asked for. The ID reaches the
	 * `pre_site_option_registration` filter as an argument, though, so a
	 * recorder on that hook can see the lookup the SSO makes.
	 *
	 * @return void
	 */
	public function test_registration_status_is_looked_up_against_the_main_network(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$asked_about = array();

		$recorder = function ( $pre, $option, $network_id ) use ( &$asked_about, $sso ) {
			// The SSO unhooks itself for the duration of its own lookup, which is the one to record.
			if ( ! has_filter( 'pre_site_option_registration', array( $sso, 'inherit_registration_option' ) ) ) {
				$asked_about[] = (int) $network_id;
			}

			return $pre;
		};

		add_filter( 'pre_site_option_registration', $recorder, 1, 3 );

		get_site_option( 'registration' );

		remove_filter( 'pre_site_option_registration', $recorder, 1 );

		$this->assertNotEmpty( $asked_about, 'The SSO looked no network up at all.' );
		$this->assertSame( array( 1 ), array_values( array_unique( $asked_about ) ) );
	}

	/**
	 * Reading the setting does not leave the filter off, or recurse into itself.
	 *
	 * The callback has to unhook itself before reading the main network, and put
	 * itself back afterwards; getting either half wrong breaks every later read.
	 *
	 * @return void
	 */
	public function test_registration_status_can_be_read_more_than_once(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		update_network_option( 1, 'registration', 'all' );

		get_site_option( 'registration' );

		$this->assertNotFalse( has_filter( 'pre_site_option_registration', array( $sso, 'inherit_registration_option' ) ) );
		$this->assertSame( 'all', get_site_option( 'registration', 'a-local-answer' ) );
	}
}
