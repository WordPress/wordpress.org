<?php
/**
 * A user's Teams page.
 *
 * @package WordPressdotorg\FreeScout\WPOrgTeams
 */

declare( strict_types = 1 );

namespace Modules\WPOrgTeams\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mailbox;
use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\WPOrgTeams\Http\Middleware\TeamAccess;
use Modules\WPOrgTeams\Providers\WPOrgTeamsServiceProvider;
use Modules\WPOrgTeams\Services\Access;
use Modules\WPOrgTeams\Services\Pending;
use Modules\WPOrgTeams\Services\Teams;

/**
 * Shows and changes the teams a user is a member of, and what they give them, in place of core's Permissions page.
 */
final class UserTeamsController extends Controller {

	/**
	 * Shows a user's teams.
	 *
	 * @param int $id User ID.
	 * @return View
	 */
	public function show( int $id ): View {
		$user    = $this->user_or_abort( $id );
		$granted = Access::granted( (int) $user->id );

		return view(
			WPOrgTeamsServiceProvider::ALIAS . '::user_teams',
			array(
				'user'        => $user,
				'users'       => auth()->user()->isAdmin() ? User::sortUsers( User::nonDeleted()->get() ) : array(),
				'teams'       => Teams::all(),
				'assignable'  => Teams::assignable_by( auth()->user() )->pluck( 'id' )->map( 'intval' )->all(),
				'member_of'   => Teams::of_user( (int) $user->id )->pluck( 'id' )->map( 'intval' )->all(),
				'mailboxes'   => Mailbox::query()->whereKey( $granted['mailbox_ids'] )->get()->sortBy( 'name' ),
				'permissions' => $granted['permissions'],
				'defaults'    => array_values( array_intersect( User::getUserPermissionsList(), (array) User::getGlobalUserPermissions() ) ),
			)
		);
	}

	/**
	 * Changes a user's teams, and so their access.
	 *
	 * @param int     $id      User ID.
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function save( int $id, Request $request ): RedirectResponse {
		$user     = $this->user_or_abort( $id );
		$selected = $request->input( TeamAccess::TEAMS_FIELD );
		$selected = is_array( $selected ) ? array_map( 'intval', array_filter( $selected, 'is_numeric' ) ) : array();

		// Only the teams they may give out; the user's other teams stay as they are.
		foreach ( Teams::assignable_by( auth()->user() ) as $team ) {
			$wanted = in_array( (int) $team->id, $selected, true );
			Teams::change_members(
				(int) $team->id,
				static fn ( array $member_ids ): array => $wanted
					? array_merge( $member_ids, array( (int) $user->id ) )
					: array_diff( $member_ids, array( (int) $user->id ) )
			);
		}

		app( Pending::class )->add( array( (int) $user->id ) );
		if ( app( Pending::class )->flush() ) {
			\Session::flash( 'flash_error_floating', __( 'The teams were saved, but the access they give couldn’t be updated. Try saving again.' ) );
		} else {
			\Session::flash( 'flash_success_floating', __( 'Teams saved' ) );
		}

		return redirect()->route( 'wporgteams.user', array( 'id' => $user->id ) );
	}

	/**
	 * The user whose teams to show or change, if the logged-in user may.
	 *
	 * @param int $id User ID.
	 * @return User
	 */
	private function user_or_abort( int $id ): User {
		if ( ! Teams::active() ) {
			abort( 404 );
		}

		$user = User::query()->findOrFail( $id );
		if ( ! WPOrgTeamsServiceProvider::can_change_teams( auth()->user(), $user ) ) {
			abort( 403 );
		}

		return $user;
	}
}
