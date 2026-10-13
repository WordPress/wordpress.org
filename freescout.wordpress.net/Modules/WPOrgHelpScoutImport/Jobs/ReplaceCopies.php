<?php
/**
 * Queued sending of a batch of imported conversations to WordPress.org's copy.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\WPOrgHelpScoutImport\Services\Copies;
use Modules\WPOrgWebhooks\Services\Client;

/**
 * Tells WordPress.org which FreeScout conversation each of a batch of HelpScout's was imported as, or that its copy goes,
 * and queues the next batch.
 *
 * WordPress.org swaps the IDs in its copy, so running a batch again does nothing more.
 */
final class ReplaceCopies implements ShouldQueue {
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;

	/**
	 * How many conversations go in a request.
	 *
	 * @var int
	 */
	public const BATCH = 1000;

	/**
	 * The endpoint on api.wordpress.org.
	 *
	 * @var string
	 */
	private const ENDPOINT = 'replace-copies.php';

	/**
	 * Seconds before a batch WordPress.org didn't take is tried again, multiplied by the attempts so far.
	 *
	 * @var int
	 */
	private const RETRY_DELAY = 60;

	/**
	 * How many times a batch is tried.
	 *
	 * @var int
	 */
	public $tries = 3;

	/**
	 * FreeScout mailbox ID.
	 *
	 * @var int
	 */
	public $mailbox_id;

	/**
	 * Constructor.
	 *
	 * @param int $mailbox_id FreeScout mailbox ID.
	 */
	public function __construct( int $mailbox_id ) {
		$this->mailbox_id = $mailbox_id;
	}

	/**
	 * Sends the next batch, and queues the one after.
	 *
	 * Failures don't throw: the worker would retry anything thrown, and the switch would be left running.
	 *
	 * @return void
	 */
	public function handle(): void {
		$state = Copies::get( $this->mailbox_id );
		if ( ! $state || Copies::STATUS_RUNNING !== $state['status'] ) {
			return;
		}

		try {
			$batch = Copies::batch( $this->mailbox_id, (int) $state['after_id'], self::BATCH, $state['since'] ?? null );

			if ( $batch->isEmpty() ) {
				// Imports that finished while it ran may want another.
				if ( Copies::finish( $this->mailbox_id ) ) {
					self::dispatch( $this->mailbox_id );
				}

				return;
			}

			Client::from_config( 60 )->post(
				self::ENDPOINT,
				array( 'copies' => $batch->map( array( Copies::class, 'request' ) )->values()->all() )
			);
		} catch ( \Throwable $e ) {
			if ( $this->attempts() < $this->tries ) {
				$this->release( self::RETRY_DELAY * $this->attempts() );

				return;
			}

			\Log::error( '[WPOrgHelpScoutImport] Could not point WordPress.org at mailbox ' . $this->mailbox_id . '\'s conversations: ' . $e->getMessage() );
			Copies::fail( $this->mailbox_id, $e->getMessage() );

			return;
		}

		Copies::advance( $this->mailbox_id, (int) $batch->last()->id, $batch->count() );

		self::dispatch( $this->mailbox_id );
	}
}
