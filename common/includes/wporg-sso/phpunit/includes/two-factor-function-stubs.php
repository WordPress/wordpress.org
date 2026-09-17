<?php
/**
 * Stand-ins for the wporg-two-factor helpers the SSO imports.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Two_Factor;

use WPOrg_SSO_Two_Factor_State;

if ( ! function_exists( __NAMESPACE__ . '\user_should_2fa' ) ) {
	/**
	 * Whether the given user is expected to use two-factor.
	 *
	 * @param \WP_User $user The user.
	 * @return bool
	 */
	function user_should_2fa( $user ): bool {
		return ! empty( WPOrg_SSO_Two_Factor_State::$should_2fa[ WPOrg_SSO_Two_Factor_State::subject( $user ) ] );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\user_requires_2fa' ) ) {
	/**
	 * Whether two-factor is mandatory for the given user.
	 *
	 * @param \WP_User $user The user.
	 * @return bool
	 */
	function user_requires_2fa( $user ): bool {
		return ! empty( WPOrg_SSO_Two_Factor_State::$requires_2fa[ WPOrg_SSO_Two_Factor_State::subject( $user ) ] );
	}
}
