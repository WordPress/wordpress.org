<?php
/**
 * Choices agents make in their profile, on or off.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Services;

use App\User;

/**
 * Keeps an agent's choice with core's options, under its option's name followed by their user ID. Off, the default,
 * isn't stored.
 */
final class UserChoice {

	/**
	 * Whether an agent turned a choice on.
	 *
	 * @param User   $user   Agent.
	 * @param string $option Option's name, without the user ID.
	 * @return bool Whether they did.
	 */
	public static function get( User $user, string $option ): bool {
		return (bool) \Option::get( $option . $user->id, false );
	}

	/**
	 * Turns a choice on or off for an agent.
	 *
	 * @param User   $user    Agent.
	 * @param string $option  Option's name, without the user ID.
	 * @param bool   $enabled Whether it's on.
	 * @return void
	 */
	public static function set( User $user, string $option, bool $enabled ): void {
		if ( $enabled ) {
			\Option::set( $option . $user->id, 1 );
			// Core's per-request cache, which setting and removing options leave as it was.
			unset( \Option::$cache[ $option . $user->id ] );
		} else {
			self::forget( (int) $user->id, $option );
		}
	}

	/**
	 * Forgets a choice: off, or of a deleted agent.
	 *
	 * @param int    $user_id User ID.
	 * @param string $option  Option's name, without the user ID.
	 * @return void
	 */
	public static function forget( int $user_id, string $option ): void {
		\Option::remove( $option . $user_id );
		unset( \Option::$cache[ $option . $user_id ] );
	}
}
