<?php
/**
 * Indexes the review emails again.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Console;

use Illuminate\Console\Command;
use Modules\WPOrgPluginReview\Jobs\IndexReviews as IndexReviewsJob;

/**
 * Brings the index of review emails up to date with every reply of the plugins team's mailbox, like after the module
 * was switched off for a while, when their threads' hooks didn't run.
 */
final class IndexReviews extends Command {

	/**
	 * Command signature.
	 *
	 * @var string
	 */
	protected $signature = 'wporgpluginreview:index {--now : Index in this process instead of on the queue}';

	/**
	 * Command description.
	 *
	 * @var string
	 */
	protected $description = 'Indexes the plugin review emails FreeScout already has, on the queue unless asked to do it now.';

	/**
	 * Runs the command.
	 *
	 * @return int Exit code.
	 */
	public function handle(): int {
		if ( ! $this->option( 'now' ) ) {
			IndexReviewsJob::dispatch();
			$this->line( 'Indexing the review emails on the queue.' );

			return 0;
		}

		$last = 0;
		do {
			$last = ( new IndexReviewsJob( $last ) )->index_batch();
		} while ( null !== $last );

		$this->line( 'Indexed the review emails.' );

		return 0;
	}
}
