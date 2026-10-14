<?php
/**
 * Queued sending of a batch of imported conversations to WordPress.org's copy.
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
use Modules\WPOrgHelpScoutImport\Services\Copies;
use Modules\WPOrgWebhooks\Services\Client;

/**
 * Sends WordPress.org a batch of imported conversations for its copy, and queues the next. Sending a batch again
 * changes nothing.
 */
final class ReplaceCopies implements ShouldQueue {
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;

	/**
	 * How many conversations go in a request; each takes WordPress.org a handful of queries.
	 *
	 * @var int
	 */
	public const BATCH = 250;

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
	 * Token of the send it's part of; a later send stops it.
	 *
	 * @var string
	 */
	public $run;

	/**
	 * ID of the last imported conversation sent.
	 *
	 * @var int
	 */
	public $after_id;

	/**
	 * Constructor.
	 *
	 * @param int    $mailbox_id FreeScout mailbox ID.
	 * @param string $run        Token of the send it's part of.
	 * @param int    $after_id   ID of the last imported conversation sent.
	 */
	public function __construct( int $mailbox_id, string $run, int $after_id = 0 ) {
		$this->mailbox_id = $mailbox_id;
		$this->run        = $run;
		$this->after_id   = $after_id;
	}

	/**
	 * Sends the next batch, and queues the one after.
	 *
	 * Failures don't throw, but are noted for the import page, which can send everything again.
	 *
	 * @return void
	 */
	public function handle(): void {
		$state = Copies::get( $this->mailbox_id );
		if ( ! $state || ( $state['run'] ?? '' ) !== $this->run ) {
			return;
		}

		try {
			$batch = Copies::batch( $this->mailbox_id, $this->after_id, self::BATCH );
			if ( $batch->isEmpty() ) {
				Copies::note( $this->mailbox_id, $this->run, array( 'finished_at' => Carbon::now()->toDateTimeString() ) );

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
			Copies::note( $this->mailbox_id, $this->run, array( 'error' => $e->getMessage() ) );

			return;
		}

		if ( Copies::note( $this->mailbox_id, $this->run, array( 'sent' => (int) $state['sent'] + $batch->count() ) ) ) {
			self::dispatch( $this->mailbox_id, $this->run, (int) $batch->last()->id );
		}
	}
}
