<?php
/**
 * Tests for the last check the SSO makes before emitting a Location header.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

/**
 * Covers WPOrg_SSO::_safe_redirect(), which every other test double replaces.
 *
 * Its re-validation is load-bearing: `_maybe_perform_remote_login()` hands it
 * `$_GET['redirect_to']` without checking the host first, so this method is the
 * only thing between a crafted hand-off link and an open redirect on every
 * wordcamp.org and bbpress.org page.
 *
 * The request is stopped on the `wp_redirect` filter, which fires after the
 * sanitising and re-validation and before `header()` and `exit`.
 */
class WPOrg_SSO_Safe_Redirect_Test extends WPOrg_SSO_TestCase {

	/**
	 * Stops every redirect at the filter rather than letting it exit.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		add_filter( 'wp_redirect', array( $this, 'stop_at_the_header' ), 10, 2 );
	}

	/**
	 * Throws with the location the SSO settled on.
	 *
	 * @throws WPOrg_SSO_Redirect_Exception Always.
	 *
	 * @param string $location The destination the SSO arrived at.
	 * @param int    $status   The HTTP status it chose.
	 */
	public function stop_at_the_header( $location, $status ): never {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught by the test, never rendered.
		throw new WPOrg_SSO_Redirect_Exception( (string) $location, (int) $status );
	}

	/**
	 * Builds an SSO whose redirects are performed rather than captured.
	 *
	 * @param string $host The host the request arrived on.
	 * @param string $uri  The request URI.
	 * @return WPOrg_SSO_Live_Redirect_Double
	 */
	protected function make_live_sso( string $host, string $uri = '/' ): WPOrg_SSO_Live_Redirect_Double {
		$_SERVER['HTTP_HOST']   = $host;
		$_SERVER['SCRIPT_NAME'] = '/index.php';
		$_SERVER['REQUEST_URI'] = $uri;

		return new WPOrg_SSO_Live_Redirect_Double();
	}

	/**
	 * A destination on the network is honoured as asked.
	 *
	 * @return void
	 */
	public function test_a_network_destination_is_honoured(): void {
		$sso = $this->make_live_sso( 'wordcamp.org' );

		$redirect = $this->catch_redirect(
			function () use ( $sso ) {
				$sso->safe_redirect( 'https://make.wordpress.org/core/' );
			}
		);

		$this->assertSame( 'https://make.wordpress.org/core/', $redirect->to );
	}

	/**
	 * A destination off the network is replaced, not emitted.
	 *
	 * @return void
	 */
	public function test_a_foreign_destination_is_replaced(): void {
		$sso = $this->make_live_sso( 'wordcamp.org' );

		$redirect = $this->catch_redirect(
			function () use ( $sso ) {
				$sso->safe_redirect( 'https://evil.com/steal' );
			}
		);

		// Replaced by the safest thing the request offers, which is the host it arrived on.
		$this->assertSame( 'https://wordcamp.org/', $redirect->to );
		$this->assertStringNotContainsString( 'evil.com', $redirect->to );
	}

	/**
	 * The replacement still respects a safe destination the request named.
	 *
	 * @return void
	 */
	public function test_a_replaced_destination_falls_back_to_the_request(): void {
		$_REQUEST['redirect_to'] = 'https://bbpress.org/forums/';

		$sso = $this->make_live_sso( 'wordcamp.org' );

		$redirect = $this->catch_redirect(
			function () use ( $sso ) {
				$sso->safe_redirect( 'https://evil.com/steal' );
			}
		);

		$this->assertSame( 'https://bbpress.org/forums/', $redirect->to );
	}

	/**
	 * A header-splitting payload never reaches the header.
	 *
	 * The SSO calls `wp_sanitize_redirect()` itself rather than relying on
	 * whatever the platform does later, so the contract is ours to keep.
	 *
	 * @return void
	 */
	public function test_carriage_returns_are_stripped(): void {
		$sso = $this->make_live_sso( 'wordcamp.org' );

		$redirect = $this->catch_redirect(
			function () use ( $sso ) {
				$sso->safe_redirect( "https://wordpress.org/\r\nSet-Cookie: pwned=1" );
			}
		);

		$this->assertStringNotContainsString( "\r", $redirect->to );
		$this->assertStringNotContainsString( "\n", $redirect->to );
	}

	/**
	 * A relative destination is not a destination; it is replaced.
	 *
	 * @return void
	 */
	public function test_a_relative_destination_is_replaced(): void {
		$sso = $this->make_live_sso( 'wordcamp.org' );

		$redirect = $this->catch_redirect(
			function () use ( $sso ) {
				$sso->safe_redirect( '/wp-admin/' );
			}
		);

		$this->assertSame( 'https://wordcamp.org/', $redirect->to );
	}

	/**
	 * The requested status reaches the header untouched.
	 *
	 * @return void
	 */
	public function test_the_requested_status_is_kept(): void {
		$sso = $this->make_live_sso( 'wordcamp.org' );

		$redirect = $this->catch_redirect(
			function () use ( $sso ) {
				$sso->safe_redirect( 'https://wordpress.org/', 301 );
			}
		);

		$this->assertSame( 301, $redirect->status );
	}

	/**
	 * A hand-off cannot be pointed off the network by its own query string.
	 *
	 * `_maybe_perform_remote_login()` passes `redirect_to` through unchecked,
	 * so without the re-validation below it this is an open redirect on every
	 * host the SSO serves.
	 *
	 * @return void
	 */
	public function test_a_hand_off_cannot_be_redirected_off_the_network(): void {
		$user = new WP_User( $this->factory->user->create() );

		$_GET['sso_token']   = $this->make_live_sso( 'login.wordpress.org' )->generate_remote_token( $user, 'wordcamp.org' );
		$_GET['redirect_to'] = 'https://evil.com/steal';

		$sso = $this->make_live_sso( 'wordcamp.org', '/schedule/' );

		$redirect = $this->catch_redirect( array( $sso, 'maybe_perform_remote_login' ) );

		$this->assertSame( 'https://wordcamp.org/schedule/', $redirect->to );
		$this->assertStringNotContainsString( 'evil.com', $redirect->to );
	}
}
