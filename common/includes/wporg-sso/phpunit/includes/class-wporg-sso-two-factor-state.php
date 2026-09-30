<?php
/**
 * The answers the two-factor test stubs give.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * Two-factor state, held per user.
 *
 * Keyed by user so a test can tell whether the SSO asked about the right
 * person. A stub that answered the same way whoever it was handed would let
 * the nags consult the current user, or nobody at all, and say nothing.
 */
class WPOrg_SSO_Two_Factor_State {

	/**
	 * Users who are expected to use two-factor, keyed by ID.
	 *
	 * @var bool[]
	 */
	public static array $should_2fa = array();

	/**
	 * Users for whom two-factor is mandatory rather than encouraged, keyed by ID.
	 *
	 * @var bool[]
	 */
	public static array $requires_2fa = array();

	/**
	 * Users who have two-factor enabled, keyed by ID.
	 *
	 * @var bool[]
	 */
	public static array $using_2fa = array();

	/**
	 * How many backup codes each user has left, keyed by ID.
	 *
	 * @var int[]
	 */
	public static array $codes_remaining = array();

	/**
	 * Forgets every user's state.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$should_2fa      = array();
		self::$requires_2fa    = array();
		self::$using_2fa       = array();
		self::$codes_remaining = array();
	}

	/**
	 * The ID of the user a question is about.
	 *
	 * @throws RuntimeException When the caller did not say who it is asking about.
	 *
	 * @param WP_User|int|null $user The user, or their ID.
	 * @return int
	 */
	public static function subject( $user ): int {
		$user_id = $user instanceof WP_User ? $user->ID : (int) $user;

		if ( ! $user_id ) {
			/*
			 * Loud on purpose. Answering "false" here would let the SSO ask
			 * about nobody -- or about the wrong person -- and still pass.
			 */
			throw new RuntimeException( 'The two-factor stack was asked a question about nobody.' );
		}

		return $user_id;
	}
}
