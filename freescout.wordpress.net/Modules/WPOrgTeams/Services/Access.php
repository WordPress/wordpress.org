<?php
/**
 * Mailbox access and user permissions from teams.
 *
 * @package WordPressdotorg\FreeScout\WPOrgTeams
 */

declare( strict_types = 1 );

namespace Modules\WPOrgTeams\Services;

use App\User;

/**
 * Gives users exactly the mailboxes and user permissions their teams have.
 *
 * Core checks both on the user (`mailbox_user` rows and the `permissions` column) without a hook, so they're written
 * there. Permissions a team doesn't grant are left unset, so Manage » Settings' defaults for all users still apply.
 * "Can see only assigned conversations" isn't a team permission, and stays as it is.
 */
final class Access {

	/**
	 * Whether a user's access comes from their teams: everyone but administrators, robots, and deleted users.
	 *
	 * @param User $user User.
	 * @return bool
	 */
	public static function is_managed( User $user ): bool {
		return ! $user->isAdmin() && ! $user->isDeleted() && User::TYPE_ROBOT !== (int) $user->type;
	}

	/**
	 * What a user's teams grant.
	 *
	 * @param int $user_id User ID.
	 * @return array{mailbox_ids: int[], permissions: array} Mailbox IDs, and IDs of user permissions.
	 */
	public static function granted( int $user_id ): array {
		$mailbox_ids = array();
		$permissions = array();
		foreach ( Teams::of_user( $user_id ) as $team ) {
			$mailbox_ids = array_merge( $mailbox_ids, Teams::mailbox_ids( $team ) );
			$permissions = array_merge( $permissions, Teams::permissions( $team ) );
		}

		$mailbox_ids = array_values( array_unique( $mailbox_ids ) );
		sort( $mailbox_ids );

		return array(
			'mailbox_ids' => $mailbox_ids,
			'permissions' => array_values( array_unique( $permissions, SORT_REGULAR ) ),
		);
	}

	/**
	 * Gives a user what their teams grant, and takes away what they don't.
	 *
	 * @param User $user    User.
	 * @param bool $dry_run Whether to only tell what would change.
	 * @return array{mailboxes_added: int[], mailboxes_removed: int[], permissions_added: array, permissions_removed: array}
	 */
	public static function apply( User $user, bool $dry_run = false ): array {
		$changes = array(
			'mailboxes_added'     => array(),
			'mailboxes_removed'   => array(),
			'permissions_added'   => array(),
			'permissions_removed' => array(),
		);
		if ( ! self::is_managed( $user ) ) {
			return $changes;
		}

		$granted = self::granted( (int) $user->id );
		$current = array_map( 'intval', $user->mailboxes()->pluck( 'mailboxes.id' )->all() );

		$changes['mailboxes_added']   = array_values( array_diff( $granted['mailbox_ids'], $current ) );
		$changes['mailboxes_removed'] = array_values( array_diff( $current, $granted['mailbox_ids'] ) );

		$own         = is_array( $user->permissions ) ? $user->permissions : array();
		$permissions = $own;
		$defaults    = (array) User::getGlobalUserPermissions();
		foreach ( User::getUserPermissionsList() as $permission ) {
			unset( $permissions[ $permission ] );

			$grants = in_array( $permission, $granted['permissions'], true );
			if ( $grants ) {
				$permissions[ $permission ] = 1;
			}

			$had = $user->hasPermission( $permission );
			$has = $grants || in_array( $permission, $defaults, false ); // phpcs:ignore WordPress.PHP.StrictInArray.FoundNonStrictFalse -- Settings keep core's IDs as strings.
			if ( $has && ! $had ) {
				$changes['permissions_added'][] = $permission;
			} elseif ( $had && ! $has ) {
				$changes['permissions_removed'][] = $permission;
			}
		}

		if ( $dry_run ) {
			return $changes;
		}

		if ( $changes['mailboxes_added'] || $changes['mailboxes_removed'] ) {
			// IDs only, so the settings of the mailboxes they keep, like hiding one or managing it, stay.
			$user->mailboxes()->sync( $granted['mailbox_ids'] );
			$user->syncPersonalFolders( $granted['mailbox_ids'] );
			$user->clearWebsiteNotificationsCache();
		}

		ksort( $own );
		ksort( $permissions );
		if ( $permissions !== $own ) {
			$user->permissions = $permissions;
			$user->save();
		}

		return $changes;
	}

	/**
	 * The users with access to a mailbox, for its Permissions page: its teams' members, instead of who was checked.
	 *
	 * @param int[] $user_ids   IDs the page sent, plus its teams, which the Teams module adds.
	 * @param int   $mailbox_id Mailbox ID.
	 * @return int[]
	 */
	public static function mailbox_user_ids( array $user_ids, int $mailbox_id ): array {
		$ids = array();

		// Teams and robots keep their access, as the page left it.
		foreach ( User::query()->whereKey( array_map( 'intval', $user_ids ) )->get() as $user ) {
			if ( ! self::is_managed( $user ) ) {
				$ids[] = (int) $user->id;
			}
		}

		$members = array();
		foreach ( Teams::all() as $team ) {
			if ( in_array( $mailbox_id, Teams::mailbox_ids( $team ), true ) ) {
				$members = array_merge( $members, Teams::member_ids( $team ) );
			}
		}
		foreach ( User::query()->whereKey( array_unique( $members ) )->get() as $member ) {
			if ( self::is_managed( $member ) ) {
				$ids[] = (int) $member->id;
			}
		}

		return array_values( array_unique( $ids ) );
	}
}
