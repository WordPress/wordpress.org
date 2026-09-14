<?php
/**
 * Tests for the dispatcher that funnels every login screen to the SSO host.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers WP_WPOrg_SSO::redirect_all_login_or_signup_to_sso(), which decides on
 * every request whether to hand the visitor to login.wordpress.org, to let the
 * request through, or to serve one of the SSO host's own screens.
 */
class WPOrg_SSO_Login_Redirects_Test extends WPOrg_SSO_TestCase {

	/**
	 * Sites outside the network are left entirely alone.
	 *
	 * @return void
	 */
	public function test_foreign_hosts_are_not_touched(): void {
		$sso = $this->make_sso( 'example.com', '/wp-login.php', '/wp-login.php' );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertFalse( has_filter( 'lostpassword_url', array( $sso, 'lostpassword_url' ) ) );
	}

	/**
	 * Signup screens anywhere on the network become the SSO registration page.
	 *
	 * @return void
	 */
	public function test_signup_screens_go_to_the_sso_registration_page(): void {
		$sso = $this->make_sso( 'wordpress.org', '/wp-signup.php', '/wp-signup.php' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertStringStartsWith( 'https://login.wordpress.org/register', $redirect->to );
		$this->assertSame( 301, $redirect->status );
	}

	/**
	 * A login screen on another network site goes to the SSO host.
	 *
	 * @return void
	 */
	public function test_login_screens_go_to_the_sso_host(): void {
		$sso = $this->make_sso( 'bbpress.org', '/wp-login.php', '/wp-login.php' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertStringStartsWith( 'https://login.wordpress.org/', $redirect->to );
		$this->assertSame( 301, $redirect->status );
		$this->assertSame( 'bbpress.org', $this->query_arg( $redirect->to, 'from' ) );
		$this->assertSame( 'https://bbpress.org/', $this->query_arg( $redirect->to, 'redirect_to' ) );
	}

	/**
	 * The requested action is carried over so the SSO host can serve it.
	 *
	 * @return void
	 */
	public function test_login_screens_carry_their_action_across(): void {
		$_GET['action'] = 'lostpassword';

		$sso = $this->make_sso( 'wordpress.org', '/wp-login.php', '/wp-login.php?action=lostpassword' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertSame( 'lostpassword', $this->query_arg( $redirect->to, 'action' ) );
	}

	/**
	 * Handlers that only work on the site they were requested on stay put.
	 *
	 * @param string $action The wp-login.php action to leave alone.
	 * @return void
	 */
	#[DataProvider( 'get_local_login_actions' )]
	public function test_local_login_actions_are_left_alone( string $action ): void {
		$_GET['action']     = $action;
		$_REQUEST['action'] = $action;

		$sso = $this->make_sso( 'wordpress.org', '/wp-login.php', '/wp-login.php?action=' . $action );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );
	}

	/**
	 * The wp-login.php actions that must be handled where they were requested.
	 *
	 * @return array[]
	 */
	public static function get_local_login_actions(): array {
		return array(
			// Email confirmation links are signed for the site that sent them.
			'confirmaction'  => array( 'confirmaction' ),
			// The post password form posts back to the site holding the post.
			'postpass'       => array( 'postpass' ),
			// Logout is finished locally, then bounced to the SSO host by login_form_logout().
			'logout'         => array( 'logout' ),
			// Two-factor revalidation happens in the session being revalidated.
			'revalidate_2fa' => array( 'revalidate_2fa' ),
		);
	}

	/**
	 * A logout that is being submitted is not a logout screen, and is redirected.
	 *
	 * @return void
	 */
	public function test_posted_logout_is_still_redirected(): void {
		$_GET['action'] = 'logout';
		$_POST['x']     = 'y';

		$sso = $this->make_sso( 'wordpress.org', '/wp-login.php', '/wp-login.php?action=logout' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertStringStartsWith( 'https://login.wordpress.org/', $redirect->to );
	}

	/**
	 * Ordinary pages are served; only their login links are rewritten.
	 *
	 * @return void
	 */
	public function test_ordinary_pages_only_get_their_login_links_rewritten(): void {
		$sso = $this->make_sso( 'wordpress.org', '/index.php', '/support/' );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertNotFalse( has_filter( 'login_url', array( $sso, 'login_url' ) ) );
	}

	/**
	 * The SSO host's own front page is served rather than redirected.
	 *
	 * @return void
	 */
	public function test_sso_front_page_is_served_to_logged_out_visitors(): void {
		$sso = $this->make_sso( 'login.wordpress.org', '/index.php', '/' );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertSame( 'root', WP_WPOrg_SSO::$matched_route );
		$this->assertNotFalse( has_filter( 'login_form_defaults', array( $sso, 'login_form_defaults' ) ) );
		$this->assertTrue( apply_filters( 'is_valid_wporg_sso_path', false ) );
	}

	/**
	 * An action on the SSO front page belongs to wp-login.php, and is sent there.
	 *
	 * @return void
	 */
	public function test_actions_on_the_sso_front_page_go_to_wp_login(): void {
		$_GET['action'] = 'logout';

		$sso = $this->make_sso( 'login.wordpress.org', '/index.php', '/?action=logout' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertStringStartsWith( 'https://login.wordpress.org/wp-login.php', $redirect->to );
		$this->assertSame( 'logout', $this->query_arg( $redirect->to, 'action' ) );
	}

	/**
	 * A logged-in visitor to the SSO front page is sent back where they came from.
	 *
	 * @return void
	 */
	public function test_logged_in_visitors_leave_the_sso_front_page(): void {
		wp_set_current_user( $this->factory->user->create() );

		$_REQUEST['redirect_to'] = 'https://make.wordpress.org/core/';

		$sso = $this->make_sso( 'login.wordpress.org', '/index.php', '/' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertSame( 'https://make.wordpress.org/core/', $redirect->to );
	}

	/**
	 * With nowhere to return to, a logged-in visitor gets their profile.
	 *
	 * @return void
	 */
	public function test_logged_in_visitors_fall_back_to_their_profile(): void {
		$user = new WP_User( $this->factory->user->create( array( 'user_login' => 'sso-profile-user' ) ) );

		wp_set_current_user( $user->ID );

		$sso = $this->make_sso( 'login.wordpress.org', '/index.php', '/' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertSame( 'https://profiles.wordpress.org/' . $user->user_nicename . '/', $redirect->to );
	}

	/**
	 * The SSO host's own screens are served, and announce themselves to the theme.
	 *
	 * @return void
	 */
	public function test_sso_paths_are_served(): void {
		$sso = $this->make_sso( 'login.wordpress.org', '/index.php', '/lostpassword/sso-traveller' );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertSame( 'lostpassword', WP_WPOrg_SSO::$matched_route );
		$this->assertSame( 'sso-traveller', WP_WPOrg_SSO::$matched_route_params['user'] );
		$this->assertTrue( apply_filters( 'is_valid_wporg_sso_path', false ) );
	}

	/**
	 * Trailing dots and spaces do not smuggle a request past the route table.
	 *
	 * Landing on the right route is only half of it: the segment after
	 * `/register/` is captured as a username and prefills the signup form, so a
	 * `.` or `..` that survives normalization is a username as far as the
	 * template is concerned.
	 *
	 * @param string $uri The request URI to try.
	 * @return void
	 */
	#[DataProvider( 'get_normalized_route_uris' )]
	public function test_route_matching_normalizes_trailing_characters( string $uri ): void {
		$sso = $this->make_sso( 'login.wordpress.org', '/index.php', $uri );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertSame( 'register', WP_WPOrg_SSO::$matched_route );
		$this->assertSame( '', WP_WPOrg_SSO::$matched_route_params['user'] ?? '' );
	}

	/**
	 * Request URIs that all have to resolve to the registration route.
	 *
	 * @return array[]
	 */
	public static function get_normalized_route_uris(): array {
		return array(
			'plain'          => array( '/register' ),
			'trailing slash' => array( '/register/' ),
			'trailing dot'   => array( '/register/.' ),
			'trailing dots'  => array( '/register/..' ),
			'encoded space'  => array( '/register/.%20' ),
			'with a query'   => array( '/register?locale=de_DE' ),
		);
	}

	/**
	 * Anything else on the SSO host is a login screen for logged-out visitors.
	 *
	 * @return void
	 */
	public function test_unknown_sso_paths_go_to_the_login_screen(): void {
		$sso = $this->make_sso( 'login.wordpress.org', '/index.php', '/not-a-route' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertSame( 'https://login.wordpress.org/', $redirect->to );
		$this->assertSame( 301, $redirect->status );
		$this->assertFalse( WP_WPOrg_SSO::$matched_route );
	}

	/**
	 * The REST and XML-RPC endpoints stay reachable on the SSO host.
	 *
	 * @param string $uri The request URI to try.
	 * @return void
	 */
	#[DataProvider( 'get_api_uris' )]
	public function test_api_endpoints_are_reachable_on_the_sso_host( string $uri ): void {
		$sso = $this->make_sso( 'login.wordpress.org', '/index.php', $uri );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );
	}

	/**
	 * Endpoints that must not be swallowed by the login redirect.
	 *
	 * @return array[]
	 */
	public static function get_api_uris(): array {
		return array(
			'REST route' => array( '/wp-json/wp/v2/users' ),
			'XML-RPC'    => array( '/xmlrpc.php' ),
		);
	}

	/**
	 * A REST request made by query parameter is not exempt, and never was.
	 *
	 * `/?rest_route=…` matches the `root` route, so the dispatcher answers from
	 * the route table and never reaches the `/?rest_route=` exemption below it.
	 * For a logged-out request that is harmless; a logged-in one is redirected
	 * away instead of served. Recorded as it behaves, not as intended: the
	 * exemption looks like dead code in production for the same reason.
	 *
	 * @return void
	 */
	public function test_rest_requests_by_query_parameter_are_not_exempt(): void {
		wp_set_current_user( $this->factory->user->create() );

		$sso = $this->make_sso( 'login.wordpress.org', '/index.php', '/?rest_route=/wp/v2/users' );

		$redirect = $this->catch_redirect( array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertSame( 'root', WP_WPOrg_SSO::$matched_route );
		$this->assertStringStartsWith( 'https://profiles.wordpress.org/', $redirect->to );
	}

	/**
	 * The SSO host's login screen keeps its forms on the SSO host.
	 *
	 * @return void
	 */
	public function test_sso_login_screen_keeps_its_forms_local(): void {
		$sso = $this->make_sso( 'login.wordpress.org', '/wp-login.php', '/wp-login.php' );

		$this->assert_no_redirect( $sso, array( $sso, 'redirect_all_login_or_signup_to_sso' ) );

		$this->assertNotFalse( has_filter( 'network_site_url', array( $sso, 'login_network_site_url' ) ) );
		$this->assertSame(
			'https://login.wordpress.org/wp-login.php',
			$sso->login_network_site_url( 'https://wordpress.org/wp-login.php', 'wp-login.php', 'login' )
		);
	}
}
