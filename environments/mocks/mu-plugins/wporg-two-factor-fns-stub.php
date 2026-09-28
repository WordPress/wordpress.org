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
	 * Callers pass a WP_User or a user ID; the real function links to that user's
	 * account screen, which has no local equivalent.
	 *
	 * @param WP_User|int|null $user User to link to, or null for the current user.
	 * @return string
	 */
	function get_edit_account_url( $user = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Signature has to match the real function.
		return home_url( '/' );
	}
}
