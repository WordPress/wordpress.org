<?php
/**
 * FreeScout passwords of users who log in with WordPress.org.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Services;

use App\User;

/**
 * Gives users core's "no password" marker, so core doesn't ask them for a password they don't have.
 *
 * Only administrators' break-glass passwords, from `wporgsso:password`, are kept.
 */
final class Passwords {

	/**
	 * Option prefix, followed by a user ID, marking that `wporgsso:password` gave them a break-glass password.
	 *
	 * @var string
	 */
	private const OPTION_BREAK_GLASS = 'wporgsso.break_glass.';

	/**
	 * Replaces a user's password with core's marker for users who haven't set one. Doesn't save the user.
	 *
	 * The marker is encrypted, not hashed, so no password matches it.
	 *
	 * @param User $user FreeScout user.
	 * @return void
	 */
	public static function clear( User $user ): void {
		if ( ! method_exists( User::class, 'getDummyPassword' ) || ! method_exists( $user, 'isDummyPassword' ) ) {
			return;
		}

		if ( $user->password && $user->isDummyPassword() ) {
			return;
		}

		$user->password = User::getDummyPassword();
	}

	/**
	 * Clears a user's password, unless it's an administrator's break-glass password. Doesn't save the user.
	 *
	 * @param User $user FreeScout user.
	 * @return void
	 */
	public static function clear_unless_break_glass( User $user ): void {
		if ( self::has_break_glass( $user ) ) {
			return;
		}

		self::clear( $user );
	}

	/**
	 * Records that a user's password is a break-glass password.
	 *
	 * @param User $user FreeScout administrator.
	 * @return void
	 */
	public static function mark_break_glass( User $user ): void {
		\Option::set( self::OPTION_BREAK_GLASS . $user->id, 1 );
	}

	/**
	 * Whether a user is an administrator with a break-glass password.
	 *
	 * @param User $user FreeScout user.
	 * @return bool
	 */
	public static function has_break_glass( User $user ): bool {
		// Not core's option cache, which outlives a request in tests.
		return $user->isAdmin() && (bool) \Option::get( self::OPTION_BREAK_GLASS . $user->id, 0, true, false );
	}
}
