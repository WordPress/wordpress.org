<?php
/**
 * The page under Manage where administrators run imports.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Http\Controllers;

use App\Conversation;
use App\Http\Controllers\Controller;
use App\Mailbox;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\WPOrgHelpScoutImport\Entities\Run;
use Modules\WPOrgHelpScoutImport\Jobs\ImportPage;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\People;

/**
 * Lists runs, starts them, and pauses, resumes, cancels, and retries them.
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
	 * Cache key for the highest conversation number HelpScout gave out.
	 *
	 * @var string
	 */
	private const HIGHEST_NUMBER_CACHE_KEY = 'wporghelpscoutimport.highest_number';

	/**
	 * Why a mailbox can't take another run.
	 *
	 * @var string
	 */
	private const BUSY = 'An import into this mailbox is still running or paused. Finish or cancel it first.';

	/**
	 * Shows HelpScout's mailboxes, the runs so far, and the forms to start more.
	 *
	 * @return View
	 */
	public function index(): View {
		$helpscout = app( HelpScout::class );
		$sources   = array();
		$error     = '';

		if ( $helpscout->is_configured() ) {
			try {
				$sources = $helpscout->mailboxes();
			} catch ( \Throwable $e ) {
				$error = $e->getMessage();
			}
		}

		$runs = Run::query()->with( 'mailbox' )->orderByDesc( 'id' )->limit( 100 )->get();

		return view(
			'wporghelpscoutimport::index',
			array(
				'configured' => $helpscout->is_configured(),
				'error'      => $error,
				'sources'    => $sources,
				'mailboxes'  => Mailbox::query()->orderBy( 'name' )->get(),
				'runs'       => $runs,
				'running'    => $runs->contains( 'status', Run::STATUS_RUNNING ),
				'numbering'  => $sources ? self::numbering( $helpscout ) : null,
			)
		);
	}

	/**
	 * Starts importing a HelpScout mailbox: everything, or what changed since its last finished import into the mailbox.
	 *
	 * HelpScout users of the mailbox without a FreeScout user get one, with access to the mailbox; that's confirmed first.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function start( Request $request ): RedirectResponse {
		$source_id  = (int) $request->input( 'helpscout_mailbox_id' );
		$mailbox    = Mailbox::find( (int) $request->input( 'mailbox_id' ) );
		$everything = filter_var( $request->input( 'everything' ), FILTER_VALIDATE_BOOLEAN );
		$helpscout  = app( HelpScout::class );
		$people     = new People( $helpscout );

		try {
			$source = collect( $helpscout->mailboxes() )->firstWhere( 'id', $source_id );
		} catch ( \Throwable $e ) {
			return self::back_with_error( $e->getMessage() );
		}

		if ( ! $source || ! $mailbox ) {
			return self::back_with_error( __( 'Choose a HelpScout mailbox and a FreeScout mailbox.' ) );
		}

		if ( self::is_busy( (int) $mailbox->id ) ) {
			return self::back_with_error( __( self::BUSY ) );
		}

		// Imported conversations take HelpScout's numbers: new ones in FreeScout mustn't take those first.
		$numbering = self::numbering( $helpscout );
		if ( $numbering && $numbering['next'] <= $numbering['highest'] ) {
			return self::back_with_error( __( 'Set Next Conversation # above :highest under Manage » Settings » General first, so FreeScout doesn’t give away HelpScout’s numbers.', array( 'highest' => number_format( $numbering['highest'] ) ) ) );
		}

		try {
			if ( ! filter_var( $request->input( 'confirmed' ), FILTER_VALIDATE_BOOLEAN ) ) {
				$pending = $people->pending( $source_id );
				if ( $pending ) {
					return redirect()
						->route( 'wporghelpscoutimport.index' )
						->with(
							'wporghelpscoutimport_confirm',
							array(
								'helpscout_mailbox_id' => $source_id,
								'mailbox_id'           => (int) $mailbox->id,
								'mailbox'              => (string) $mailbox->name,
								'name'                 => (string) ( $source['name'] ?? '' ),
								'everything'           => $everything,
								'users'                => array_map(
									static function ( array $user ): string {
										return trim( ( $user['firstName'] ?? '' ) . ' ' . ( $user['lastName'] ?? '' ) . ' <' . ( $user['email'] ?? '' ) . '>' );
									},
									$pending
								),
							)
						);
				}
			}
		} catch ( \Throwable $e ) {
			return self::back_with_error( $e->getMessage() );
		}

		$created = array();

		try {
			// HelpScout's users are read before the lock, rather than while holding it.
			$people->directory();

			$run = \DB::transaction(
				static function () use ( $source_id, $source, $mailbox, $everything, $people, &$created ): ?Run {
					// Users are created under the lock too, so a double click doesn't create them twice.
					if ( ! self::lock( (int) $mailbox->id ) ) {
						return null;
					}

					$created = $people->prepare( $source_id, $mailbox );

					$previous = $everything ? null : Run::query()
						->where( 'helpscout_mailbox_id', $source_id )
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
		} catch ( \Throwable $e ) {
			return self::back_with_error( $e->getMessage() );
		}

		if ( ! $run ) {
			return self::back_with_error( __( self::BUSY ) );
		}

		ImportPage::dispatch( (int) $run->id, (string) $run->token );

		$message = $run->since
			? __( 'Importing what changed in :name since its last import.', array( 'name' => $source['name'] ?? '' ) )
			: __( 'Importing :name.', array( 'name' => $source['name'] ?? '' ) );
		if ( $created ) {
			$message .= ' ' . __( 'Created :count FreeScout users for its HelpScout users.', array( 'count' => count( $created ) ) );
		}

		return redirect()->route( 'wporghelpscoutimport.index' )->with( 'flash_success', $message );
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
	 * Resumes a paused, failed, or stalled run where it stopped.
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

		return redirect()->route( 'wporghelpscoutimport.index' );
	}

	/**
	 * Stops a run for good, so its mailbox can take another.
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

		return redirect()->route( 'wporghelpscoutimport.index' );
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
			return redirect()->route( 'wporghelpscoutimport.index' );
		}

		$run = \DB::transaction(
			static function () use ( $failed ): ?Run {
				if ( ! self::lock( (int) $failed->mailbox_id ) ) {
					return null;
				}

				$run            = self::begin( (int) $failed->helpscout_mailbox_id, (string) $failed->helpscout_mailbox_name, (int) $failed->mailbox_id, null, false );
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

		return redirect()->route( 'wporghelpscoutimport.index' )->with( 'flash_success', __( 'Importing :count failed conversations again.', array( 'count' => $run->total ) ) );
	}

	/**
	 * Creates a running run; its first page is queued once the transaction is committed.
	 *
	 * @param int         $source_id   HelpScout mailbox ID.
	 * @param string      $source_name HelpScout mailbox name.
	 * @param int         $mailbox_id  FreeScout mailbox ID.
	 * @param Carbon|null $since       Only conversations changed since then.
	 * @param bool        $save        Whether to save it.
	 * @return Run
	 */
	private static function begin( int $source_id, string $source_name, int $mailbox_id, ?Carbon $since, bool $save = true ): Run {
		$run                         = new Run();
		$run->helpscout_mailbox_id   = $source_id;
		$run->helpscout_mailbox_name = $source_name;
		$run->mailbox_id             = $mailbox_id;
		$run->user_id                = auth()->id();
		$run->status                 = Run::STATUS_RUNNING;
		$run->since                  = $since;
		$run->started_at             = Carbon::now();
		$run->renew_token();

		if ( $save ) {
			$run->save();
		}

		return $run;
	}

	/**
	 * Whether conversation numbers are set up for HelpScout's, and what's wrong if they aren't.
	 *
	 * New FreeScout conversations take the next number after the highest; HelpScout goes on numbering the mailboxes
	 * that haven't moved yet, so FreeScout's next number has to be above HelpScout's highest.
	 *
	 * @param HelpScout $helpscout HelpScout API client.
	 * @return array|null `custom` (whether FreeScout shows numbers rather than IDs), `next` (FreeScout's next number),
	 *                    and `highest` (HelpScout's highest); null if HelpScout's highest couldn't be read.
	 */
	private static function numbering( HelpScout $helpscout ): ?array {
		try {
			$highest = (int) \Cache::remember(
				self::HIGHEST_NUMBER_CACHE_KEY,
				60,
				static function () use ( $helpscout ): int {
					return $helpscout->highest_number();
				}
			);
		} catch ( \Throwable $e ) {
			return null;
		}

		return array(
			'custom'  => (bool) config( 'app.custom_number' ),
			'next'    => max( (int) \Option::get( 'next_ticket', 0, true, false ), (int) Conversation::query()->max( 'number' ) + 1 ),
			'highest' => $highest,
		);
	}

	/**
	 * Whether a FreeScout mailbox has a run open already.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @return bool
	 */
	private static function is_busy( int $mailbox_id ): bool {
		return Run::query()
			->where( 'mailbox_id', $mailbox_id )
			->whereIn( 'status', array( Run::STATUS_RUNNING, Run::STATUS_PAUSED ) )
			->exists();
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
