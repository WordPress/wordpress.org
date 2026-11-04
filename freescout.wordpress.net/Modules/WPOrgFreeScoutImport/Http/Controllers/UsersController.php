<?php
/**
 * The page where administrators see which user here each of a source's users is credited to.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\WPOrgFreeScoutImport\Entities\Source;
use Modules\WPOrgFreeScoutImport\Services\People;
use Modules\WPOrgFreeScoutImport\Services\RemoteApi;
use Modules\WPOrgFreeScoutImport\Services\WordPressOrgAccounts;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lists a source's users, saves who they're credited to, and connects them to WordPress.org accounts from a CSV.
 *
 * Imports credit remote users to the user with their email, or create a disabled one. This page is for those who
 * have a user here under another email, and for those who'll work here.
 */
final class UsersController extends Controller {

	/**
	 * Shows a source's users with the users they're credited to.
	 *
	 * @param int $id Source ID.
	 * @return View
	 */
	public function index( int $id ): View {
		$source = Source::query()->findOrFail( $id );
		$users  = array();
		$error  = '';

		try {
			foreach ( People::directory( RemoteApi::for_source( $source ) ) as $remote_user ) {
				$users[] = array(
					'id'       => (int) ( $remote_user['id'] ?? 0 ),
					'name'     => trim( ( $remote_user['firstName'] ?? '' ) . ' ' . ( $remote_user['lastName'] ?? '' ) ),
					'email'    => (string) ( $remote_user['email'] ?? '' ),
					'existing' => People::existing( (int) $source->id, $remote_user ),
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
				'source'      => $source,
				'name'        => $source->name,
				'users'       => $users,
				'creditable'  => People::creditable()->orderBy( 'first_name' )->orderBy( 'last_name' )->get(),
				'can_connect' => WordPressOrgAccounts::available(),
				'error'       => $error,
			)
		);
	}

	/**
	 * Saves who a source's users are credited to, and credits what's imported already to them.
	 *
	 * Rows left at their default are left alone.
	 *
	 * @param Request $request Request.
	 * @param int     $id      Source ID.
	 * @return RedirectResponse
	 */
	public function save( Request $request, int $id ): RedirectResponse {
		$source = Source::query()->findOrFail( $id );

		foreach ( (array) $request->input( 'users', array() ) as $remote_user_id => $user_id ) {
			$user = (int) $remote_user_id > 0 && (int) $user_id > 0 ? People::creditable()->whereKey( (int) $user_id )->first() : null;
			if ( $user ) {
				People::choose( (int) $source->id, (int) $remote_user_id, $user );
			}
		}

		return self::back( $source )->with( 'flash_success', __( 'Saved.' ) );
	}

	/**
	 * Downloads a source's users as a CSV, to fill in their WordPress.org usernames.
	 *
	 * @param int $id Source ID.
	 * @return Response
	 */
	public function export( int $id ): Response {
		$source = Source::query()->findOrFail( $id );

		try {
			$csv = ( new WordPressOrgAccounts( RemoteApi::for_source( $source ) ) )->export();
		} catch ( \Throwable $e ) {
			return self::back( $source )->with( 'flash_error', self::unreachable( $source, $e ) );
		}

		return response(
			$csv,
			200,
			array(
				'Content-Type'        => 'text/csv; charset=UTF-8',
				'Content-Disposition' => 'attachment; filename="freescout-import-users-' . (int) $source->id . '.csv"',
			)
		);
	}

	/**
	 * Checks a CSV of a source's users and their WordPress.org usernames, and connects them once that's confirmed.
	 *
	 * Checking shows what connecting would do, without doing it; connecting checks again, and does it.
	 *
	 * @param Request $request Request.
	 * @param int     $id      Source ID.
	 * @return View|RedirectResponse
	 */
	public function connect( Request $request, int $id ): View|RedirectResponse {
		$source = Source::query()->findOrFail( $id );

		if ( ! WordPressOrgAccounts::available() ) {
			return self::back( $source )->with( 'flash_error', __( 'Connecting users to WordPress.org accounts needs WP.org SSO to be on.' ) );
		}

		$csv  = (string) $request->input( 'csv', '' );
		$file = $request->file( 'csv_file' );
		if ( $file && $file->isValid() ) {
			$csv = (string) file_get_contents( $file->getRealPath() );
		}

		$rows = WordPressOrgAccounts::parse( $csv );
		if ( ! $rows ) {
			return self::back( $source )->with( 'flash_error', __( 'The CSV has no rows with a wporg_username.' ) );
		}

		// Each row is a request to api.wordpress.org, on the first check.
		set_time_limit( 300 );

		$accounts = new WordPressOrgAccounts( RemoteApi::for_source( $source ) );

		try {
			$plan = $accounts->plan( $rows );
		} catch ( \Throwable $e ) {
			return self::back( $source )->with( 'flash_error', self::unreachable( $source, $e ) );
		}

		if ( ! filter_var( $request->input( 'apply' ), FILTER_VALIDATE_BOOLEAN ) ) {
			return view(
				'wporgfreescoutimport::connect',
				array(
					'source' => $source,
					'name'   => $source->name,
					'plan'   => $plan,
					'csv'    => $csv,
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

		$redirect = self::back( $source )->with( 'flash_success', __( 'Connected :count users to WordPress.org accounts.', array( 'count' => count( $result['done'] ) ) ) );

		return $errors ? $redirect->with( 'flash_error', __( ':count rows couldn’t be connected; check the CSV again to see why.', array( 'count' => $errors ) ) ) : $redirect;
	}

	/**
	 * Reads a source's users again.
	 *
	 * @param int $id Source ID.
	 * @return RedirectResponse
	 */
	public function refresh( int $id ): RedirectResponse {
		$source = Source::query()->findOrFail( $id );

		try {
			People::directory( RemoteApi::for_source( $source ), true );
		} catch ( \Throwable $e ) {
			return self::back( $source )->with( 'flash_error', $e->getMessage() );
		}

		return self::back( $source );
	}

	/**
	 * Goes back to a source's Users page.
	 *
	 * @param Source $source Source.
	 * @return RedirectResponse
	 */
	private static function back( Source $source ): RedirectResponse {
		return redirect()->route( 'wporgfreescoutimport.users', array( 'id' => $source->id ) );
	}

	/**
	 * Says a source couldn't be reached.
	 *
	 * @param Source     $source Source.
	 * @param \Throwable $e      What went wrong.
	 * @return string
	 */
	private static function unreachable( Source $source, \Throwable $e ): string {
		return __(
			':name couldn’t be reached: :error',
			array(
				'name'  => $source->name,
				'error' => $e->getMessage(),
			)
		);
	}
}
