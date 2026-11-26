<?php
/**
 * Hooks into the forms that change teams and access.
 *
 * @package WordPressdotorg\FreeScout\WPOrgTeams
 */

declare( strict_types = 1 );

namespace Modules\WPOrgTeams\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Http\Request;
use Modules\WPOrgTeams\Services\Pending;
use Modules\WPOrgTeams\Services\Teams;
use Symfony\Component\HttpFoundation\Response;

/**
 * Takes what the module's fields sent before the Teams module and core handle the forms, and updates access after.
 *
 * - The team form: the permissions the team grants. Members' access is updated once Teams saved the team.
 * - Deleting a team: its members lose what it granted.
 * - The create user form: the new user's teams, instead of mailboxes.
 * - A user's Permissions page: it's their Teams page now.
 */
final class TeamAccess {

	/**
	 * The Teams module's action that saves a team.
	 *
	 * @var string
	 */
	public const TEAM_SAVE_ACTION = 'Modules\Teams\Http\Controllers\TeamsController@updateSave';

	/**
	 * The Teams module's AJAX action, which deletes teams.
	 *
	 * @var string
	 */
	public const TEAM_AJAX_ACTION = 'Modules\Teams\Http\Controllers\TeamsController@ajax';

	/**
	 * Core's action that creates a user.
	 *
	 * @var string
	 */
	public const CREATE_USER_ACTION = 'App\Http\Controllers\UsersController@createSave';

	/**
	 * Core's actions of a user's Permissions page.
	 *
	 * @var string[]
	 */
	public const PERMISSIONS_ACTIONS = array(
		'App\Http\Controllers\UsersController@permissions',
		'App\Http\Controllers\UsersController@permissionsSave',
	);

	/**
	 * Field the team form sends when it showed the permissions, so a form without them can't clear them.
	 *
	 * @var string
	 */
	public const PERMISSIONS_SHOWN_FIELD = 'wporgteams_permissions_shown';

	/**
	 * Field of the permissions a team grants.
	 *
	 * @var string
	 */
	public const PERMISSIONS_FIELD = 'wporgteams_permissions';

	/**
	 * Field of a user's teams.
	 *
	 * @var string
	 */
	public const TEAMS_FIELD = 'wporgteams_teams';

	/**
	 * Handles a request.
	 *
	 * @param Request $request Request.
	 * @param Closure $next    Next handler.
	 * @return Response
	 */
	public function handle( Request $request, Closure $next ): Response {
		$action = $request->route() ? (string) $request->route()->getActionName() : '';

		$watched = array_merge( array( self::TEAM_SAVE_ACTION, self::TEAM_AJAX_ACTION, self::CREATE_USER_ACTION ), self::PERMISSIONS_ACTIONS );
		if ( ! in_array( $action, $watched, true ) || ! Teams::active() ) {
			return $next( $request );
		}

		if ( in_array( $action, self::PERMISSIONS_ACTIONS, true ) ) {
			return redirect()->route( 'wporgteams.user', array( 'id' => $request->route( 'id' ) ) );
		}

		$pending = app( Pending::class );

		if ( self::TEAM_SAVE_ACTION === $action && $request->has( self::PERMISSIONS_SHOWN_FIELD ) ) {
			$pending->team_permissions = self::strings( $request->input( self::PERMISSIONS_FIELD ) );
		}

		if ( self::CREATE_USER_ACTION === $action ) {
			// Access comes from teams, so the form's mailboxes would only be taken away again.
			$request->offsetUnset( 'mailboxes' );

			$user = $request->user();
			if ( $user instanceof User && $user->can( 'create', User::class ) ) {
				$team_ids                   = array_map( 'intval', self::strings( $request->input( self::TEAMS_FIELD ) ) );
				$pending->new_user_team_ids = array_values( array_intersect( Teams::assignable_by( $user )->pluck( 'id' )->map( 'intval' )->all(), $team_ids ) );
			}
		}

		$response = $next( $request );

		$pending->team_permissions  = null;
		$pending->new_user_team_ids = null;
		$pending->flush();

		return $response;
	}

	/**
	 * A list of strings from a form field.
	 *
	 * @param mixed $value Field value.
	 * @return string[]
	 */
	private static function strings( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		return array_values( array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
	}
}
