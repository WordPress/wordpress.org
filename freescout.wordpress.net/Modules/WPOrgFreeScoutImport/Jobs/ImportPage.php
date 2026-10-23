<?php
/**
 * Queued import of one page of a source mailbox's conversations.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\WPOrgFreeScoutImport\Entities\Run;
use Modules\WPOrgFreeScoutImport\Exceptions\ApiError;
use Modules\WPOrgFreeScoutImport\Services\Importer;
use Modules\WPOrgFreeScoutImport\Services\People;
use Modules\WPOrgFreeScoutImport\Services\RemoteApi;

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
	 * Seconds before a page the source failed is tried again.
	 *
	 * @var int
	 */
	private const RETRY_DELAY = 300;

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
	 * The list is in the order conversations were numbered, so new ones are added at its end. It only moves when a
	 * conversation on an earlier page is deleted for good: later ones move forward, onto pages already read. So the
	 * previous page is read again too, and what's new on it is imported first. The run is done when a page comes back
	 * empty.
	 *
	 * Progress is saved after every conversation, so a page that's stopped part way goes on where it stopped.
	 *
	 * @return void
	 */
	public function handle(): void {
		// Whether modules, like Tags, are on can have changed since the worker started.
		\App\Module::clearModulesCache();

		$run = $this->current_run();
		if ( ! $run ) {
			return;
		}

		$mailbox = $run->mailbox;
		if ( ! $mailbox ) {
			$this->stop( $run, 'The mailbox was deleted.' );

			return;
		}

		if ( ! $run->source ) {
			$this->stop( $run, 'The source was deleted.' );

			return;
		}

		try {
			$source   = RemoteApi::for_source( $run->source );
			$importer = new Importer( $source, new People( (int) $run->source_id ) );

			if ( $run->is_retry() ) {
				$listed = array_map(
					static function ( int $id ): array {
						return array(
							'id'    => $id,
							'retry' => true,
						);
					},
					array_slice( array_values( array_diff( self::ids( (array) $run->retry_ids ), self::ids( (array) $run->page_done ) ) ), 0, RemoteApi::PAGE_SIZE )
				);
				$page   = array( 'conversations' => array() );
			} else {
				$page   = $this->page( $source, $run );
				$listed = $page['conversations'];

				if ( $run->page > 1 ) {
					$previous = $source->conversations( (int) $run->remote_mailbox_id, (int) $run->page - 1, $run->since );
					$known    = self::ids( (array) $run->previous_page );
					$moved    = array_filter(
						$previous['conversations'],
						static function ( mixed $conversation ) use ( $known ): bool {
							return ! in_array( (int) ( $conversation['id'] ?? 0 ), $known, true );
						}
					);
					$listed   = array_merge( array_values( $moved ), $listed );
				}

				$run->pages = $page['pages'] ? $page['pages'] : $run->pages;
				$run->total = $page['total'] ? $page['total'] : $run->total;
			}

			$done = self::ids( (array) $run->page_done );

			foreach ( $listed as $conversation ) {
				$current = (int) ( $conversation['id'] ?? 0 );
				if ( in_array( $current, $done, true ) ) {
					continue;
				}

				// Kept before it's imported: a job that dies on it, like out of memory, leaves it behind for the next.
				$run->attempts   = $current === (int) $run->attempting ? $run->attempts + 1 : 1;
				$run->attempting = $current;
				if ( ! $this->save( $run ) ) {
					return;
				}

				if ( $run->attempts > self::MAX_ATTEMPTS ) {
					$run->add_failure( $current, 'Its import stopped ' . self::MAX_ATTEMPTS . ' times without finishing, like by running out of memory or time.' );
				} else {
					$this->import( $importer, $source, (array) $conversation, $mailbox, $run );
				}

				$done[]          = $current;
				$run->page_done  = $done;
				$run->attempting = null;
				$run->attempts   = 0;

				if ( ! $this->save( $run ) ) {
					return;
				}
			}

			$run->page_failures = 0;
		} catch ( ApiError $e ) {
			// A refused key, or an answer to the list that waiting won't change.
			if ( $e->is_denied() || ( $e->status >= 400 && $e->status < 500 ) ) {
				$this->stop( $run, Run::describe( $e ) );
			} else {
				$this->fail_page( $run, Run::describe( $e ) );
			}

			return;
		} catch ( \Throwable $e ) {
			// FreeScout's worker doesn't retry jobs: without this, the run would wait for a job that's gone.
			\Log::error( '[WPOrgFreeScoutImport] Could not import a page of ' . $run->remote_mailbox_name . ': ' . Run::describe( $e ) );
			$this->fail_page( $run, Run::describe( $e ) );

			return;
		} finally {
			// Not thrown from here: the next job is queued already, and a failure would queue another, from failed().
			try {
				Importer::update_counters( $mailbox );
			} catch ( \Throwable $e ) {
				\Log::error( '[WPOrgFreeScoutImport] Could not update the folder counters of ' . $mailbox->name . ': ' . Run::describe( $e ) );
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
			$this->save( $run );

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
	 * @param RemoteApi    $source       The source's API.
	 * @param array        $conversation Remote conversation, or for a retry, `id` and `retry`.
	 * @param \App\Mailbox $mailbox      Mailbox.
	 * @param Run          $run          Run, whose counters are added to.
	 * @return void
	 *
	 * @throws ApiError If the source is unavailable or refuses the key, which stops the page.
	 */
	private function import( Importer $importer, RemoteApi $source, array $conversation, \App\Mailbox $mailbox, Run $run ): void {
		$id = (int) ( $conversation['id'] ?? 0 );

		try {
			if ( ! empty( $conversation['retry'] ) ) {
				$conversation = $source->conversation( $id );
			}

			if ( ! $conversation ) {
				$result = Importer::SKIPPED_GONE;
			} elseif ( isset( $conversation['mailboxId'] ) && (int) $conversation['mailboxId'] !== (int) $run->remote_mailbox_id ) {
				// Moved to another mailbox there since it failed: it's imported with that one.
				$result = Importer::SKIPPED_MOVED;
			} else {
				$result = $importer->import( $conversation, $mailbox );
			}
		} catch ( ApiError $e ) {
			// A refused key and outages hold for every conversation.
			if ( $e->is_denied() || 0 === $e->status || $e->status >= 500 ) {
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
	public function failed( ?\Throwable $exception = null ): void {
		$run = $this->current_run();
		if ( ! $run ) {
			return;
		}

		$this->fail_page( $run, $exception ? Run::describe( $exception ) : 'The import job stopped.' );
	}

	/**
	 * The run's current page of the list.
	 *
	 * Past the last page the list had so far, an answer the API won't give counts as an empty page: the end of the list.
	 *
	 * @param RemoteApi $source The source's API.
	 * @param Run       $run    Run.
	 * @return array See RemoteApi::conversations().
	 *
	 * @throws ApiError If the page couldn't be read.
	 */
	private function page( RemoteApi $source, Run $run ): array {
		try {
			return $source->conversations( (int) $run->remote_mailbox_id, (int) $run->page, $run->since );
		} catch ( ApiError $e ) {
			if ( $run->pages && $run->page > $run->pages && $e->status >= 400 && $e->status < 500 && ! $e->is_denied() ) {
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
	 * Tries the page again later, or stops the run once too many pages failed in a row.
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
	 * @param int        $id  Remote conversation ID.
	 * @param \Throwable $e   What went wrong.
	 * @return void
	 */
	private function fail( Run $run, int $id, \Throwable $e ): void {
		$run->add_failure( $id, Run::describe( $e ) );
		\Log::error( '[WPOrgFreeScoutImport] Could not import ' . $run->last_error );
	}

	/**
	 * Remote IDs of a list of conversations, or of a list of IDs.
	 *
	 * @param array $items Conversations or IDs.
	 * @return int[]
	 */
	private static function ids( array $items ): array {
		return array_map(
			static function ( mixed $item ): int {
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

		\Log::error( '[WPOrgFreeScoutImport] Stopped importing ' . $run->remote_mailbox_name . ': ' . $reason );
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
