<?php
/**
 * Test double for the WordPress flavour of the WordPress.org SSO.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * The WordPress SSO, with its redirects captured and its internals reachable.
 */
class WPOrg_SSO_Test_Double extends WP_WPOrg_SSO {

	use WPOrg_SSO_Captures_Redirects;

	/**
	 * Whether the passed host, domain, or URL belongs to the WordPress.org network.
	 *
	 * @param mixed $host A domain, hostname, or URL.
	 * @return bool
	 */
	public function is_valid_targeted_domain( $host ): bool {
		return $this->_is_valid_targeted_domain( $host );
	}

	/**
	 * The registrable host a given hostname belongs to.
	 *
	 * @param string $host The hostname to process.
	 * @return string
	 */
	public function get_targetted_host( string $host ): string {
		return $this->_get_targetted_host( $host );
	}

	/**
	 * A safe redirect target drawn from the current request.
	 *
	 * @param string|false $fallback Where to send the user when the request offers no safe target.
	 * @return string|false
	 */
	public function get_safer_redirect_to( $fallback = 'https://wordpress.org/' ) {
		return $this->_get_safer_redirect_to( $fallback );
	}

	/**
	 * The hostname as it is compared inside a remote token.
	 *
	 * @param string $host A hostname, possibly with a port.
	 * @return string
	 */
	public function normalize_token_host( string $host ): string {
		return $this->_normalize_token_host( $host );
	}

	/**
	 * Mints a remote login/logout token.
	 *
	 * @param WP_User $user        The user the token is for.
	 * @param string  $target_host The host the token is issued for.
	 * @param string  $bounce      Fingerprint of the browser's bounce ticket.
	 * @return string
	 */
	public function generate_remote_token( WP_User $user, string $target_host = '', string $bounce = '' ): string {
		return $this->_generate_remote_token( $user, $target_host, $bounce );
	}

	/**
	 * The signature half of a remote token.
	 *
	 * @param WP_User $user          The user the token is for.
	 * @param int     $valid_until   Timestamp the token expires at.
	 * @param bool    $remember_me   Whether the login should be remembered.
	 * @param string  $session_token The session the token is bound to.
	 * @param string  $target_host   The host the token is issued for.
	 * @param string  $bounce        Fingerprint of the browser's bounce ticket.
	 * @return string
	 */
	public function generate_remote_token_hash( WP_User $user, int $valid_until, bool $remember_me = false, string $session_token = '', string $target_host = '', string $bounce = '' ): string {
		return $this->_generate_remote_token_hash( $user, $valid_until, $remember_me, $session_token, $target_host, $bounce );
	}

	/**
	 * Validates a remote token against the current host.
	 *
	 * @param string $sso_token The raw token from the URL.
	 * @param string $bounce    Fingerprint of the browser's bounce ticket.
	 * @return array
	 */
	public function validate_remote_token( string $sso_token, string $bounce = '' ): array {
		return $this->_validate_remote_token( $sso_token, $bounce );
	}

	/**
	 * Claims a remote token for single use.
	 *
	 * @param string $sso_hash The token's validated signature.
	 * @return bool
	 */
	public function claim_remote_token( string $sso_hash ): bool {
		return $this->_claim_remote_token( $sso_hash );
	}

	/**
	 * Adds a remote login token to a redirect leaving the wordpress.org family.
	 *
	 * @param string                 $redirect The redirect target.
	 * @param WP_User|WP_Error|false $user   The user being logged in, if known.
	 * @return string
	 */
	public function maybe_add_remote_login_bounce( string $redirect, $user = false ): string {
		return $this->_maybe_add_remote_login_bounce( $redirect, $user );
	}

	/**
	 * Handles an inbound `sso_token`, logging the user in on this host.
	 *
	 * @return void
	 */
	public function maybe_perform_remote_login(): void {
		$this->_maybe_perform_remote_login();
	}

	/**
	 * Handles an inbound `sso_logout`, ending the user's session.
	 *
	 * @return void
	 */
	public function maybe_perform_remote_logout(): void {
		$this->_maybe_perform_remote_logout();
	}

	/**
	 * Whether the user has accepted the current terms of service.
	 *
	 * @param int $user_id The user to check.
	 * @return bool
	 */
	public function user_has_agreed_to_tos( int $user_id ): bool {
		return $this->has_agreed_to_tos( $user_id );
	}
}
