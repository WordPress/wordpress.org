<?php
/**
 * Stand-in for the Two Factor plugin's core class.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

if ( ! class_exists( 'Two_Factor_Core' ) ) {
	/**
	 * Minimal stand-in for the Two Factor plugin's core class.
	 */
	class Two_Factor_Core {

		/**
		 * Whether the given user has two-factor enabled.
		 *
		 * @param int $user_id User ID.
		 * @return bool
		 */
		public static function is_user_using_two_factor( $user_id = 0 ): bool {
			return ! empty( WPOrg_SSO_Two_Factor_State::$using_2fa[ WPOrg_SSO_Two_Factor_State::subject( $user_id ) ] );
		}
	}
}
