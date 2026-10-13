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
use Modules\WPOrgWebhooks\Services\EventPayload;
use Modules\WPOrgWebhooks\Services\NotDeliveredException;
use Modules\WPOrgWebhooks\Services\PayloadTooLargeException;

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
	 * When WordPress.org has no copy of the conversation yet, it takes nothing, and asks for all of its threads, which
	 * the event then carries. Other failures aren't retried, since webhook.php may have counted the event already. They
	 * fail the job rather than throw: the worker would retry anything thrown until the job runs out of tries.
	 *
	 * @return void
	 */
	public function handle(): void {
		try {
			$client   = Client::from_config();
			$response = self::send( $client, self::current( $this->payload ) );

			if ( 'all' === ( $response['threads'] ?? '' ) && empty( $this->payload['all_threads'] ) ) {
				self::send( $client, self::current( array( 'all_threads' => true ) + $this->payload ) );
			}
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

	/**
	 * Posts an event; one too large for api.wordpress.org's web server goes again without its threads, so it still
	 * counts, and the copy still gets what else it says.
	 *
	 * @param Client $client  Client.
	 * @param array  $payload Event payload, as it's sent.
	 * @return array What webhook.php answered.
	 *
	 * @throws PayloadTooLargeException If it's too large without its threads too.
	 */
	private static function send( Client $client, array $payload ): array {
		$endpoint = (string) config( 'wporgwebhooks.endpoint' );

		try {
			return $client->post( $endpoint, $payload );
		} catch ( PayloadTooLargeException $e ) {
			if ( empty( $payload['email']['threads'] ) ) {
				throw $e;
			}

			\Log::error( '[WPOrgWebhooks] Sending conversation ' . (int) ( $payload['conversation']['id'] ?? 0 ) . '\'s event without its threads: ' . $e->getMessage() );
			$payload['email']['threads'] = array();

			return $client->post( $endpoint, $payload );
		}
	}

	/**
	 * The payload with the conversation as it is now; as built, if that can't be read.
	 *
	 * @param array $payload Event payload.
	 * @return array
	 */
	private static function current( array $payload ): array {
		try {
			return EventPayload::refresh( $payload );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgWebhooks] Could not refresh an event: ' . $e->getMessage() );

			return $payload;
		}
	}
}
