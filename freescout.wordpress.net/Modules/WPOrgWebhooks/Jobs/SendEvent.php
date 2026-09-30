<?php
/**
 * Queued delivery of a conversation event.
 *
 * @package WordPressdotorg\FreeScout\WPOrgWebhooks
 */

declare( strict_types = 1 );

namespace Modules\WPOrgWebhooks\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\WPOrgWebhooks\Services\Client;
use Modules\WPOrgWebhooks\Services\NotDeliveredException;

/**
 * Posts an event to the WordPress.org webhook endpoint.
 *
 * Runs on the queue so agents and mail fetching never wait on api.wordpress.org.
 */
final class SendEvent implements ShouldQueue {
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;

	/**
	 * Seconds before an undelivered event is tried again, multiplied by the attempts so far.
	 *
	 * @var int
	 */
	private const RETRY_DELAY = 300;

	/**
	 * How many times the event is tried; FreeScout's worker tries a job only once unless the job says otherwise.
	 *
	 * @var int
	 */
	public $tries = 3;

	/**
	 * Event payload.
	 *
	 * @var array
	 */
	public $payload;

	/**
	 * Constructor.
	 *
	 * @param array $payload Event payload.
	 */
	public function __construct( array $payload ) {
		$this->payload = $payload;
	}

	/**
	 * Sends the event, and tries again later if api.wordpress.org didn't get it.
	 *
	 * Other failures aren't retried, since webhook.php may have counted the event already. They fail the job rather
	 * than throw: the worker would retry anything thrown until the job runs out of tries.
	 *
	 * @return void
	 */
	public function handle(): void {
		try {
			Client::from_config()->post( (string) config( 'wporgwebhooks.endpoint' ), $this->payload );
		} catch ( NotDeliveredException $e ) {
			if ( $this->attempts() < $this->tries ) {
				$this->release( self::RETRY_DELAY * $this->attempts() );

				return;
			}

			$this->fail( $e );
		} catch ( \Throwable $e ) {
			$this->fail( $e );
		}
	}
}
