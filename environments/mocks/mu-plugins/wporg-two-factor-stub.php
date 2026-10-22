<?php
/**
 * Plugin Name: WordPress.org Two-Factor (local stub)
 * Description: Stubs the Two Factor plugin's Two_Factor_Core class that the Theme
 *              Directory upload page depends on, so it renders locally without the
 *              full two-factor stack. Pretends the current user has passed
 *              two-factor so the upload form is shown. A no-op when the real class
 *              is present. See wporg-two-factor-fns-stub.php for the wporg helpers.
 *
 * @package theme-directory-env
 */

if ( ! class_exists( 'Two_Factor_Core' ) ) {
	/**
	 * Minimal stand-in for the Two Factor plugin's core class.
	 */
	class Two_Factor_Core {
		/**
		 * Report every user as using two-factor so upload forms render.
		 *
		 * @param int $user_id User ID. Unused; kept to match the real signature.
		 * @return bool
		 */
		public static function is_user_using_two_factor( $user_id = 0 ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Stub must match Two_Factor_Core::is_user_using_two_factor().
			return true;
		}
	}
}
