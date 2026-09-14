<?php
/**
 * A WordPress.org SSO that performs its redirects for real.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * Exposes the production `_safe_redirect()` instead of capturing it.
 *
 * The other doubles replace that method, which leaves the sanitising and the
 * re-validation it does before emitting a `Location` header untested — and that
 * re-check is the only thing standing between a request-supplied `redirect_to`
 * and an open redirect. This one runs it; the test stops the request on the
 * `wp_redirect` filter, the last point before `header()` and `exit`.
 */
class WPOrg_SSO_Live_Redirect_Double extends WP_WPOrg_SSO {

	/**
	 * Runs the real redirect.
	 *
	 * @param string $to     Destination URL.
	 * @param int    $status HTTP redirect status.
	 */
	public function safe_redirect( string $to, int $status = 302 ): never {
		$this->_safe_redirect( $to, $status );
	}

	/**
	 * Handles an inbound `sso_token`, redirecting for real afterwards.
	 *
	 * @return void
	 */
	public function maybe_perform_remote_login(): void {
		$this->_maybe_perform_remote_login();
	}

	/**
	 * Mints a remote login/logout token.
	 *
	 * @param WP_User $user        The user the token is for.
	 * @param string  $target_host The host the token is issued for.
	 * @return string
	 */
	public function generate_remote_token( WP_User $user, string $target_host = '' ): string {
		return $this->_generate_remote_token( $user, $target_host );
	}
}
