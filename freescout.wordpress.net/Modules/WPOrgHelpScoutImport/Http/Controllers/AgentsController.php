<?php
/**
 * The page where administrators choose who HelpScout users' replies and notes are credited to.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\WPOrgHelpScoutImport\Entities\Agent;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\People;
use Modules\WPOrgHelpScoutImport\Services\WordPressOrgUsers;

/**
 * Lists every HelpScout user once, for the whole account: a choice applies to every mailbox.
 */
final class AgentsController extends Controller {

	/**
	 * Lists HelpScout's users, optionally only those of one mailbox, with who each is credited to.
	 *
	 * @param Request $request Request; `mailbox` names a HelpScout mailbox to list the users of.
	 * @return View
	 */
	public function index( Request $request ): View {
		$mailbox_id = (int) $request->query( 'mailbox' );
		$people     = new People();
		$agents     = array();
		$mailboxes  = array();
		$error      = '';

		try {
			$directory = People::directory( app( HelpScout::class ) );
			foreach ( $directory as $user ) {
				$mailboxes += (array) $user['mailboxes'];
			}
			asort( $mailboxes );

			// Users HelpScout no longer lists only show up in imported conversations, from any mailbox.
			$listed = array_filter(
				$directory,
				static function ( array $user ) use ( $mailbox_id ): bool {
					return ! $mailbox_id || isset( $user['mailboxes'][ $mailbox_id ] );
				}
			);
			$agents = $people->agents( $mailbox_id ? $listed : array_merge( $listed, People::former( $directory ) ) );
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
		}

		return view(
			'wporghelpscoutimport::agents',
			array(
				'agents'     => $agents,
				'unmatched'  => count( array_filter( $agents, array( People::class, 'is_unmatched' ) ) ),
				'mailboxes'  => $mailboxes,
				'mailbox_id' => $mailbox_id,
				'users'      => People::creditable()->orderBy( 'first_name' )->orderBy( 'last_name' )->get(),
				'teams'      => array_values(
					array_map(
						static function ( array $team ): string {
							return trim( ( $team['firstName'] ?? '' ) . ' ' . ( $team['lastName'] ?? '' ) );
						},
						array_filter( $directory ?? array(), array( People::class, 'is_team' ) )
					)
				),
				'can_create' => WordPressOrgUsers::available(),
				'error'      => $error,
			)
		);
	}

	/**
	 * Saves who HelpScout users are credited to, for every row of the list at once.
	 *
	 * A WordPress.org username creates a FreeScout user from that account, or finds the one connected to it, and
	 * wins over a chosen user. No user chosen goes back to the user with the same email. Replies and notes already
	 * imported are credited again.
	 *
	 * @param Request $request Request, with `agents`: rows by HelpScout user ID, each with `user_id`, `username`, and
	 *                         `can_log_in`.
	 * @return RedirectResponse
	 */
	public function save( Request $request ): RedirectResponse {
		$errors  = array();
		$created = array();
		$create  = WordPressOrgUsers::available();

		foreach ( (array) $request->input( 'agents', array() ) as $helpscout_user_id => $row ) {
			$helpscout_user_id = (int) $helpscout_user_id;
			$row               = is_array( $row ) ? $row : array();
			$user_id           = (int) ( $row['user_id'] ?? 0 );
			$username          = trim( (string) ( $row['username'] ?? '' ) );
			if ( $helpscout_user_id <= 0 ) {
				continue;
			}

			if ( '' !== $username && $create ) {
				try {
					$user      = WordPressOrgUsers::for_username( $username, filter_var( $row['can_log_in'] ?? false, FILTER_VALIDATE_BOOLEAN ) );
					$user_id   = (int) $user->id;
					$created[] = $username;
				} catch ( \RuntimeException $e ) {
					$errors[] = $username . ': ' . $e->getMessage();
					continue;
				}
			}

			if ( $user_id && People::creditable()->whereKey( $user_id )->exists() ) {
				Agent::query()->updateOrCreate( array( 'helpscout_user_id' => $helpscout_user_id ), array( 'user_id' => $user_id ) );
			} else {
				Agent::query()->where( 'helpscout_user_id', $helpscout_user_id )->delete();
			}

			People::recredit( $helpscout_user_id );
		}

		$redirect = redirect()
			->route( 'wporghelpscoutimport.agents', array_filter( array( 'mailbox' => (int) $request->input( 'mailbox' ) ) ) )
			->with( 'flash_success', $created ? __( 'Saved, with users from WordPress.org for :usernames.', array( 'usernames' => implode( ', ', $created ) ) ) : __( 'Saved.' ) );

		return $errors ? $redirect->with( 'flash_error', implode( ' ', $errors ) ) : $redirect;
	}

	/**
	 * Asks HelpScout for its users again, for users or mailbox access added since.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function refresh( Request $request ): RedirectResponse {
		try {
			People::directory( app( HelpScout::class ), true );
		} catch ( \Throwable $e ) {
			return redirect()->route( 'wporghelpscoutimport.agents' )->with( 'flash_error', $e->getMessage() );
		}

		return redirect()->route( 'wporghelpscoutimport.agents', array_filter( array( 'mailbox' => (int) $request->input( 'mailbox' ) ) ) );
	}
}
