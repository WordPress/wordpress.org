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
 * Imports a page of 25 conversations, then queues the next page.
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
	 * Imports the run's current page.
	 *
	 * Progress is saved after every conversation, so a page that's stopped part way goes on where it stopped.
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

		try {
			$helpscout = app( HelpScout::class );
			$importer  = new Importer( $helpscout, new People() );
			$page      = $helpscout->conversations( (int) $run->helpscout_mailbox_id, (int) $run->page, $run->since );

			$run->pages = $page['pages'];
			$run->total = $page['total'];

			foreach ( array_slice( $page['conversations'], (int) $run->position ) as $conversation ) {
				$result = $this->import( $importer, (array) $conversation, $mailbox, $run );

				++$run->position;
				if ( $result ) {
					++$run->{$result};
				}

				if ( ! $this->save( $run ) ) {
					return;
				}
			}
		} catch ( RateLimited $e ) {
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

		$run->position = 0;
		++$run->page;

		if ( $run->page > (int) $run->pages ) {
			$run->status      = Run::STATUS_DONE;
			$run->finished_at = Carbon::now();
			$this->save( $run );

			return;
		}

		if ( $this->save( $run ) ) {
			$this->again( 0 );
		}
	}

	/**
	 * Imports one conversation; a conversation that fails is counted and logged, and the page goes on.
	 *
	 * @param Importer     $importer     Importer.
	 * @param array        $conversation HelpScout conversation.
	 * @param \App\Mailbox $mailbox      FreeScout mailbox.
	 * @param Run          $run          Run.
	 * @return string|null The counter to add to, or null.
	 *
	 * @throws ApiError If HelpScout is unavailable or refuses the app, which stops the page.
	 */
	private function import( Importer $importer, array $conversation, \App\Mailbox $mailbox, Run $run ): ?string {
		try {
			return $importer->import( $conversation, $mailbox );
		} catch ( ApiError $e ) {
			// The rate limit, rejected credentials, and outages hold for every conversation.
			if ( $e instanceof RateLimited || $e->is_denied() || 0 === $e->status || $e->status >= 500 ) {
				throw $e;
			}

			$run->last_error = 'HelpScout conversation ' . ( $conversation['id'] ?? '?' ) . ': ' . $e->getMessage();
			\Log::error( '[WPOrgHelpScoutImport] Could not import ' . $run->last_error );

			return 'failed';
		} catch ( \Throwable $e ) {
			$run->last_error = 'HelpScout conversation ' . ( $conversation['id'] ?? '?' ) . ': ' . $e->getMessage();
			\Log::error( '[WPOrgHelpScoutImport] Could not import ' . $run->last_error );

			return 'failed';
		}
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
