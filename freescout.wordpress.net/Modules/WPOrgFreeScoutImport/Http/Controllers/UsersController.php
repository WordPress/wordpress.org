<?php
/**
 * The page where administrators see which user here each user of the other FreeScout is credited to.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\WPOrgFreeScoutImport\Services\People;
use Modules\WPOrgFreeScoutImport\Services\Source;
use Modules\WPOrgFreeScoutImport\Services\WordPressOrgAccounts;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lists the source's users, and saves who they're credited to.
 *
 * Imports credit source users to the user with their email, or create a disabled one. This page is for those who have
 * a user here under another email.
 */
final class UsersController extends Controller {

	/**
	 * Shows the source's users with the users they're credited to.
	 *
	 * @return View
	 */
	public function index(): View {
		$source = app( Source::class );
		$users  = array();
		$error  = '';

		try {
			foreach ( People::directory( $source ) as $source_user ) {
				$users[] = array(
					'id'       => (int) ( $source_user['id'] ?? 0 ),
					'name'     => trim( ( $source_user['firstName'] ?? '' ) . ' ' . ( $source_user['lastName'] ?? '' ) ),
					'email'    => (string) ( $source_user['email'] ?? '' ),
					'existing' => People::existing( $source_user ),
				);
			}
		} catch ( \Throwable $e ) {
			$error = $e->getMessage();
		}

		usort(
			$users,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return view(
			'wporgfreescoutimport::users',
			array(
				'name'        => $source->name(),
				'users'       => $users,
				'creditable'  => People::creditable()->orderBy( 'first_name' )->orderBy( 'last_name' )->get(),
				'can_connect' => WordPressOrgAccounts::available(),
				'error'       => $error,
			)
		);
	}

	/**
	 * Saves who source users are credited to, and credits what's imported already to them.
	 *
	 * Rows left at their default are left alone.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function save( Request $request ): RedirectResponse {
		foreach ( (array) $request->input( 'users', array() ) as $source_user_id => $user_id ) {
			$user = (int) $source_user_id > 0 && (int) $user_id > 0 ? People::creditable()->whereKey( (int) $user_id )->first() : null;
			if ( $user ) {
				People::choose( (int) $source_user_id, $user );
			}
		}

		return redirect()->route( 'wporgfreescoutimport.users' )->with( 'flash_success', __( 'Saved.' ) );
	}

	/**
	 * Downloads the source's users as a CSV, to fill in their WordPress.org usernames.
	 *
	 * @return Response
	 */
	public function export(): Response {
		$source = app( Source::class );

		try {
			$csv = ( new WordPressOrgAccounts( $source ) )->export();
		} catch ( \Throwable $e ) {
			return redirect()->route( 'wporgfreescoutimport.users' )->with(
				'flash_error',
				__(
					':name couldn’t be reached: :error',
					array(
						'name'  => $source->name(),
						'error' => $e->getMessage(),
					)
				)
			);
		}

		return response(
			$csv,
			200,
			array(
				'Content-Type'        => 'text/csv; charset=UTF-8',
				'Content-Disposition' => 'attachment; filename="freescout-import-users.csv"',
			)
		);
	}

	/**
	 * Checks a CSV of source users and their WordPress.org usernames, and connects them once that's confirmed.
	 *
	 * Checking shows what connecting would do, without doing it; connecting checks again, and does it.
	 *
	 * @param Request $request Request.
	 * @return View|RedirectResponse
	 */
	public function connect( Request $request ): View|RedirectResponse {
		if ( ! WordPressOrgAccounts::available() ) {
			return redirect()->route( 'wporgfreescoutimport.users' )->with( 'flash_error', __( 'Connecting users to WordPress.org accounts needs WP.org SSO to be on.' ) );
		}

		$csv  = (string) $request->input( 'csv', '' );
		$file = $request->file( 'csv_file' );
		if ( $file && $file->isValid() ) {
			$csv = (string) file_get_contents( $file->getRealPath() );
		}

		$rows = WordPressOrgAccounts::parse( $csv );
		if ( ! $rows ) {
			return redirect()->route( 'wporgfreescoutimport.users' )->with( 'flash_error', __( 'The CSV has no rows with a wporg_username.' ) );
		}

		// Each row is a request to api.wordpress.org, on the first check.
		set_time_limit( 300 );

		$source   = app( Source::class );
		$accounts = new WordPressOrgAccounts( $source );

		try {
			$plan = $accounts->plan( $rows );
		} catch ( \Throwable $e ) {
			return redirect()->route( 'wporgfreescoutimport.users' )->with(
				'flash_error',
				__(
					':name couldn’t be reached: :error',
					array(
						'name'  => $source->name(),
						'error' => $e->getMessage(),
					)
				)
			);
		}

		if ( ! filter_var( $request->input( 'apply' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return view(
				'wporgfreescoutimport::connect',
				array(
					'name' => $source->name(),
					'plan' => $plan,
					'csv'  => $csv,
				)
			);
		}

		$result = $accounts->apply( $plan, array_map( 'intval', (array) $request->input( 'confirmed', array() ) ) );
		$errors = count(
			array_filter(
				$plan,
				static function ( array $step ): bool {
					return '' !== $step['error'];
				}
			)
		) + count( $result['skipped'] );

		$redirect = redirect()->route( 'wporgfreescoutimport.users' )->with( 'flash_success', __( 'Connected :count users to WordPress.org accounts.', array( 'count' => count( $result['done'] ) ) ) );

		return $errors ? $redirect->with( 'flash_error', __( ':count rows couldn’t be connected; check the CSV again to see why.', array( 'count' => $errors ) ) ) : $redirect;
	}

	/**
	 * Reads the source's users again.
	 *
	 * @return RedirectResponse
	 */
	public function refresh(): RedirectResponse {
		try {
			People::directory( app( Source::class ), true );
		} catch ( \Throwable $e ) {
			return redirect()->route( 'wporgfreescoutimport.users' )->with( 'flash_error', $e->getMessage() );
		}

		return redirect()->route( 'wporgfreescoutimport.users' );
	}
}
