<?php
/**
 * Who the Plugin Review panel is for.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

use App\User;

/**
 * Tells members of the plugins team's teams apart, with the Teams module.
 *
 * Teams is a premium module, which most contributors don't have; without it, everyone who can see the plugins team's
 * mailbox is taken for a reviewer. Not final, so tests can answer for Teams.
 */
class Reviewers {

	/**
	 * How the names of the plugins team's teams start, like Plugin Reviews and Plugin Security.
	 *
	 * @var string
	 */
	private const TEAM_PREFIX = 'Plugin';

	/**
	 * The Teams module's alias.
	 *
	 * @var string
	 */
	public const TEAMS_MODULE = 'teams';

	/**
	 * The Teams module's provider, which tells teams and their members.
	 *
	 * @var string
	 */
	private const TEAMS = '\Modules\Teams\Providers\TeamsServiceProvider';

	/**
	 * Whether a user is a member of one of the plugins team's teams, or anyone is, without the Teams module.
	 *
	 * @param User|null $user User.
	 * @return bool False if Teams can't tell.
	 */
	public function includes( ?User $user ): bool {
		if ( ! $user ) {
			return false;
		}

		if ( ! \App\Module::isActive( self::TEAMS_MODULE ) ) {
			return true;
		}

		try {
			foreach ( $this->team_names( $user ) as $name ) {
				if ( str_starts_with( $name, self::TEAM_PREFIX ) ) {
					return true;
				}
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not read the teams of user ' . $user->id . ': ' . $e->getMessage() );
		}

		return false;
	}

	/**
	 * The names of the teams a user is a member of, as the Teams module tells them.
	 *
	 * @param User $user User.
	 * @return string[]
	 */
	protected function team_names( User $user ): array {
		$teams = self::TEAMS;
		if ( ! class_exists( $teams ) ) {
			return array();
		}

		$team_ids = array_map( 'intval', (array) $teams::getUserTeamIds( (int) $user->id ) );
		$names    = array();
		foreach ( $teams::getTeams( true ) as $team ) {
			if ( in_array( (int) $team->id, $team_ids, true ) ) {
				$names[] = (string) $team->first_name;
			}
		}

		return $names;
	}
}
