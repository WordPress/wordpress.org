<?php
/**
 * The page where administrators see which FreeScout user each HelpScout user is credited to, and which team each team is.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\People;
use Modules\WPOrgHelpScoutImport\Services\WordPressOrgAccounts;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lists HelpScout's users and teams, and saves who they're credited to.
 *
 * Imports create FreeScout users for HelpScout users without one. This page is for those who already have one under
 * another email, and for HelpScout's teams.
 */
final class AgentsController extends Controller {

	/**
	 * Shows HelpScout's users, or one mailbox's, with their FreeScout users, and HelpScout's teams with their teams.
	 *
	 * @param Request $request Request.
	 * @return View
	 */
	public function index( Request $request ): View {
		$mailbox_id = (int) $request->query( 'mailbox' );
		$agents     = array();
		$teams      = array();
		$mailboxes  = array();
		$error      = '';

		try {
			$directory = ( new People( app( HelpScout::class ) ) )->directory();
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
			$agents = People::agents( $mailbox_id ? $listed : array_merge( $listed, People::former( $directory ) ) );
			$teams  = People::teams( $directory );
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
		}

		return view(
			'wporghelpscoutimport::agents',
			array(
				'agents'          => $agents,
				'teams'           => $teams,
				'mailboxes'       => $mailboxes,
				'mailbox_id'      => $mailbox_id,
				'users'           => People::creditable()->orderBy( 'first_name' )->orderBy( 'last_name' )->get(),
				'freescout_teams' => People::freescout_teams()->orderBy( 'first_name' )->get(),
				'can_connect'     => WordPressOrgAccounts::available(),
				'error'           => $error,
			)
		);
	}

	/**
	 * Saves who HelpScout users and teams are credited to, and credits what's imported already to them.
	 *
	 * Rows left at their default are left alone: they get the user with their email, or a new one, when imported.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function save( Request $request ): RedirectResponse {
		// People can only be credited to people, and teams to teams.
		$choices = array(
			'agents' => People::creditable(),
			'teams'  => People::freescout_teams(),
		);

		foreach ( $choices as $field => $query ) {
			foreach ( (array) $request->input( $field, array() ) as $helpscout_id => $user_id ) {
				$user = (int) $helpscout_id > 0 && (int) $user_id > 0 ? ( clone $query )->whereKey( (int) $user_id )->first() : null;
				if ( $user ) {
					People::choose( (int) $helpscout_id, $user );
				}
			}
		}

		return redirect()
			->route( 'wporghelpscoutimport.agents', array_filter( array( 'mailbox' => (int) $request->input( 'mailbox' ) ) ) )
			->with( 'flash_success', __( 'Saved.' ) );
	}

	/**
	 * Downloads HelpScout's users as a CSV, to fill in their WordPress.org usernames.
	 *
	 * @return Response
	 */
	public function export(): Response {
		$csv = ( new WordPressOrgAccounts( new People( app( HelpScout::class ) ) ) )->export();

		return response(
			$csv,
			200,
			array(
				'Content-Type'        => 'text/csv; charset=UTF-8',
				'Content-Disposition' => 'attachment; filename="helpscout-users.csv"',
			)
		);
	}

	/**
	 * Checks a CSV of HelpScout users and their WordPress.org usernames, and connects them once that's confirmed.
	 *
	 * Checking shows what connecting would do, without doing it; connecting checks again, and does it.
	 *
	 * @param Request $request Request.
	 * @return View|RedirectResponse
	 */
	public function connect( Request $request ): View|RedirectResponse {
		if ( ! WordPressOrgAccounts::available() ) {
			return redirect()->route( 'wporghelpscoutimport.agents' )->with( 'flash_error', __( 'Connecting users to WordPress.org accounts needs WP.org SSO to be on.' ) );
		}

		$csv  = (string) $request->input( 'csv', '' );
		$file = $request->file( 'csv_file' );
		if ( $file && $file->isValid() ) {
			$csv = (string) file_get_contents( $file->getRealPath() );
		}

		$rows = WordPressOrgAccounts::parse( $csv );
		if ( ! $rows ) {
			return redirect()->route( 'wporghelpscoutimport.agents' )->with( 'flash_error', __( 'The CSV has no rows with a wporg_username.' ) );
		}

		// Each row is a request to api.wordpress.org, on the first check.
		set_time_limit( 300 );

		$accounts = new WordPressOrgAccounts( new People( app( HelpScout::class ) ) );
		$plan     = $accounts->plan( $rows );

		if ( ! filter_var( $request->input( 'apply' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return view(
				'wporghelpscoutimport::connect',
				array(
					'plan' => $plan,
					'csv'  => $csv,
				)
			);
		}

		$result = $accounts->apply( $plan );
		$errors = count(
			array_filter(
				$plan,
				static function ( array $step ): bool {
					return '' !== $step['error'];
				}
			)
		) + count( $result['skipped'] );

		$redirect = redirect()->route( 'wporghelpscoutimport.agents' )->with( 'flash_success', __( 'Connected :count HelpScout users to WordPress.org accounts.', array( 'count' => count( $result['done'] ) ) ) );

		return $errors ? $redirect->with( 'flash_error', __( ':count rows couldn’t be connected; check the CSV again to see why.', array( 'count' => $errors ) ) ) : $redirect;
	}

	/**
	 * Asks HelpScout for its users again.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function refresh( Request $request ): RedirectResponse {
		try {
			( new People( app( HelpScout::class ) ) )->directory( true );
		} catch ( \Throwable $e ) {
			return redirect()->route( 'wporghelpscoutimport.agents' )->with( 'flash_error', $e->getMessage() );
		}

		return redirect()->route( 'wporghelpscoutimport.agents', array_filter( array( 'mailbox' => (int) $request->input( 'mailbox' ) ) ) );
	}
}
