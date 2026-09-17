<?php
/**
 * The wp_die() a test double captured instead of halting the run.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * Thrown in place of the `wp_die()` the SSO calls on a malformed token.
 */
class WPOrg_SSO_Die_Exception extends Exception {}
