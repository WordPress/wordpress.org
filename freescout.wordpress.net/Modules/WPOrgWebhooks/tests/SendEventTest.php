<?php
/**
 * Tests for delivering conversation events.
 *
 * @package WordPressdotorg\FreeScout\WPOrgWebhooks
 */

declare( strict_types = 1 );

namespace Modules\WPOrgWebhooks\Tests;

use Illuminate\Queue\Jobs\DatabaseJob;
use Modules\WPOrgWebhooks\Jobs\SendEvent;
use Modules\WPOrgWebhooks\Providers\WPOrgWebhooksServiceProvider;
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
	 * @return void
	 */
	private function send_event( DatabaseJob $queue_job ): void {
		$event = new SendEvent( array( 'event' => 'conversation.user_replied' ) );
		$event->setJob( $queue_job );
		$event->handle();
	}
}
