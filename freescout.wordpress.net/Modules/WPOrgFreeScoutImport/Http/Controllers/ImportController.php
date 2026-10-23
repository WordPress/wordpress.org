<?php
/**
 * The page under Manage where administrators run imports.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mailbox;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\WPOrgFreeScoutImport\Entities\ImportedConversation;
use Modules\WPOrgFreeScoutImport\Entities\MessageId;
use Modules\WPOrgFreeScoutImport\Entities\Source;
use Modules\WPOrgFreeScoutImport\Entities\Run;
use Modules\WPOrgFreeScoutImport\Jobs\ImportPage;
use Modules\WPOrgFreeScoutImport\Services\RemoteApi;

/**
 * Lists the sources and runs, starts runs, and pauses, resumes, cancels, and retries them; and finds conversations by
 * the number they had in a source.
 *
 * Starting imports everything the first time, and what the source changed since the last finished import after that.
 */
final class ImportController extends Controller {

	/**
	 * How far before the last finished import started the next one looks, in minutes, for clocks that disagree.
	 *
	 * @var int
	 */
	private const CHANGES_OVERLAP_MINUTES = 15;

	/**
	 * Why another run can't start.
	 *
	 * @var string
	 */
	private const BUSY = 'An import is still running or paused. Finish or cancel it first.';

	/**
	 * Shows the sources with their mailboxes, the runs so far, and the forms to start more.
	 *
	 * @return View
	 */
	public function index(): View {
		$sources = array();

		foreach ( Source::query()->orderBy( 'name' )->get() as $source ) {
			$api       = RemoteApi::for_source( $source );
			$mailboxes = array();
			$error     = '';

			if ( $api->is_configured() ) {
				try {
					$mailboxes = $api->mailboxes();
				} catch ( \Throwable $e ) {
					$error = $e->getMessage();
				}
			}

			$sources[] = array(
				'source'      => $source,
				'configured'  => $api->is_configured(),
				'mailboxes'   => $mailboxes,
				'error'       => $error,
				'message_ids' => MessageId::query()->where( 'source_id', $source->id )->count(),
				'deletable'   => ! $source->has_imports(),
			);
		}

		$runs = Run::query()->with( array( 'mailbox', 'source' ) )->orderByDesc( 'id' )->limit( 100 )->get();

		return view(
			'wporgfreescoutimport::index',
			array(
				'sources'   => $sources,
				'mailboxes' => Mailbox::query()->orderBy( 'name' )->get(),
				'runs'      => $runs,
				'running'   => $runs->contains( 'status', Run::STATUS_RUNNING ),
			)
		);
	}

	/**
	 * Starts importing a source's mailbox: everything, or what changed since its last finished import into the mailbox.
	 *
	 * @param Request $request Request, with `remote_mailbox` as the source's ID and the mailbox's ID there, like `2:7`.
	 * @return RedirectResponse
	 */
	public function start( Request $request ): RedirectResponse {
		list( $source_id, $remote_mailbox_id ) = array_map( 'intval', array_pad( explode( ':', (string) $request->input( 'remote_mailbox' ), 2 ), 2, 0 ) );

		$source     = Source::find( $source_id );
		$mailbox    = Mailbox::find( (int) $request->input( 'mailbox_id' ) );
		$everything = filter_var( $request->input( 'everything' ), FILTER_VALIDATE_BOOLEAN );

		try {
			$remote = $source ? collect( RemoteApi::for_source( $source )->mailboxes() )->firstWhere( 'id', $remote_mailbox_id ) : null;
		} catch ( \Throwable $e ) {
			return self::back_with_error( $e->getMessage() );
		}

		if ( ! $remote || ! $mailbox ) {
			return self::back_with_error( __( 'Choose a mailbox to import, and one to import it into.' ) );
		}

		$run = \DB::transaction(
			static function () use ( $source_id, $remote_mailbox_id, $remote, $mailbox, $everything ): ?Run {
				if ( ! self::lock() ) {
					return null;
				}

				$previous = $everything ? null : Run::query()
					->where( 'source_id', $source_id )
					->where( 'remote_mailbox_id', $remote_mailbox_id )
					->where( 'mailbox_id', $mailbox->id )
					->where( 'status', Run::STATUS_DONE )
					->whereNull( 'retry_ids' )
					->whereNotNull( 'started_at' )
					->orderByDesc( 'started_at' )
					->first();
				$since    = $previous ? $previous->started_at->copy()->subMinutes( self::CHANGES_OVERLAP_MINUTES ) : null;

				return self::begin( $source_id, $remote_mailbox_id, (string) ( $remote['name'] ?? '' ), (int) $mailbox->id, $since );
			}
		);

		if ( ! $run ) {
			return self::back_with_error( __( self::BUSY ) );
		}

		ImportPage::dispatch( (int) $run->id, (string) $run->token );

		$message = $run->since
			? __( 'Importing what changed in :name since its last import.', array( 'name' => $remote['name'] ?? '' ) )
			: __( 'Importing :name.', array( 'name' => $remote['name'] ?? '' ) );

		return redirect()->route( 'wporgfreescoutimport.index' )->with( 'flash_success', $message );
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

		return redirect()->route( 'wporgfreescoutimport.index' );
	}

	/**
	 * Resumes a paused, failed, or stalled run where it stopped.
	 *
	 * @param int $id Run ID.
	 * @return RedirectResponse
	 */
	public function resume( int $id ): RedirectResponse {
		$run = \DB::transaction(
			static function () use ( $id ): Run|string|null {
				if ( ! self::lock( $id ) ) {
					return self::BUSY;
				}

				// Read again under the lock, in case it changed since.
				$run = Run::find( $id );
				if ( ! $run || ! ( in_array( $run->status, array( Run::STATUS_PAUSED, Run::STATUS_FAILED ), true ) || $run->is_stalled() ) ) {
					return null;
				}

				$run->status        = Run::STATUS_RUNNING;
				$run->finished_at   = null;
				$run->page_failures = 0;
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

		return redirect()->route( 'wporgfreescoutimport.index' );
	}

	/**
	 * Stops a run for good, so another can start.
	 *
	 * @param int $id Run ID.
	 * @return RedirectResponse
	 */
	public function cancel( int $id ): RedirectResponse {
		$run = Run::find( $id );
		if ( $run && ( $run->is_open() || Run::STATUS_FAILED === $run->status ) ) {
			$run->status      = Run::STATUS_CANCELLED;
			$run->finished_at = Carbon::now();
			$run->renew_token();
			$run->save();
		}

		return redirect()->route( 'wporgfreescoutimport.index' );
	}

	/**
	 * Imports a finished run's failed conversations again, in a run of their own.
	 *
	 * @param int $id Run ID.
	 * @return RedirectResponse
	 */
	public function retry( int $id ): RedirectResponse {
		$failed = Run::find( $id );
		if ( ! $failed || $failed->is_open() || ! $failed->failures || ! $failed->mailbox ) {
			return redirect()->route( 'wporgfreescoutimport.index' );
		}

		$run = \DB::transaction(
			static function () use ( $failed ): ?Run {
				if ( ! self::lock() ) {
					return null;
				}

				$run            = self::begin( (int) $failed->source_id, (int) $failed->remote_mailbox_id, (string) $failed->remote_mailbox_name, (int) $failed->mailbox_id, null, false );
				$run->retry_ids = array_map( 'intval', array_keys( (array) $failed->failures ) );
				$run->total     = count( $run->retry_ids );
				$run->save();

				return $run;
			}
		);

		if ( ! $run ) {
			return self::back_with_error( __( self::BUSY ) );
		}

		ImportPage::dispatch( (int) $run->id, (string) $run->token );

		return redirect()->route( 'wporgfreescoutimport.index' )->with( 'flash_success', __( 'Importing :count failed conversations again.', array( 'count' => $run->total ) ) );
	}

	/**
	 * Opens the conversation that had a number in a source.
	 *
	 * @param Request $request Request, with `source_id` and `number`.
	 * @return RedirectResponse
	 */
	public function find( Request $request ): RedirectResponse {
		$number          = (int) ltrim( trim( (string) $request->input( 'number' ) ), '#' );
		$conversation_id = $number > 0 ? (int) ImportedConversation::query()->where( 'source_id', (int) $request->input( 'source_id' ) )->where( 'remote_number', $number )->value( 'conversation_id' ) : 0;

		if ( ! $conversation_id ) {
			return self::back_with_error( __( 'No conversation was imported with that number.' ) );
		}

		return redirect()->route( 'conversations.view', array( 'id' => $conversation_id ) );
	}

	/**
	 * Creates a running run; its first page is queued once the transaction is committed.
	 *
	 * @param int         $source_id           Source.
	 * @param int         $remote_mailbox_id   Mailbox ID there.
	 * @param string      $remote_mailbox_name Mailbox name there.
	 * @param int         $mailbox_id          Mailbox ID here.
	 * @param Carbon|null $since               Only conversations changed since then.
	 * @param bool        $save                Whether to save it.
	 * @return Run
	 */
	private static function begin( int $source_id, int $remote_mailbox_id, string $remote_mailbox_name, int $mailbox_id, ?Carbon $since, bool $save = true ): Run {
		$run                      = new Run();
		$run->source_id           = $source_id;
		$run->remote_mailbox_id   = $remote_mailbox_id;
		$run->remote_mailbox_name = $remote_mailbox_name;
		$run->mailbox_id          = $mailbox_id;
		$run->user_id             = auth()->id();
		$run->status              = Run::STATUS_RUNNING;
		$run->since               = $since;
		$run->started_at          = Carbon::now();
		$run->renew_token();

		if ( $save ) {
			$run->save();
		}

		return $run;
	}

	/**
	 * Locks the mailboxes' rows until the transaction ends, and checks no other run is open.
	 *
	 * One run at a time: core numbers a new conversation one above the highest, which two imports at once could both
	 * take. The lock keeps two requests, like a double click, from both finding none.
	 *
	 * @param int $except Run to leave out.
	 * @return bool Whether a run can start.
	 */
	private static function lock( int $except = 0 ): bool {
		Mailbox::query()->lockForUpdate()->get( array( 'id' ) );

		return ! Run::query()
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
	public static function back_with_error( string $error ): RedirectResponse {
		return redirect()->route( 'wporgfreescoutimport.index' )->with( 'flash_error', $error );
	}
}
