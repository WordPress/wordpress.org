<?php
/**
 * Redirect capture shared by the SSO test doubles.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * Replaces the redirect-and-exit with a throw, for any SSO subclass.
 */
trait WPOrg_SSO_Captures_Redirects {

	/**
	 * Every redirect attempted by this instance, in order.
	 *
	 * @var array[]
	 */
	public array $redirects = array();

	/**
	 * Records the redirect and aborts, standing in for the header-and-exit.
	 *
	 * @throws WPOrg_SSO_Redirect_Exception Always.
	 *
	 * @param string $to     Destination URL.
	 * @param int    $status HTTP redirect status.
	 */
	protected function _safe_redirect( $to, $status = 302 ): never { // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore, Generic.CodeAnalysis.UselessOverridingMethod.Found -- Overrides WPOrg_SSO::_safe_redirect(), whose untyped signature this has to match.
		$this->redirects[] = array(
			'to'     => (string) $to,
			'status' => (int) $status,
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Caught by the test, never rendered.
		throw new WPOrg_SSO_Redirect_Exception( (string) $to, (int) $status );
	}
}
