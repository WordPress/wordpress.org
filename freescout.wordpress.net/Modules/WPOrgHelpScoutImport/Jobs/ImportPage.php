<?php
/**
 * Queued import of one page of a HelpScout mailbox's conversations.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\WPOrgHelpScoutImport\Entities\Run;
use Modules\WPOrgHelpScoutImport\Exceptions\ApiError;
use Modules\WPOrgHelpScoutImport\Exceptions\RateLimited;
use Modules\WPOrgHelpScoutImport\Services\Copies;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\Importer;
use Modules\WPOrgHelpScoutImport\Services\People;
use Modules\WPOrgHelpScoutImport\Services\SavedReplies;

/**
 * Imports a page of 25 conversations, then queues the next page; or 25 of the conversations a run retries.
 *
 * Small jobs keep the queue moving: FreeScout's single worker takes outgoing email before them.
 */
final class ImportPage implements ShouldQueue {
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;

	/**
	 * Seconds before a page HelpScout failed is tried again.
	 *
	 * @var int
	 */
	private const RETRY_DELAY = 300;

	/**
	 * Conversations a job retries, like a page of HelpScout's list.
	 *
	 * @var int
	 */
	private const RETRY_BATCH = 25;

	/**
	 * How often a conversation's import can start before it's counted as failed.
	 *
	 * @var int
	 */
	private const MAX_ATTEMPTS = 5;

	/**
	 * Failures in a row before a run stops: an hour of tries.
	 *
	 * @var int
	 */
	private const MAX_PAGE_FAILURES = 12;

	/**
	 * Tries in which a conversation the rate limit cut off gives way to outgoing email, before it waits regardless.
	 *
	 * @var int
	 */
	private const GIVE_WAY_TRIES = 3;

	/**
	 * Run ID.
	 *
	 * @var int
	 */
	public $run_id;

	/**
	 * The run's token when this job was queued; a different one means the run was paused or resumed since.
	 *
	 * @var string
	 */
	public $token;

	/**
	 * Constructor.
	 *
	 * @param int    $run_id Run ID.
	 * @param string $token  The run's token.
	 */
	public function __construct( int $run_id, string $token ) {
		$this->run_id = $run_id;
		$this->token  = $token;
	}

	/**
	 * Imports the run's current page, and what moved onto the previous one since it was read; or a retry's conversations.
	 *
	 * HelpScout's list moves when a conversation on an earlier page is deleted, or changed during an import of changes:
	 * later ones move forward, onto pages already read. So the previous page is read again too, and what's new on it
	 * is imported first. The run is done when a page comes back empty.
	 *
	 * Progress is saved after every conversation, so a page that's stopped part way goes on where it stopped.
	 *
	 * A run that retries another's failures has no pages: it gets its conversations one by one, 25 per job.
	 *
	 * @return void
	 */
	public function handle(): void {
		// Whether modules, like Teams, are on can have changed since the worker started.
		\App\Module::clearModulesCache();

		$run = $this->current_run();
		if ( ! $run ) {
			return;
		}

		$mailbox = $run->mailbox;
		if ( ! $mailbox ) {
			$this->stop( $run, 'The FreeScout mailbox was deleted.' );

			return;
		}

		$current = 0;

		try {
			$helpscout = app( HelpScout::class );
			$people    = new People( $helpscout );
			$importer  = new Importer( $helpscout, $people );

			if ( $run->is_retry() ) {
				$listed = array_map(
					static function ( int $id ): array {
						return array(
							'id'    => $id,
							'retry' => true,
						);
					},
					self::ids( (array) $run->retry_ids )
				);
				$page   = array( 'conversations' => array() );
			} else {
				$page   = $this->page( $helpscout, $run );
				$listed = $page['conversations'];

				if ( $run->page > 1 ) {
					$previous = $helpscout->conversations( (int) $run->helpscout_mailbox_id, (int) $run->page - 1, $run->since );
					$known    = self::ids( (array) $run->previous_page );
					$moved    = array_filter(
						$previous['conversations'],
						static function ( $conversation ) use ( $known ): bool {
							return ! in_array( (int) ( $conversation['id'] ?? 0 ), $known, true );
						}
					);
					$listed   = array_merge( array_values( $moved ), $listed );
				}

				$run->pages = $page['pages'] ? $page['pages'] : $run->pages;
				$run->total = $page['total'] ? $page['total'] : $run->total;
			}

			$done     = self::ids( (array) $run->page_done );
			$imported = 0;

			foreach ( $listed as $conversation ) {
				$current = (int) ( $conversation['id'] ?? 0 );
				if ( in_array( $current, $done, true ) ) {
					continue;
				}

				// A retry goes on in another job after as many as a page has, to keep jobs short.
				if ( $run->is_retry() && $imported >= self::RETRY_BATCH ) {
					$current = 0;
					break;
				}
				++$imported;

				// Kept before it's imported: a job that dies on it, like out of memory, leaves it behind for the next.
				$run->attempts   = $current === (int) $run->attempting ? $run->attempts + 1 : 1;
				$run->attempting = $current;
				if ( ! $this->save( $run ) ) {
					return;
				}

				if ( $run->attempts > self::MAX_ATTEMPTS ) {
					$run->add_failure( $current, 'Its import stopped ' . self::MAX_ATTEMPTS . ' times without finishing, like by running out of memory or time, or HelpScout failing for it.' );
				} else {
					// The rate limit cut this one off before: it needs more than a minute's share, so this time it waits.
					// It gives way to outgoing email in the queue, but not forever: after a few tries, it waits regardless.
					$attempts = (int) $run->attempts;
					$helpscout->set_patient(
						$current === (int) $run->waiting_on,
						static function () use ( $attempts ): bool {
							return $attempts > self::GIVE_WAY_TRIES || self::queue_is_free();
						}
					);

					$this->import( $importer, $helpscout, (array) $conversation, $mailbox, $run );
				}

				$done[]          = $current;
				$run->page_done  = $done;
				$run->waiting_on = null;
				$run->attempting = null;
				$run->attempts   = 0;

				if ( ! $this->save( $run ) ) {
					return;
				}
			}

			// Once the list is done: saved replies are few, and changes to them are only found by reading them all.
			if ( ! $run->is_retry() && ! $page['conversations'] && SavedReplies::available() ) {
				$current = 0;
				$helpscout->set_patient( false );
				( new SavedReplies( $helpscout, $importer, $people ) )->import( $run, $mailbox );
			}

			$run->page_failures = 0;
		} catch ( RateLimited $e ) {
			// Waiting for the rate limit isn't a failed try; giving way to email while patient is, so it ends.
			$was_patient     = $current && $current === (int) $run->waiting_on;
			$run->waiting_on = $current ? $current : null;
			$run->attempts   = $current && ! $was_patient ? max( 0, $run->attempts - 1 ) : $run->attempts;
			$this->save( $run );
			$this->again( $e->retry_after );

			return;
		} catch ( ApiError $e ) {
			// Refused, or an answer to the list that waiting won't change.
			if ( $e->is_denied() || ( $e->status >= 400 && $e->status < 500 ) ) {
				$this->stop( $run, Run::describe( $e ) );
			} else {
				$this->fail_page( $run, Run::describe( $e ) );
			}

			return;
		} catch ( \Throwable $e ) {
			// FreeScout's worker doesn't retry jobs: without this, the run would wait for a job that's gone.
			\Log::error( '[WPOrgHelpScoutImport] Could not import a page of ' . $run->helpscout_mailbox_name . ': ' . Run::describe( $e ) );
			$this->fail_page( $run, Run::describe( $e ) );

			return;
		} finally {
			// Not thrown from here: the next job is queued already, and a failure would queue another, from failed().
			try {
				Importer::update_counters( $mailbox );
			} catch ( \Throwable $e ) {
				\Log::error( '[WPOrgHelpScoutImport] Could not update the folder counters of ' . $mailbox->name . ': ' . Run::describe( $e ) );
			}
		}

		if ( $run->is_retry() && array_diff( self::ids( (array) $run->retry_ids ), self::ids( (array) $run->page_done ) ) ) {
			$this->again( 0 );

			return;
		}

		// A retry is done once its conversations are; a mailbox's list once a page comes back empty.
		if ( $run->is_retry() || ! $page['conversations'] ) {
			$run->status      = Run::STATUS_DONE;
			$run->finished_at = Carbon::now();
			if ( $this->save( $run ) ) {
				// Once WordPress.org points at the mailbox, it points at what this imported too.
				Copies::catch_up( (int) $run->mailbox_id, (string) $run->started_at );
			}

			return;
		}

		$run->previous_page = self::ids( $page['conversations'] );
		$run->page_done     = null;
		++$run->page;

		if ( $this->save( $run ) ) {
			$this->again( 0 );
		}
	}

	/**
	 * Imports one conversation; a conversation that fails is counted and logged, and the page goes on.
	 *
	 * @param Importer     $importer     Importer.
	 * @param HelpScout    $helpscout    HelpScout API client.
	 * @param array        $conversation HelpScout conversation, or for a retry, `id` and `retry`.
	 * @param \App\Mailbox $mailbox      FreeScout mailbox.
	 * @param Run          $run          Run, whose counters are added to.
	 * @return void
	 *
	 * @throws ApiError If HelpScout is unavailable or refuses the app, which stops the page.
	 */
	private function import( Importer $importer, HelpScout $helpscout, array $conversation, \App\Mailbox $mailbox, Run $run ): void {
		$id = (int) ( $conversation['id'] ?? 0 );

		try {
			if ( ! empty( $conversation['retry'] ) ) {
				$conversation = $helpscout->conversation( $id );
			}

			if ( ! $conversation ) {
				$result = Importer::SKIPPED_GONE;
			} elseif ( isset( $conversation['mailboxId'] ) && (int) $conversation['mailboxId'] !== (int) $run->helpscout_mailbox_id ) {
				// Moved to another HelpScout mailbox since it failed: it's imported with that one.
				$result = Importer::SKIPPED_MOVED;
			} else {
				$result = $importer->import( $conversation, $mailbox );
			}
		} catch ( ApiError $e ) {
			// The rate limit, rejected credentials, and outages hold for every conversation; a 403 may be this one's.
			if ( $e instanceof RateLimited || 401 === $e->status || 0 === $e->status || $e->status >= 500 ) {
				throw $e;
			}

			$this->fail( $run, $id, $e );

			return;
		} catch ( \Throwable $e ) {
			$this->fail( $run, $id, $e );

			return;
		}

		if ( Importer::is_skipped( $result ) ) {
			$run->add_skip( $result );
		} else {
			++$run->{$result};
		}
	}

	/**
	 * Goes on after the job itself failed, like when the worker ran out of memory or time on a conversation.
	 *
	 * FreeScout's worker tries a job only once; without this, the run would wait for a Resume. The conversation it
	 * failed on is tried again, and counted as failed after a few tries.
	 *
	 * @param \Throwable|null $exception Why it failed.
	 * @return void
	 */
	public function failed( $exception = null ): void {
		$run = $this->current_run();
		if ( ! $run ) {
			return;
		}

		// Counted like a page that failed, so a job that keeps dying outside a conversation stops the run.
		$this->fail_page( $run, $exception ? Run::describe( $exception ) : 'The import job stopped.' );
	}

	/**
	 * The run's current page of HelpScout's list.
	 *
	 * Past the last page HelpScout gave so far, an answer it won't give counts as an empty page: the end of the list.
	 *
	 * @param HelpScout $helpscout HelpScout API client.
	 * @param Run       $run       Run.
	 * @return array See HelpScout::conversations().
	 *
	 * @throws ApiError If HelpScout didn't give the page.
	 */
	private function page( HelpScout $helpscout, Run $run ): array {
		try {
			return $helpscout->conversations( (int) $run->helpscout_mailbox_id, (int) $run->page, $run->since );
		} catch ( ApiError $e ) {
			if ( $run->pages && $run->page > $run->pages && $e->status >= 400 && $e->status < 500 && ! $e instanceof RateLimited && ! $e->is_denied() ) {
				return array(
					'conversations' => array(),
					'pages'         => 0,
					'total'         => 0,
				);
			}

			throw $e;
		}
	}

	/**
	 * Whether FreeScout's queue has nothing waiting that a patient request would hold up, like outgoing email.
	 *
	 * @return bool
	 */
	public static function queue_is_free(): bool {
		try {
			if ( 'database' !== config( 'queue.default' ) ) {
				return true;
			}

			return ! \DB::table( (string) config( 'queue.connections.database.table', 'jobs' ) )
				->where( 'queue', 'emails' )
				->where( 'available_at', '<=', time() )
				->exists();
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Tries the page again later, or stops the run once HelpScout failed too many pages in a row.
	 *
	 * @param Run    $run   Run.
	 * @param string $error What went wrong.
	 * @return void
	 */
	private function fail_page( Run $run, string $error ): void {
		++$run->page_failures;
		if ( $run->page_failures > self::MAX_PAGE_FAILURES ) {
			$this->stop( $run, 'Stopped after ' . self::MAX_PAGE_FAILURES . ' failures in a row: ' . $error );

			return;
		}

		$run->last_error = $error;
		$this->save( $run );
		$this->again( self::RETRY_DELAY );
	}

	/**
	 * Counts and logs a conversation that failed.
	 *
	 * @param Run        $run Run.
	 * @param int        $id  HelpScout conversation ID.
	 * @param \Throwable $e   What went wrong.
	 * @return void
	 */
	private function fail( Run $run, int $id, \Throwable $e ): void {
		$run->add_failure( $id, Run::describe( $e ) );
		\Log::error( '[WPOrgHelpScoutImport] Could not import ' . $run->last_error );
	}

	/**
	 * HelpScout IDs of a list of conversations, or of a list of IDs.
	 *
	 * @param array $items Conversations or IDs.
	 * @return int[]
	 */
	private static function ids( array $items ): array {
		return array_map(
			static function ( $item ): int {
				return (int) ( is_array( $item ) ? ( $item['id'] ?? 0 ) : $item );
			},
			array_values( $items )
		);
	}

	/**
	 * The run, if this job is still its current one.
	 *
	 * @return Run|null
	 */
	private function current_run(): ?Run {
		$run = Run::find( $this->run_id );

		return $run && Run::STATUS_RUNNING === $run->status && $this->token === $run->token ? $run : null;
	}

	/**
	 * Saves the run's progress, unless it was paused or resumed meanwhile.
	 *
	 * @param Run $run Run.
	 * @return bool Whether this job is still the run's current one.
	 */
	private function save( Run $run ): bool {
		$changed = Run::query()
			->whereKey( $run->id )
			->where( 'token', $this->token )
			->where( 'status', Run::STATUS_RUNNING )
			->update( $run->getDirty() + array( 'updated_at' => Carbon::now() ) );

		$run->syncOriginal();

		return $changed > 0;
	}

	/**
	 * Stops the run for good.
	 *
	 * @param Run    $run    Run.
	 * @param string $reason Why.
	 * @return void
	 */
	private function stop( Run $run, string $reason ): void {
		$run->status      = Run::STATUS_FAILED;
		$run->last_error  = $reason;
		$run->finished_at = Carbon::now();
		$this->save( $run );

		\Log::error( '[WPOrgHelpScoutImport] Stopped importing ' . $run->helpscout_mailbox_name . ': ' . $reason );
	}

	/**
	 * Queues this page, or the next, again.
	 *
	 * @param int $delay Seconds to wait.
	 * @return void
	 */
	private function again( int $delay ): void {
		$job = new self( $this->run_id, $this->token );

		dispatch( $delay > 0 ? $job->delay( Carbon::now()->addSeconds( $delay ) ) : $job );
	}
}
