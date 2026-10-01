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
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
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
	 * Shows HelpScout's mailboxes, the runs so far, and the forms to start more.
	 *
	 * @param Request $request Request; `agents` names a HelpScout mailbox whose users to check.
	 * @return View
	 */
	public function index( Request $request ): View {
		$helpscout = app( HelpScout::class );
		$sources   = array();
		$error     = '';
		$missing   = null;

		if ( $helpscout->is_configured() ) {
			try {
				$sources = $helpscout->mailboxes();

				$agents = (int) $request->query( 'agents' );
				if ( $agents ) {
					$missing = ( new People() )->missing_users( $helpscout->users( $agents ) );
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
				'missing'    => $missing,
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

		$error = self::busy( (int) $mailbox->id );
		if ( $error ) {
			return self::back_with_error( $error );
		}

		$previous = Run::query()
			->where( 'helpscout_mailbox_id', $source_id )
			->where( 'mailbox_id', $mailbox->id )
			->where( 'status', Run::STATUS_DONE )
			->whereNotNull( 'started_at' )
			->orderByDesc( 'started_at' )
			->first();
		$since    = $previous ? $previous->started_at->copy()->subMinutes( self::CHANGES_OVERLAP_MINUTES ) : null;

		self::begin( $source_id, (string) ( $source['name'] ?? '' ), (int) $mailbox->id, $since );

		$message = $since
			? __( 'Importing what changed in :name since its last import.', array( 'name' => $source['name'] ?? '' ) )
			: __( 'Importing :name.', array( 'name' => $source['name'] ?? '' ) );

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
	 * Resumes a paused or failed run where it stopped.
	 *
	 * @param int $id Run ID.
	 * @return RedirectResponse
	 */
	public function resume( int $id ): RedirectResponse {
		$run = Run::find( $id );
		if ( ! $run || ! in_array( $run->status, array( Run::STATUS_PAUSED, Run::STATUS_FAILED ), true ) ) {
			return redirect()->route( 'wporghelpscoutimport.index' );
		}

		$error = self::busy( (int) $run->mailbox_id, (int) $run->id );
		if ( $error ) {
			return self::back_with_error( $error );
		}

		$run->status      = Run::STATUS_RUNNING;
		$run->finished_at = null;
		$token            = $run->renew_token();
		$run->save();

		ImportPage::dispatch( (int) $run->id, $token );

		return redirect()->route( 'wporghelpscoutimport.index' );
	}

	/**
	 * Creates a run and queues its first page.
	 *
	 * @param int         $source_id   HelpScout mailbox ID.
	 * @param string      $source_name HelpScout mailbox name.
	 * @param int         $mailbox_id  FreeScout mailbox ID.
	 * @param Carbon|null $since       Only conversations changed since then.
	 * @return void
	 */
	private static function begin( int $source_id, string $source_name, int $mailbox_id, ?Carbon $since ): void {
		$run                         = new Run();
		$run->helpscout_mailbox_id   = $source_id;
		$run->helpscout_mailbox_name = $source_name;
		$run->mailbox_id             = $mailbox_id;
		$run->user_id                = auth()->id();
		$run->status                 = Run::STATUS_RUNNING;
		$run->since                  = $since;
		$run->started_at             = Carbon::now();
		$token                       = $run->renew_token();
		$run->save();

		ImportPage::dispatch( (int) $run->id, $token );
	}

	/**
	 * Why a FreeScout mailbox can't take another run now, if it can't.
	 *
	 * One run per mailbox at a time, so two can't import the same conversation at once.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 * @param int $except     Run to leave out.
	 * @return string Empty if it can.
	 */
	private static function busy( int $mailbox_id, int $except = 0 ): string {
		$open = Run::query()
			->where( 'mailbox_id', $mailbox_id )
			->whereIn( 'status', array( Run::STATUS_RUNNING, Run::STATUS_PAUSED ) )
			->where( 'id', '!=', $except )
			->exists();

		return $open ? __( 'An import into this mailbox is still running or paused. Finish it first.' ) : '';
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
