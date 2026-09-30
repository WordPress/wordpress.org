<?php
/**
 * Tests for the bbPress flavour of the SSO.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

/**
 * Covers BB_WPOrg_SSO::redirect_all_login_or_signup_to_sso(), the standalone
 * bbPress equivalent of the WordPress dispatcher.
 */
class BB_WPOrg_SSO_Test extends WPOrg_SSO_TestCase {

	/**
	 * Builds a bbPress SSO instance serving a given request.
	 *
	 * @param string $host   The host the request arrived on.
	 * @param string $script The script handling the request.
	 * @return BB_WPOrg_SSO_Test_Double
	 */
	protected function make_bb_sso( string $host, string $script ): BB_WPOrg_SSO_Test_Double {
		$_SERVER['HTTP_HOST']   = $host;
		$_SERVER['SCRIPT_NAME'] = $script;
		$_SERVER['REQUEST_URI'] = $script;

		return new BB_WPOrg_SSO_Test_Double();
	}

	/**
	 * Sites outside the network are left alone.
	 *
	 * @return void
	 */
	public function test_foreign_hosts_are_not_touched(): void {
		$sso = $this->make_bb_sso( 'example.com', '/bb-login.php' );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );
	}

	/**
	 * Registration on bbPress goes to the SSO registration page.
	 *
	 * @return void
	 */
	public function test_registration_goes_to_the_sso_host(): void {
		$sso = $this->make_bb_sso( 'bbpress.org', '/register.php' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertSame( 'https://login.wordpress.org/register', $redirect->to );
		$this->assertSame( 301, $redirect->status );
	}

	/**
	 * The bbPress login screen goes to the SSO host.
	 *
	 * @return void
	 */
	public function test_login_goes_to_the_sso_host(): void {
		$sso = $this->make_bb_sso( 'bbpress.org', '/bb-login.php' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertStringStartsWith( 'https://login.wordpress.org/', $redirect->to );
		$this->assertSame( 301, $redirect->status );
		$this->assertSame( 'https://bbpress.org/', $this->query_arg( $redirect->to, 'redirect_to' ) );
	}

	/**
	 * A login being submitted from the site header is allowed to complete.
	 *
	 * @return void
	 */
	public function test_posted_login_is_handled_locally(): void {
		$_POST['log'] = 'someone';

		$sso = $this->make_bb_sso( 'bbpress.org', '/bb-login.php' );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );
	}

	/**
	 * Logging out does not need a trip to the SSO host.
	 *
	 * @return void
	 */
	public function test_logout_is_handled_locally(): void {
		$_GET['action'] = 'logout';

		$sso = $this->make_bb_sso( 'bbpress.org', '/bb-login.php' );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );
	}

	/**
	 * Any other requested action is carried across to the SSO host.
	 *
	 * @return void
	 */
	public function test_other_actions_are_carried_across(): void {
		$_GET['action'] = 'lostpassword';

		$sso = $this->make_bb_sso( 'bbpress.org', '/bb-login.php' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertSame( 'lostpassword', $this->query_arg( $redirect->to, 'action' ) );
	}

	/**
	 * Pages that are not login or registration screens are served as they are.
	 *
	 * @return void
	 */
	public function test_ordinary_pages_are_served(): void {
		$sso = $this->make_bb_sso( 'bbpress.org', '/index.php' );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );
	}
}
