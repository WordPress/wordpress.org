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
use Modules\WPOrgFreeScoutImport\Entities\Run;
use Modules\WPOrgFreeScoutImport\Jobs\ApplyMessageIds;
use Modules\WPOrgFreeScoutImport\Jobs\ImportPage;
use Modules\WPOrgFreeScoutImport\Services\MessageIds;
use Modules\WPOrgFreeScoutImport\Services\People;
use Modules\WPOrgFreeScoutImport\Services\Settings;
use Modules\WPOrgFreeScoutImport\Services\Source;

/**
 * Lists runs, starts them, and pauses, resumes, cancels, and retries them; takes the Message-ID file; and finds
 * conversations by the number they had in the other FreeScout.
 *
 * Starting imports everything the first time, and what the other FreeScout changed since the last finished import
 * after that.
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
	 * Shows the other FreeScout's mailboxes, the runs so far, and the forms to start more.
	 *
	 * @return View
	 */
	public function index(): View {
		$source  = app( Source::class );
		$sources = array();
		$error   = '';

		if ( $source->is_configured() ) {
			try {
				$sources = $source->mailboxes();
			} catch ( \Throwable $e ) {
				$error = $e->getMessage();
			}
		}

		$runs = Run::query()->with( 'mailbox' )->orderByDesc( 'id' )->limit( 100 )->get();

		return view(
			'wporgfreescoutimport::index',
			array(
				'configured'  => $source->is_configured(),
				'name'        => $source->name(),
				'url'         => Settings::url(),
				'has_key'     => Settings::has_api_key(),
				'error'       => $error,
				'sources'     => $sources,
				'mailboxes'   => Mailbox::query()->orderBy( 'name' )->get(),
				'runs'        => $runs,
				'running'     => $runs->contains( 'status', Run::STATUS_RUNNING ),
				'message_ids' => MessageId::query()->count(),
			)
		);
	}

	/**
	 * Starts importing a mailbox: everything, or what changed since its last finished import into the mailbox.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function start( Request $request ): RedirectResponse {
		$source_id  = (int) $request->input( 'source_mailbox_id' );
		$mailbox    = Mailbox::find( (int) $request->input( 'mailbox_id' ) );
		$everything = filter_var( $request->input( 'everything' ), FILTER_VALIDATE_BOOLEAN );

		try {
			$source = collect( app( Source::class )->mailboxes() )->firstWhere( 'id', $source_id );
		} catch ( \Throwable $e ) {
			return self::back_with_error( $e->getMessage() );
		}

		if ( ! $source || ! $mailbox ) {
			return self::back_with_error( __( 'Choose a mailbox to import, and one to import it into.' ) );
		}

		$run = \DB::transaction(
			static function () use ( $source_id, $source, $mailbox, $everything ): ?Run {
				if ( ! self::lock() ) {
					return null;
				}

				$previous = $everything ? null : Run::query()
					->where( 'source_mailbox_id', $source_id )
					->where( 'mailbox_id', $mailbox->id )
					->where( 'status', Run::STATUS_DONE )
					->whereNull( 'retry_ids' )
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
			static function () use ( $id ) {
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

				$run            = self::begin( (int) $failed->source_mailbox_id, (string) $failed->source_mailbox_name, (int) $failed->mailbox_id, null, false );
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
	 * Saves the other FreeScout's address, and its API key if one was entered.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function settings( Request $request ): RedirectResponse {
		$changed = Settings::url() !== rtrim( trim( (string) $request->input( 'url' ) ), '/' );

		if ( ! Settings::save( (string) $request->input( 'url' ), (string) $request->input( 'api_key' ) ) ) {
			return self::back_with_error( __( 'Enter the other FreeScout’s address, like https://scout.wc-us.org.' ) );
		}

		// Another FreeScout has other users.
		if ( $changed ) {
			People::forget_directory();
		}

		return redirect()->route( 'wporgfreescoutimport.index' )->with( 'flash_success', __( 'Saved.' ) );
	}

	/**
	 * Forgets the API key, like after the last import.
	 *
	 * @return RedirectResponse
	 */
	public function forget(): RedirectResponse {
		Settings::forget_api_key();

		return redirect()->route( 'wporgfreescoutimport.index' )->with( 'flash_success', __( 'Forgot the API key.' ) );
	}

	/**
	 * Keeps the Message-IDs of the file bin/export-message-ids.php wrote, and gives them to threads imported already.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function message_ids( Request $request ): RedirectResponse {
		$file = $request->file( 'message_ids' );
		if ( ! $file || ! $file->isValid() ) {
			return self::back_with_error( __( 'Choose the file of Message-IDs.' ) );
		}

		try {
			$count = MessageIds::store( (string) $file->getRealPath() );
		} catch ( \Throwable $e ) {
			return self::back_with_error( $e->getMessage() );
		}

		if ( ! $count ) {
			return self::back_with_error( __( 'The file has no Message-IDs: each line needs a thread ID and a Message-ID, separated by a comma.' ) );
		}

		ApplyMessageIds::dispatch();

		return redirect()->route( 'wporgfreescoutimport.index' )->with( 'flash_success', __( 'Kept :count Message-IDs. Threads imported already get theirs on the queue.', array( 'count' => number_format( $count ) ) ) );
	}

	/**
	 * Opens the conversation that had a number in the other FreeScout.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function find( Request $request ): RedirectResponse {
		$number          = (int) ltrim( trim( (string) $request->input( 'number' ) ), '#' );
		$conversation_id = $number > 0 ? (int) ImportedConversation::query()->where( 'source_number', $number )->value( 'conversation_id' ) : 0;

		if ( ! $conversation_id ) {
			return self::back_with_error( __( 'No conversation was imported with that number.' ) );
		}

		return redirect()->route( 'conversations.view', array( 'id' => $conversation_id ) );
	}

	/**
	 * Creates a running run; its first page is queued once the transaction is committed.
	 *
	 * @param int         $source_id   Source mailbox ID.
	 * @param string      $source_name Source mailbox name.
	 * @param int         $mailbox_id  Mailbox ID.
	 * @param Carbon|null $since       Only conversations changed since then.
	 * @param bool        $save        Whether to save it.
	 * @return Run
	 */
	private static function begin( int $source_id, string $source_name, int $mailbox_id, ?Carbon $since, bool $save = true ): Run {
		$run                      = new Run();
		$run->source_mailbox_id   = $source_id;
		$run->source_mailbox_name = $source_name;
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
	private static function back_with_error( string $error ): RedirectResponse {
		return redirect()->route( 'wporgfreescoutimport.index' )->with( 'flash_error', $error );
	}
}
