<?php
/**
 * Tests for delivering conversation events.
 *
 * @package WordPressdotorg\FreeScout\WPOrgWebhooks
 */

declare( strict_types = 1 );

namespace Modules\WPOrgWebhooks\Tests;

use App\Thread;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Queue\Jobs\DatabaseJob;
use Modules\WPOrgWebhooks\Jobs\SendEvent;
use Modules\WPOrgWebhooks\Providers\WPOrgWebhooksServiceProvider;
use Modules\WPOrgWebhooks\Services\Client;
use Modules\WPOrgWebhooks\Services\EventPayload;
use Psr\Http\Message\RequestInterface;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers retrying events api.wordpress.org didn't take. The test configuration points at a closed port.
 */
final class SendEventTest extends TestCase {

	/**
	 * Registers the module, for its configuration.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgWebhooksServiceProvider::class );
	}

	/**
	 * An unreachable api.wordpress.org gets the event again later.
	 *
	 * @return void
	 */
	public function test_failed_delivery_is_retried_later(): void {
		$queue_job = $this->queue_job( 2 );
		$queue_job->expects( $this->once() )->method( 'release' )->with( 600 );

		$this->send_event( $queue_job );
	}

	/**
	 * After the last attempt the event fails, so it's kept in the failed jobs.
	 *
	 * @return void
	 */
	public function test_last_attempt_fails_the_job(): void {
		$queue_job = $this->queue_job( 3 );
		$queue_job->expects( $this->never() )->method( 'release' );
		$queue_job->expects( $this->once() )->method( 'markAsFailed' );

		$this->send_event( $queue_job );
	}

	/**
	 * Failures that may have reached webhook.php, like the stand-in here, fail the job instead of being retried.
	 *
	 * @return void
	 */
	public function test_other_failures_are_not_retried(): void {
		config( array( 'wporgwebhooks.secret' => '' ) );

		$queue_job = $this->queue_job( 1 );
		$queue_job->expects( $this->never() )->method( 'release' );
		$queue_job->expects( $this->once() )->method( 'markAsFailed' );

		$this->send_event( $queue_job );
	}

	/**
	 * WordPress.org asks for all of the threads of a conversation it has no copy of, and gets the event again with them;
	 * only that one counts.
	 *
	 * @return void
	 */
	public function test_sends_all_threads_when_asked(): void {
		$conversation = $this->create_conversation( $this->create_mailbox(), $this->create_sender() );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Please review', null, '2026-01-01 10:00:00' );
		$this->create_thread( $conversation, Thread::TYPE_NOTE, 'https://wordpress.org/plugins/my-plugin/', $this->create_user(), '2026-01-02 10:00:00' );
		$sent = $this->answer( new Response( 200, array(), '{"threads":"all"}' ), new Response( 200, array(), '{}' ) );

		$queue_job = $this->queue_job( 1 );
		$queue_job->expects( $this->never() )->method( 'release' );
		$this->send_event( $queue_job, EventPayload::build( 'conversation.status_changed', $conversation, null ) );

		$this->assertCount( 2, $sent );
		$this->assertSame( array( false, array() ), array( $sent[0]['email']['all_threads'], $sent[0]['email']['threads'] ) );
		$this->assertTrue( $sent[1]['email']['all_threads'] );
		$this->assertSame( array( 'https://wordpress.org/plugins/my-plugin/', 'Please review' ), array_column( $sent[1]['email']['threads'], 'body' ) );
	}

	/**
	 * An event too large for api.wordpress.org's web server goes again without its threads.
	 *
	 * @return void
	 */
	public function test_too_large_events_go_without_their_threads(): void {
		$conversation = $this->create_conversation( $this->create_mailbox(), $this->create_sender() );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Please review', null, '2026-01-01 10:00:00' );
		$sent = $this->answer( new Response( 413 ), new Response( 200, array(), '{}' ) );

		$queue_job = $this->queue_job( 1 );
		$queue_job->expects( $this->never() )->method( 'markAsFailed' );
		$this->send_event( $queue_job, EventPayload::build( 'conversation.moved', $conversation, null ) );

		$this->assertCount( 2, $sent );
		$this->assertCount( 1, $sent[0]['email']['threads'] );
		$this->assertSame( array(), $sent[1]['email']['threads'] );
		$this->assertSame( 'conversation.moved', $sent[1]['event'] );
	}

	/**
	 * Answers api.wordpress.org's requests in turn.
	 *
	 * @param Response ...$responses What it answers.
	 * @return \ArrayObject The payloads it was sent, as they arrive.
	 */
	private function answer( Response ...$responses ): \ArrayObject {
		$sent    = new \ArrayObject();
		$handler = new MockHandler( $responses );
		$this->app->instance(
			Client::class,
			new Client(
				'https://api.wordpress.test/',
				'test-secret',
				5,
				static function ( RequestInterface $request, array $options ) use ( $sent, $handler ): PromiseInterface {
					$sent[] = json_decode( (string) $request->getBody(), true );

					return $handler( $request, $options );
				}
			)
		);

		return $sent;
	}

	/**
	 * Mocks the queued job an event runs as; FreeScout queues in the database.
	 *
	 * @param int $attempts Attempts so far, including this one.
	 * @return DatabaseJob&\PHPUnit\Framework\MockObject\MockObject
	 */
	private function queue_job( int $attempts ): DatabaseJob {
		$queue_job = $this->createMock( DatabaseJob::class );
		$queue_job->method( 'attempts' )->willReturn( $attempts );

		return $queue_job;
	}

	/**
	 * Runs an event job as the queue worker would.
	 *
	 * @param DatabaseJob $queue_job Queued job the event runs as.
	 * @param array       $payload   Event payload.
	 * @return void
	 */
	private function send_event( DatabaseJob $queue_job, array $payload = array( 'event' => 'conversation.user_replied' ) ): void {
		$event = new SendEvent( $payload );
		$event->setJob( $queue_job );
		$event->handle();
	}
}
