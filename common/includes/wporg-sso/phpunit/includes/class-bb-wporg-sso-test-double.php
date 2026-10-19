<?php
/**
 * Test double for the bbPress flavour of the WordPress.org SSO.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * The bbPress SSO, with its redirects captured.
 */
class BB_WPOrg_SSO_Test_Double extends BB_WPOrg_SSO {

	use WPOrg_SSO_Captures_Redirects;
}
