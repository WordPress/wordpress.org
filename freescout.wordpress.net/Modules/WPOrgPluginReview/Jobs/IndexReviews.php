<?php
/**
 * Queued indexing of the review emails FreeScout already has.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Jobs;

use App\Thread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\WPOrgPluginReview\Services\Review;

/**
 * Indexes the review emails that their threads' hooks didn't see: written before the module was switched on, or while
 * it was off.
 *
 * The module's migration queues it once, as it creates the index; `php artisan wporgpluginreview:index` runs it again.
 * It reads a batch of the plugins team's replies at a time, and queues itself again for the next, so no job runs long;
 * running it again is harmless.
 */
final class IndexReviews implements ShouldQueue {
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;

	/**
	 * How many threads are read at a time.
	 *
	 * @var int
	 */
	private const CHUNK = 200;

	/**
	 * How many chunks a job reads before queuing the next.
	 *
	 * @var int
	 */
	private const CHUNKS_PER_JOB = 25;

	/**
	 * ID of the last thread read before this job.
	 *
	 * @var int
	 */
	public $after_id;

	/**
	 * Constructor.
	 *
	 * @param int $after_id ID of the last thread read before this job.
	 */
	public function __construct( int $after_id = 0 ) {
		$this->after_id = $after_id;
	}

	/**
	 * Indexes the next batch of threads, and queues the one after.
	 *
	 * @return void
	 */
	public function handle(): void {
		$last = $this->index_batch();

		if ( null !== $last ) {
			self::dispatch( $last );
		}
	}

	/**
	 * Indexes the next batch of threads.
	 *
	 * @return int|null ID of the last thread read, to start the next batch after; null once there are no more.
	 */
	public function index_batch(): ?int {
		$mailbox_ids = Review::plugins_mailbox_ids();
		if ( ! $mailbox_ids ) {
			return null;
		}

		$last = $this->after_id;

		for ( $chunk = 0; $chunk < self::CHUNKS_PER_JOB; $chunk++ ) {
			$threads = Thread::query()
				->where( 'id', '>', $last )
				->where( 'type', Thread::TYPE_MESSAGE )
				->where( 'state', Thread::STATE_PUBLISHED )
				->whereIn(
					'conversation_id',
					static function ( QueryBuilder $conversations ) use ( $mailbox_ids ): void {
						$conversations->select( 'id' )->from( 'conversations' )->whereIn( 'mailbox_id', $mailbox_ids );
					}
				)
				->where( 'body', 'like', '%Review%' )
				->orderBy( 'id' )
				->limit( self::CHUNK )
				->get();

			foreach ( $threads as $thread ) {
				Review::index_thread( $thread );
				$last = (int) $thread->id;
			}

			if ( $threads->count() < self::CHUNK ) {
				return null;
			}
		}

		return $last;
	}
}
