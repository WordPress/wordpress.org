<?php
/**
 * Queued report of a conversation Akismet got wrong.
 *
 * @package WordPressdotorg\FreeScout\WPOrgAkismet
 */

declare( strict_types = 1 );

namespace Modules\WPOrgAkismet\Jobs;

use App\Conversation;
use App\Thread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\WPOrgAkismet\Providers\WPOrgAkismetServiceProvider;
use Modules\WPOrgAkismet\Services\Akismet;
use Modules\WPOrgAkismet\Services\Message;

/**
 * Tells Akismet what an agent marked a conversation as.
 *
 * Runs on the queue so agents never wait on Akismet.
 */
final class ReportToAkismet implements ShouldQueue {
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;

	/**
	 * Seconds before a failed report is tried again, multiplied by the attempts so far.
	 *
	 * @var int
	 */
	private const RETRY_DELAY = 300;

	/**
	 * How many times the report is tried; FreeScout's worker tries a job only once unless the job says otherwise.
	 *
	 * @var int
	 */
	public $tries = 3;

	/**
	 * Conversation ID.
	 *
	 * @var int
	 */
	public $conversation_id;

	/**
	 * What the agent marked it as: Akismet::SPAM or Akismet::HAM.
	 *
	 * @var string
	 */
	public $verdict;

	/**
	 * Constructor.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $verdict         What the agent marked it as.
	 */
	public function __construct( int $conversation_id, string $verdict ) {
		$this->conversation_id = $conversation_id;
		$this->verdict         = $verdict;
	}

	/**
	 * Sends the report, and records it so the same correction isn't sent twice.
	 *
	 * @return void
	 */
	public function handle(): void {
		$conversation = Conversation::find( $this->conversation_id );

		// An agent may have changed it back while the report waited.
		if ( ! $conversation || ( $conversation->isSpam() ? Akismet::SPAM : Akismet::HAM ) !== $this->verdict ) {
			return;
		}

		// Another job may have sent the same correction since.
		$result = (array) $conversation->getMeta( WPOrgAkismetServiceProvider::META, array() );
		if ( ( $result['reported'] ?? $result['verdict'] ?? '' ) === $this->verdict ) {
			return;
		}

		$thread = $conversation->threads()
			->where( 'type', Thread::TYPE_CUSTOMER )
			->orderBy( 'id' )
			->first();

		$fields = $thread ? Message::fields( $thread, (string) ( $result['subject'] ?? $conversation->subject ) ) : null;
		if ( ! $fields ) {
			return;
		}

		try {
			app( Akismet::class )->submit( $this->verdict, $fields );
		} catch ( \Throwable $e ) {
			if ( $this->attempts() < $this->tries ) {
				$this->release( self::RETRY_DELAY * $this->attempts() );

				return;
			}

			$this->fail( $e );

			return;
		}

		$result['reported'] = $this->verdict;
		$conversation->setMeta( WPOrgAkismetServiceProvider::META, $result );

		// Recording the report isn't activity on the conversation.
		$conversation->timestamps = false;
		$conversation->save();
	}
}
