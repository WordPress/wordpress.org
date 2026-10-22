<?php
/**
 * The redirect a test double captured instead of performing.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * Thrown in place of the redirect-and-exit the SSO performs in production.
 */
class WPOrg_SSO_Redirect_Exception extends Exception {

	/**
	 * The URL the SSO redirected to.
	 *
	 * @var string
	 */
	public string $to;

	/**
	 * The HTTP status the redirect was issued with.
	 *
	 * @var int
	 */
	public int $status;

	/**
	 * Records the redirect the SSO attempted.
	 *
	 * @param string $to     The URL the SSO redirected to.
	 * @param int    $status The HTTP status the redirect was issued with.
	 */
	public function __construct( string $to, int $status ) {
		parent::__construct( sprintf( 'Redirected to %s (%d)', $to, $status ) );

		$this->to     = $to;
		$this->status = $status;
	}
}
