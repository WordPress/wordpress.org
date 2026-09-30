<?php
/**
 * Tests for the host and redirect-target validation shared by every SSO client.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Covers which hosts the SSO will hand a user off to, and where it sends them
 * when the request does not name a target of its own.
 */
class WPOrg_SSO_Redirect_Targets_Test extends WPOrg_SSO_TestCase {

	/**
	 * Only login.wordpress.org is the SSO host.
	 *
	 * @return void
	 */
	public function test_sso_host_is_only_the_login_host(): void {
		$this->assertTrue( $this->make_sso( 'login.wordpress.org' )->is_sso_host() );
		$this->assertFalse( $this->make_sso( 'wordpress.org' )->is_sso_host() );
		$this->assertFalse( $this->make_sso( 'login.wordpress.org.evil.com' )->is_sso_host() );
	}

	/**
	 * Requests without a host — cron and WP-CLI — leave the SSO dormant.
	 *
	 * @return void
	 */
	public function test_has_host_is_false_without_a_request(): void {
		unset( $_SERVER['HTTP_HOST'] );

		$this->assertFalse( ( new WPOrg_SSO_Test_Double() )->has_host() );
	}

	/**
	 * Cookies are set on the bare hostname; a port would scope them to nothing.
	 *
	 * @return void
	 */
	public function test_cookie_host_drops_the_port(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( 'login.wordpress.org', $sso->get_cookie_host() );

		// Local environments run the SSO on a port; production never does.
		$sso->sso_host = 'localhost:8888';

		$this->assertSame( 'localhost', $sso->get_cookie_host() );
	}

	/**
	 * Hosts inside the WordPress.org network are valid hand-off targets.
	 *
	 * @param string $host A domain, hostname, or URL.
	 * @return void
	 */
	#[DataProvider( 'get_network_targets' )]
	public function test_network_targets_are_valid( string $host ): void {
		$this->assertTrue( $this->make_sso( 'wordpress.org' )->is_valid_targeted_domain( $host ) );
	}

	/**
	 * Hosts and URLs that belong to the network.
	 *
	 * @return array[]
	 */
	public static function get_network_targets(): array {
		return array(
			'top level domain'      => array( 'wordpress.org' ),
			'subdomain'             => array( 'make.wordpress.org' ),
			'the SSO host'          => array( 'login.wordpress.org' ),
			'sibling project'       => array( 'bbpress.org' ),
			'sibling subdomain'     => array( 'codex.buddypress.org' ),
			'deep wordcamp host'    => array( '2023.us.wordcamp.org' ),
			'full URL'              => array( 'https://make.wordpress.org/core/' ),
			'protocol relative URL' => array( '//wordpress.org/support/' ),
			'URL with a port'       => array( 'https://wordpress.org:443/' ),
		);
	}

	/**
	 * Anything outside the network is refused, however it is dressed up.
	 *
	 * @param string $host A domain, hostname, or URL.
	 * @return void
	 */
	#[DataProvider( 'get_foreign_targets' )]
	public function test_foreign_targets_are_refused( string $host ): void {
		$this->assertFalse( $this->make_sso( 'wordpress.org' )->is_valid_targeted_domain( $host ) );
	}

	/**
	 * Hosts and URLs that must never be redirected to.
	 *
	 * @return array[]
	 */
	public static function get_foreign_targets(): array {
		return array(
			'empty string'              => array( '' ),
			'unrelated domain'          => array( 'example.com' ),
			'network name as a suffix'  => array( 'evilwordpress.org' ),
			'network name as a prefix'  => array( 'wordpress.org.evil.com' ),
			'network name in the path'  => array( 'https://evil.com/wordpress.org' ),
			'network name in the query' => array( 'https://evil.com/?to=wordpress.org' ),
			'network name in userinfo'  => array( 'https://wordpress.org@evil.com/' ),
			'relative path'             => array( '/wp-admin/' ),
			'non-HTTP scheme'           => array( 'javascript:alert(1)' ),
		);
	}

	/**
	 * Non-string input is refused rather than coerced.
	 *
	 * @return void
	 */
	public function test_non_string_targets_are_refused(): void {
		$sso = $this->make_sso( 'wordpress.org' );

		$this->assertFalse( $sso->is_valid_targeted_domain( null ) );
		$this->assertFalse( $sso->is_valid_targeted_domain( array( 'wordpress.org' ) ) );
		$this->assertFalse( $sso->is_valid_targeted_domain( false ) );
	}

	/**
	 * Hosts reduce to the registrable domain the remote tokens are scoped to.
	 *
	 * @param string $host     The hostname to process.
	 * @param string $expected The registrable domain it belongs to.
	 * @return void
	 */
	#[DataProvider( 'get_targetted_hosts' )]
	public function test_targetted_host_reduces_to_the_registrable_domain( string $host, string $expected ): void {
		$this->assertSame( $expected, $this->make_sso( 'wordpress.org' )->get_targetted_host( $host ) );
	}

	/**
	 * Hostnames and the registrable domain each belongs to.
	 *
	 * @return array[]
	 */
	public static function get_targetted_hosts(): array {
		return array(
			'already top level' => array( 'wordpress.org', 'wordpress.org' ),
			'the SSO host'      => array( 'login.wordpress.org', 'wordpress.org' ),
			'nested wordcamp'   => array( '2023.us.wordcamp.org', 'wordcamp.org' ),
			'sibling project'   => array( 'codex.bbpress.org', 'bbpress.org' ),
			// Only reduced when the reduction is a network host, so this is left whole.
			'unrelated domain'  => array( 'deep.sub.example.com', 'deep.sub.example.com' ),
			'single label'      => array( 'localhost', 'localhost' ),
		);
	}

	/**
	 * A requested redirect inside the network is honoured.
	 *
	 * @return void
	 */
	public function test_safer_redirect_honours_a_network_request(): void {
		$_REQUEST['redirect_to'] = 'https://make.wordpress.org/core/';

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( 'https://make.wordpress.org/core/', $sso->get_safer_redirect_to() );
	}

	/**
	 * A requested redirect off the network is dropped for the default.
	 *
	 * @return void
	 */
	public function test_safer_redirect_drops_a_foreign_request(): void {
		$_REQUEST['redirect_to'] = 'https://evil.com/steal';

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( 'https://wordpress.org/', $sso->get_safer_redirect_to() );
	}

	/**
	 * Spaces in a requested redirect are encoded rather than truncating it.
	 *
	 * @return void
	 */
	public function test_safer_redirect_encodes_spaces(): void {
		$_REQUEST['redirect_to'] = 'https://wordpress.org/plugins/search/hello dolly/';

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( 'https://wordpress.org/plugins/search/hello%20dolly/', $sso->get_safer_redirect_to() );
	}

	/**
	 * With no request of its own, the referer is used.
	 *
	 * @return void
	 */
	public function test_safer_redirect_falls_back_to_the_referer(): void {
		$_SERVER['HTTP_REFERER'] = 'https://bbpress.org/forums/';

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( 'https://bbpress.org/forums/', $sso->get_safer_redirect_to() );
	}

	/**
	 * A referer from the SSO host itself would bounce the user in a circle.
	 *
	 * @return void
	 */
	public function test_safer_redirect_ignores_a_referer_from_the_sso_host(): void {
		$_SERVER['HTTP_REFERER'] = 'https://login.wordpress.org/lostpassword';

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( 'https://wordpress.org/', $sso->get_safer_redirect_to() );
	}

	/**
	 * Logouts land on the confirmation screen rather than the passed default.
	 *
	 * @return void
	 */
	public function test_safer_redirect_sends_logouts_to_the_loggedout_screen(): void {
		$_GET['action'] = 'logout';

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( '/loggedout/', $sso->get_safer_redirect_to() );
	}

	/**
	 * Off the SSO host, the parent directory of the request is the last guess.
	 *
	 * @return void
	 */
	public function test_safer_redirect_guesses_the_requests_parent_directory(): void {
		$sso = $this->make_sso( 'bbpress.org', '/wp-login.php', '/forums/wp-login.php?action=lostpassword' );

		$this->assertSame( 'https://bbpress.org/forums/', $sso->get_safer_redirect_to() );
	}

	/**
	 * Callers that pass no default get nothing rather than a made-up target.
	 *
	 * @return void
	 */
	public function test_safer_redirect_returns_the_passed_default(): void {
		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertFalse( $sso->get_safer_redirect_to( false ) );
	}

	/**
	 * On the SSO host, the requested source is added to the allowed hosts.
	 *
	 * @return void
	 */
	public function test_allowed_redirect_hosts_gains_the_requested_source(): void {
		$_REQUEST['redirect_to'] = 'https://make.wordpress.org/core/';

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( array( 'make.wordpress.org' ), $sso->add_allowed_redirect_host( array() ) );
	}

	/**
	 * A foreign source never reaches the allowed hosts.
	 *
	 * @return void
	 */
	public function test_allowed_redirect_hosts_refuses_a_foreign_source(): void {
		$_REQUEST['redirect_to'] = 'https://evil.com/steal';

		$sso = $this->make_sso( 'login.wordpress.org' );

		$this->assertSame( array( 'wordpress.org' ), $sso->add_allowed_redirect_host( array() ) );
	}

	/**
	 * Off the SSO host, the SSO host itself is what needs allowing.
	 *
	 * @return void
	 */
	public function test_allowed_redirect_hosts_gains_the_sso_host(): void {
		$sso = $this->make_sso( 'wordpress.org' );

		$this->assertSame( array( 'login.wordpress.org' ), $sso->add_allowed_redirect_host( array() ) );
	}

	/**
	 * Hosts already allowed are not added twice.
	 *
	 * @return void
	 */
	public function test_allowed_redirect_hosts_does_not_duplicate(): void {
		$sso = $this->make_sso( 'wordpress.org' );

		$this->assertSame( array( 'login.wordpress.org' ), $sso->add_allowed_redirect_host( array( 'login.wordpress.org' ) ) );
	}

	/**
	 * The login URL points at the SSO host and carries the page to come back to.
	 *
	 * @return void
	 */
	public function test_login_url_points_at_the_sso_host(): void {
		$sso = $this->make_sso( 'wordpress.org', '/index.php', '/support/' );

		$login_url = $sso->login_url( 'https://wordpress.org/wp-login.php', 'https://wordpress.org/support/' );

		$this->assertStringStartsWith( 'https://login.wordpress.org/', $login_url );
		$this->assertSame( 'https://wordpress.org/support/', $this->query_arg( $login_url, 'redirect_to' ) );
	}

	/**
	 * Without a redirect of its own, the current request is what to come back to.
	 *
	 * @return void
	 */
	public function test_login_url_defaults_to_the_current_request(): void {
		$sso = $this->make_sso( 'wordpress.org', '/index.php', '/plugins/akismet/' );

		$this->assertSame(
			'https://wordpress.org/plugins/akismet/',
			$this->query_arg( $sso->login_url(), 'redirect_to' )
		);
	}

	/**
	 * A login screen is a pointless place to return to, so it is trimmed away.
	 *
	 * @return void
	 */
	public function test_login_url_trims_login_screens_from_the_return_target(): void {
		$sso = $this->make_sso( 'bbpress.org' );

		$this->assertSame(
			'https://bbpress.org/',
			$this->query_arg( $sso->login_url( '', 'https://bbpress.org/wp-login.php?action=lostpassword' ), 'redirect_to' )
		);
	}

	/**
	 * A foreign return target is dropped rather than passed to the SSO host.
	 *
	 * @return void
	 */
	public function test_login_url_drops_a_foreign_return_target(): void {
		$sso = $this->make_sso( 'wordpress.org' );

		$this->assertNull( $this->query_arg( $sso->login_url( '', 'https://evil.com/' ), 'redirect_to' ) );
	}

	/**
	 * Sibling projects announce themselves so the SSO can brand the screen.
	 *
	 * @return void
	 */
	public function test_login_url_names_non_wordpress_org_sources(): void {
		$this->assertSame( 'bbpress.org', $this->query_arg( $this->make_sso( 'bbpress.org' )->login_url(), 'from' ) );
		$this->assertNull( $this->query_arg( $this->make_sso( 'make.wordpress.org' )->login_url(), 'from' ) );
	}
}
