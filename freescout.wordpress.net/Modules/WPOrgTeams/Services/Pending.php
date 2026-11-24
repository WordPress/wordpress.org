<?php
/**
 * Changes to teams a request makes, applied once it's done with them.
 *
 * @package WordPressdotorg\FreeScout\WPOrgTeams
 */

declare( strict_types = 1 );

namespace Modules\WPOrgTeams\Services;

use App\User;

/**
 * Collects whose access to update while teams are saved, and what the forms asked for.
 *
 * The Teams module saves a team first and its mailboxes after, so members' access is updated once the request is done
 * with the team, not as it's saved. One per request.
 */
final class Pending {

	/**
	 * IDs of the users whose access to update.
	 *
	 * @var int[]
	 */
	private $user_ids = array();

	/**
	 * Permissions the team form asked for, for the team it saves; null if it didn't show them.
	 *
	 * @var array|null
	 */
	public $team_permissions = null;

	/**
	 * Teams the create user form asked for, for the user it creates; null if it didn't show them.
	 *
	 * @var int[]|null
	 */
	public $new_user_team_ids = null;

	/**
	 * Updates these users' access once the request is done with teams.
	 *
	 * @param int[] $user_ids User IDs.
	 * @return void
	 */
	public function add( array $user_ids ): void {
		$this->user_ids = array_values( array_unique( array_merge( $this->user_ids, array_map( 'intval', $user_ids ) ) ) );
	}

	/**
	 * Updates the access of the users collected so far.
	 *
	 * @return void
	 */
	public function flush(): void {
		$user_ids       = $this->user_ids;
		$this->user_ids = array();

		if ( ! $user_ids ) {
			return;
		}

		foreach ( User::query()->whereKey( $user_ids )->get() as $user ) {
			try {
				Access::apply( $user );
			} catch ( \Throwable $e ) {
				\Log::error( '[WPOrgTeams] Could not update the access of user ' . $user->id . ': ' . $e->getMessage() );
			}
		}
	}
}
