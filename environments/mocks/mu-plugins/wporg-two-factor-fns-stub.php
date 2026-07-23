<?php
/**
 * Plugin Name: WordPress.org Two-Factor Helpers (local stub)
 * Description: Stubs the WordPressdotorg\Two_Factor onboarding helper from the
 *              wporg-two-factor plugin that the Theme Directory upload page uses.
 *              A no-op when the real function is present. See
 *              wporg-two-factor-revalidation-stub.php for the revalidation helpers.
 *
 * @package theme-directory-env
 */

namespace WordPressdotorg\Two_Factor;

if ( ! function_exists( __NAMESPACE__ . '\get_onboarding_account_url' ) ) {
	/**
	 * URL where a user would enable two-factor. Points at the local home.
	 *
	 * @return string
	 */
	function get_onboarding_account_url() {
		return home_url( '/' );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\get_edit_account_url' ) ) {
	/**
	 * URL for editing account details via the new two-factor interface. Points at the local home.
	 *
	 * @return string
	 */
	function get_edit_account_url( $username = null ) {
		return home_url( '/' );
	}
}
