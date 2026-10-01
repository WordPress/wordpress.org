<?php
/**
 * The page under Manage where administrators run imports.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mailbox;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\WPOrgHelpScoutImport\Entities\Agent;
use Modules\WPOrgHelpScoutImport\Entities\Run;
use Modules\WPOrgHelpScoutImport\Jobs\ImportPage;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\People;

/**
 * Lists runs, starts them, and pauses and resumes them.
 *
 * Starting imports everything the first time, and what HelpScout changed since the last finished import after that.
 */
final class ImportController extends Controller {

	/**
	 * How far before the last finished import started the next one looks, in minutes, for clocks that disagree.
	 *
	 * @var int
	 */
	private const CHANGES_OVERLAP_MINUTES = 15;

	/**
	 * Why a mailbox can't take another run.
	 *
	 * @var string
	 */
	private const BUSY = 'An import into this mailbox is still running or paused. Finish it first.';

	/**
	 * Shows HelpScout's mailboxes, the runs so far, and the forms to start more.
	 *
	 * @param Request $request Request; `agents` names a HelpScout mailbox whose users to check.
	 * @return View
	 */
	public function index( Request $request ): View {
		$helpscout = app( HelpScout::class );
		$sources   = array();
		$error     = '';
		$people    = null;

		if ( $helpscout->is_configured() ) {
			try {
				$sources = $helpscout->mailboxes();

				$agents = (int) $request->query( 'agents' );
				if ( $agents ) {
					$people = ( new People() )->agents( $helpscout->users( $agents ) );
				}
			} catch ( \Throwable $e ) {
				$error = $e->getMessage();
			}
		}

		return view(
			'wporghelpscoutimport::index',
			array(
				'configured' => $helpscout->is_configured(),
				'error'      => $error,
				'sources'    => $sources,
				'agents'     => (int) $request->query( 'agents' ),
				'people'     => $people,
				'users'      => self::users(),
				'mailboxes'  => Mailbox::query()->orderBy( 'name' )->get(),
				'runs'       => Run::query()->with( 'mailbox' )->orderByDesc( 'id' )->limit( 100 )->get(),
			)
		);
	}

	/**
	 * Starts importing a HelpScout mailbox: everything, or what changed since its last finished import into the mailbox.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function start( Request $request ): RedirectResponse {
		$source_id = (int) $request->input( 'helpscout_mailbox_id' );
		$mailbox   = Mailbox::find( (int) $request->input( 'mailbox_id' ) );

		try {
			$source = collect( app( HelpScout::class )->mailboxes() )->firstWhere( 'id', $source_id );
		} catch ( \Throwable $e ) {
			return self::back_with_error( $e->getMessage() );
		}

		if ( ! $source || ! $mailbox ) {
			return self::back_with_error( __( 'Choose a HelpScout mailbox and a FreeScout mailbox.' ) );
		}

		$run = \DB::transaction(
			static function () use ( $source_id, $source, $mailbox ): ?Run {
				if ( ! self::lock( (int) $mailbox->id ) ) {
					return null;
				}

				$previous = Run::query()
					->where( 'helpscout_mailbox_id', $source_id )
					->where( 'mailbox_id', $mailbox->id )
					->where( 'status', Run::STATUS_DONE )
					->whereNotNull( 'started_at' )
					->orderByDesc( 'started_at' )
					->first();
				$since    = $previous ? $previous->started_at->copy()->subMinutes( self::CHANGES_OVERLAP_MINUTES ) : null;

				return self::begin( $source_id, (string) ( $source['name'] ?? '' ), (int) $mailbox->id, $since );
			}
		);

		if ( ! $run ) {
			return self::back_with_error( __( self::BUSY ) );
		}

		ImportPage::dispatch( (int) $run->id, (string) $run->token );

		$message = $run->since
			? __( 'Importing what changed in :name since its last import.', array( 'name' => $source['name'] ?? '' ) )
			: __( 'Importing :name.', array( 'name' => $source['name'] ?? '' ) );

		return redirect()->route( 'wporghelpscoutimport.index' )->with( 'flash_success', $message );
	}

	/**
	 * Saves which FreeScout user each HelpScout user's replies and notes are credited to.
	 *
	 * Nothing chosen means the user with the same email, if there's one.
	 *
	 * @param Request $request Request; `agents` maps HelpScout user IDs to FreeScout user IDs.
	 * @return RedirectResponse
	 */
	public function agents( Request $request ): RedirectResponse {
		$users = self::users()->pluck( 'id' )->map( 'intval' )->all();

		foreach ( (array) $request->input( 'agents', array() ) as $helpscout_user_id => $user_id ) {
			$helpscout_user_id = (int) $helpscout_user_id;
			$user_id           = (int) $user_id;
			if ( $helpscout_user_id <= 0 ) {
				continue;
			}

			if ( in_array( $user_id, $users, true ) ) {
				Agent::query()->updateOrCreate( array( 'helpscout_user_id' => $helpscout_user_id ), array( 'user_id' => $user_id ) );
			} else {
				Agent::query()->where( 'helpscout_user_id', $helpscout_user_id )->delete();
			}
		}

		return redirect()
			->route( 'wporghelpscoutimport.index', array( 'agents' => (int) $request->input( 'helpscout_mailbox_id' ) ) )
			->with( 'flash_success', __( 'Saved.' ) );
	}

	/**
	 * Pauses a run; the page being imported finishes its current conversation first.
	 *
	 * @param int $id Run ID.
	 * @return RedirectResponse
	 */
	public function pause( int $id ): RedirectResponse {
		$run = Run::find( $id );
		if ( $run && Run::STATUS_RUNNING === $run->status ) {
			$run->status = Run::STATUS_PAUSED;
			$run->renew_token();
			$run->save();
		}

		return redirect()->route( 'wporghelpscoutimport.index' );
	}

	/**
	 * Resumes a paused or failed run where it stopped.
	 *
	 * @param int $id Run ID.
	 * @return RedirectResponse
	 */
	public function resume( int $id ): RedirectResponse {
		$mailbox_id = (int) Run::query()->whereKey( $id )->value( 'mailbox_id' );

		$run = \DB::transaction(
			static function () use ( $id, $mailbox_id ) {
				if ( ! self::lock( $mailbox_id, $id ) ) {
					return self::BUSY;
				}

				// Read again under the lock, in case it changed since.
				$run = Run::find( $id );
				if ( ! $run || ! in_array( $run->status, array( Run::STATUS_PAUSED, Run::STATUS_FAILED ), true ) ) {
					return null;
				}

				$run->status      = Run::STATUS_RUNNING;
				$run->finished_at = null;
				$run->renew_token();
				$run->save();

				return $run;
			}
		);

		if ( self::BUSY === $run ) {
			return self::back_with_error( __( self::BUSY ) );
		}

		if ( $run instanceof Run ) {
			ImportPage::dispatch( (int) $run->id, (string) $run->token );
		}

		return redirect()->route( 'wporghelpscoutimport.index' );
	}

	/**
	 * Creates a running run; its first page is queued once the transaction is committed.
	 *
	 * @param int         $source_id   HelpScout mailbox ID.
	 * @param string      $source_name HelpScout mailbox name.
	 * @param int         $mailbox_id  FreeScout mailbox ID.
	 * @param Carbon|null $since       Only conversations changed since then.
	 * @return Run
	 */
	private static function begin( int $source_id, string $source_name, int $mailbox_id, ?Carbon $since ): Run {
		$run                         = new Run();
		$run->helpscout_mailbox_id   = $source_id;
		$run->helpscout_mailbox_name = $source_name;
		$run->mailbox_id             = $mailbox_id;
		$run->user_id                = auth()->id();
		$run->status                 = Run::STATUS_RUNNING;
		$run->since                  = $since;
		$run->started_at             = Carbon::now();
		$run->renew_token();
		$run->save();

		return $run;
	}

	/**
	 * The FreeScout users HelpScout users can be credited to: people, not robots, and not deleted.
	 *
	 * @return \Illuminate\Support\Collection
	 */
	private static function users(): \Illuminate\Support\Collection {
		return User::query()
			->where( 'type', '!=', User::TYPE_ROBOT )
			->where( 'status', '!=', User::STATUS_DELETED )
			->orderBy( 'first_name' )
			->orderBy( 'last_name' )
			->get();
	}

	/**
	 * Locks a FreeScout mailbox's row until the transaction ends, and checks no other run is open for it.
	 *
	 * One run per mailbox at a time, so two can't import the same conversation at once. The lock keeps two requests,
	 * like a double click, from both finding none.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @param int $except     Run to leave out.
	 * @return bool Whether the mailbox can take the run.
	 */
	private static function lock( int $mailbox_id, int $except = 0 ): bool {
		Mailbox::query()->whereKey( $mailbox_id )->lockForUpdate()->first();

		return ! Run::query()
			->where( 'mailbox_id', $mailbox_id )
			->whereIn( 'status', array( Run::STATUS_RUNNING, Run::STATUS_PAUSED ) )
			->where( 'id', '!=', $except )
			->exists();
	}

	/**
	 * Goes back to the page with an error.
	 *
	 * @param string $error Error.
	 * @return RedirectResponse
	 */
	private static function back_with_error( string $error ): RedirectResponse {
		return redirect()->route( 'wporghelpscoutimport.index' )->with( 'flash_error', $error );
	}
}
