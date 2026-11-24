<?php
/**
 * The Teams module's teams, as it stores them.
 *
 * @package WordPressdotorg\FreeScout\WPOrgTeams
 */

declare( strict_types = 1 );

namespace Modules\WPOrgTeams\Services;

use App\User;
use Illuminate\Support\Collection;

/**
 * Reads and changes teams the way the Teams module stores them, without its classes.
 *
 * A team is a deleted robot user named "<name> Team", with an `fsteam-<number>@example.org` email that gets the deleted
 * suffix once the team is deleted. Its members' IDs are a JSON list in its `emails` column, and its mailboxes are its
 * `mailbox_user` rows. Teams is a premium module most contributors don't have, so this reads the rows rather than
 * calling it.
 */
final class Teams {

	/**
	 * The Teams module's alias.
	 *
	 * @var string
	 */
	public const MODULE = 'teams';

	/**
	 * Last name of every team.
	 *
	 * @var string
	 */
	public const LAST_NAME = 'Team';

	/**
	 * How team emails start.
	 *
	 * @var string
	 */
	public const EMAIL_PREFIX = 'fsteam-';

	/**
	 * Whether the Teams module is on.
	 *
	 * @return bool
	 */
	public static function active(): bool {
		try {
			return (bool) \App\Module::isActive( self::MODULE );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgTeams] Could not tell whether the Teams module is on: ' . $e->getMessage() );

			return false;
		}
	}

	/**
	 * Whether a user row is a team, deleted or not.
	 *
	 * @param User $user User row.
	 * @return bool
	 */
	public static function is_team( User $user ): bool {
		return User::STATUS_DELETED === (int) $user->status
			&& self::LAST_NAME === $user->last_name
			&& 1 === preg_match( '/^' . preg_quote( self::EMAIL_PREFIX, '/' ) . '\d+@example\.org(' . preg_quote( User::EMAIL_DELETED_SUFFIX, '/' ) . '.*)?$/', (string) $user->email );
	}

	/**
	 * Whether a team has been deleted.
	 *
	 * @param User $team Team.
	 * @return bool
	 */
	public static function is_deleted( User $team ): bool {
		return str_contains( (string) $team->email, User::EMAIL_DELETED_SUFFIX );
	}

	/**
	 * The teams that aren't deleted, by name.
	 *
	 * @return Collection Teams.
	 */
	public static function all(): Collection {
		return User::query()
			->where( 'status', User::STATUS_DELETED )
			->where( 'last_name', self::LAST_NAME )
			->where( 'email', 'like', self::EMAIL_PREFIX . '%' )
			->get()
			->filter( static fn ( User $team ): bool => self::is_team( $team ) && ! self::is_deleted( $team ) )
			->sortBy( 'first_name', SORT_NATURAL | SORT_FLAG_CASE )
			->values();
	}

	/**
	 * The teams a user is a member of.
	 *
	 * @param int $user_id User ID.
	 * @return Collection Teams.
	 */
	public static function of_user( int $user_id ): Collection {
		return self::all()->filter( static fn ( User $team ): bool => in_array( $user_id, self::member_ids( $team ), true ) )->values();
	}

	/**
	 * The teams someone may add users to, or take them out of: every team for administrators, else their own.
	 *
	 * @param User|null $by Who's asking.
	 * @return Collection Teams.
	 */
	public static function assignable_by( ?User $by ): Collection {
		if ( ! $by ) {
			return new Collection();
		}

		return $by->isAdmin() ? self::all() : self::of_user( (int) $by->id );
	}

	/**
	 * A team's members' IDs.
	 *
	 * @param User $team Team.
	 * @return int[]
	 */
	public static function member_ids( User $team ): array {
		return self::ids_from_json( $team->emails );
	}

	/**
	 * Members' IDs from a team's `emails` column.
	 *
	 * @param mixed $json The column's value.
	 * @return int[]
	 */
	public static function ids_from_json( $json ): array {
		if ( ! is_string( $json ) || '' === $json ) {
			return array();
		}

		$ids = json_decode( $json, true );
		if ( ! is_array( $ids ) ) {
			return array();
		}

		return array_values( array_unique( array_map( 'intval', array_filter( $ids, 'is_numeric' ) ) ) );
	}

	/**
	 * Sets a team's members; the caller saves the team.
	 *
	 * @param User  $team       Team.
	 * @param int[] $member_ids Members' IDs.
	 * @return void
	 */
	public static function set_member_ids( User $team, array $member_ids ): void {
		$team->emails = json_encode( array_values( array_unique( array_map( 'intval', $member_ids ) ) ) );
	}

	/**
	 * The IDs of the mailboxes a team has.
	 *
	 * @param User $team Team.
	 * @return int[]
	 */
	public static function mailbox_ids( User $team ): array {
		return array_map( 'intval', $team->mailboxes()->pluck( 'mailboxes.id' )->all() );
	}

	/**
	 * The user permissions a team grants its members.
	 *
	 * @param User $team Team.
	 * @return array IDs of the permissions, among those FreeScout offers.
	 */
	public static function permissions( User $team ): array {
		$granted = is_array( $team->permissions ) ? $team->permissions : array();

		return array_values(
			array_filter(
				User::getUserPermissionsList(),
				static fn ( $permission ): bool => ! empty( $granted[ $permission ] )
			)
		);
	}

	/**
	 * Sets the user permissions a team grants its members; the caller saves the team.
	 *
	 * @param User  $team        Team.
	 * @param array $permissions IDs of the permissions; those FreeScout doesn't offer are left out.
	 * @return void
	 */
	public static function set_permissions( User $team, array $permissions ): void {
		$granted = array();
		foreach ( User::getUserPermissionsList() as $permission ) {
			if ( in_array( (string) $permission, array_map( 'strval', $permissions ), true ) ) {
				$granted[ $permission ] = 1;
			}
		}

		$team->permissions = $granted;
	}
}
