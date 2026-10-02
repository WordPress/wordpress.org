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
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\Importer;
use Modules\WPOrgHelpScoutImport\Services\People;

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
			$importer  = new Importer( $helpscout, new People( $helpscout ) );

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
				$page   = $helpscout->conversations( (int) $run->helpscout_mailbox_id, (int) $run->page, $run->since );
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

				$run->pages = $page['pages'];
				$run->total = $page['total'];
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

				// The rate limit cut this one off before: it needs more than a minute's share, so this time it waits.
				$helpscout->set_patient( $current === (int) $run->waiting_on );

				$this->import( $importer, $helpscout, (array) $conversation, $mailbox, $run );
				$done[]          = $current;
				$run->page_done  = $done;
				$run->waiting_on = null;

				if ( ! $this->save( $run ) ) {
					return;
				}
			}
		} catch ( RateLimited $e ) {
			$run->waiting_on = $current ? $current : null;
			$this->save( $run );
			$this->again( $e->retry_after );

			return;
		} catch ( ApiError $e ) {
			if ( $e->is_denied() ) {
				$this->stop( $run, $e->getMessage() );
			} else {
				$run->last_error = $e->getMessage();
				$this->save( $run );
				$this->again( self::RETRY_DELAY );
			}

			return;
		} catch ( \Throwable $e ) {
			// FreeScout's worker doesn't retry jobs: without this, the run would wait for a job that's gone.
			$run->last_error = $e->getMessage();
			\Log::error( '[WPOrgHelpScoutImport] Could not import a page of ' . $run->helpscout_mailbox_name . ': ' . $e->getMessage() );
			$this->save( $run );
			$this->again( self::RETRY_DELAY );

			return;
		} finally {
			$mailbox->updateFoldersCounters();
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

			$result = $conversation ? $importer->import( $conversation, $mailbox ) : Importer::SKIPPED_GONE;
		} catch ( ApiError $e ) {
			// The rate limit, rejected credentials, and outages hold for every conversation.
			if ( $e instanceof RateLimited || $e->is_denied() || 0 === $e->status || $e->status >= 500 ) {
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
	 * Counts and logs a conversation that failed.
	 *
	 * @param Run        $run Run.
	 * @param int        $id  HelpScout conversation ID.
	 * @param \Throwable $e   What went wrong.
	 * @return void
	 */
	private function fail( Run $run, int $id, \Throwable $e ): void {
		$run->add_failure( $id, $e->getMessage() );
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
