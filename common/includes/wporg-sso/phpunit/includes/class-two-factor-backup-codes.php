<?php
/**
 * Stand-in for the Two Factor plugin's backup codes provider.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

if ( ! class_exists( 'Two_Factor_Backup_Codes' ) ) {
	/**
	 * Minimal stand-in for the Two Factor plugin's backup codes provider.
	 */
	class Two_Factor_Backup_Codes {

		/**
		 * How many unused backup codes the given user has left.
		 *
		 * @param WP_User $user The user.
		 * @return int
		 */
		public static function codes_remaining_for_user( $user ): int {
			return (int) ( WPOrg_SSO_Two_Factor_State::$codes_remaining[ WPOrg_SSO_Two_Factor_State::subject( $user ) ] ?? 0 );
		}
	}
}
