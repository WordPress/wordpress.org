<?php
/**
 * Tests for importing page by page.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use App\Conversation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Modules\WPOrgHelpScoutImport\Entities\Run;
use Modules\WPOrgHelpScoutImport\Jobs\ImportPage;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Tests\Support\FakeHelpScout;

require_once __DIR__ . '/ImportTestCase.php';

/**
 * Covers progress, the next page, pausing, waiting for the rate limit, and failures.
 */
final class ImportPageTest extends ImportTestCase {

	/**
	 * Run under test.
	 *
	 * @var Run
	 */
	private $run;

	/**
	 * Fakes the queue, and creates a running run.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Queue::fake();

		$this->run                         = new Run();
		$this->run->helpscout_mailbox_id   = 77;
		$this->run->helpscout_mailbox_name = 'Photos';
		$this->run->mailbox_id             = $this->mailbox->id;
		$this->run->status                 = Run::STATUS_RUNNING;
		$this->run->started_at             = Carbon::now();
		$this->run->renew_token();
		$this->run->save();
	}

	/**
	 * A page is imported, counted, and followed by the next.
	 *
	 * @return void
	 */
	public function test_page_is_imported_and_next_one_queued(): void {
		$this->answer_page(
			array(
				$this->conversation(),
				$this->conversation(
					array(
						'id'     => 1002,
						'status' => 'spam',
					)
				),
			),
			2
		);

		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( 2, (int) $run->page );
		$this->assertSame( 0, (int) $run->position );
		$this->assertSame( 1, (int) $run->imported );
		$this->assertSame( 1, (int) $run->skipped );
		$this->assertSame( 2, (int) $run->pages );
		$this->assertSame( Run::STATUS_RUNNING, $run->status );
		Queue::assertPushed(
			ImportPage::class,
			function ( ImportPage $job ): bool {
				return (int) $this->run->id === $job->run_id && $this->run->token === $job->token;
			}
		);
	}

	/**
	 * The last page finishes the run.
	 *
	 * @return void
	 */
	public function test_last_page_finishes_the_run(): void {
		$this->answer_page( array( $this->conversation() ), 1 );

		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( Run::STATUS_DONE, $run->status );
		$this->assertNotNull( $run->finished_at );
		Queue::assertNotPushed( ImportPage::class );
	}

	/**
	 * A job queued before the run was paused or resumed does nothing.
	 *
	 * @return void
	 */
	public function test_outdated_job_does_nothing(): void {
		$this->answer_page( array( $this->conversation() ), 1 );
		$old = $this->run->token;
		$this->run->renew_token();
		$this->run->save();

		( new ImportPage( (int) $this->run->id, (string) $old ) )->handle();

		$this->assertCount( 0, $this->helpscout->requests_to( 'v2/conversations' ) );
		$this->assertSame( 1, (int) $this->run->fresh()->page );
	}

	/**
	 * Hitting the rate limit part way saves the progress, and tries the page again after the wait.
	 *
	 * @return void
	 */
	public function test_rate_limit_waits_and_resumes_where_it_stopped(): void {
		$this->answer_page( array( $this->conversation(), $this->conversation( array( 'id' => 1002 ) ) ), 1 );
		$this->helpscout->only( 'GET', 'v2/conversations/1002/threads', FakeHelpScout::json( array(), 429, array( 'X-RateLimit-Retry-After' => '30' ) ) );

		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( 1, (int) $run->page );
		$this->assertSame( 1, (int) $run->position );
		$this->assertSame( 1, (int) $run->imported );
		Queue::assertPushed(
			ImportPage::class,
			static function ( ImportPage $job ): bool {
				return $job->delay instanceof Carbon && $job->delay->diffInSeconds( Carbon::now() ) >= 29;
			}
		);

		// The first conversation isn't imported a second time.
		$this->answer_threads( 1002, $this->threads( 100 ) );
		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( 2, (int) $run->imported );
		$this->assertSame( 0, (int) $run->updated );
		$this->assertSame( Run::STATUS_DONE, $run->status );
	}

	/**
	 * A conversation that fails is counted and logged; the page goes on.
	 *
	 * @return void
	 */
	public function test_failed_conversation_is_counted_and_page_goes_on(): void {
		$this->answer_page( array( $this->conversation( array( 'id' => 1003 ) ), $this->conversation() ), 1 );

		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( 1, (int) $run->failed );
		$this->assertSame( 1, (int) $run->imported );
		$this->assertStringContainsString( '1003', (string) $run->last_error );
		$this->assertSame( Run::STATUS_DONE, $run->status );
	}

	/**
	 * Credentials HelpScout refuses stop the run.
	 *
	 * @return void
	 */
	public function test_refused_credentials_stop_the_run(): void {
		$this->helpscout->only( 'POST', 'v2/oauth2/token', FakeHelpScout::json( array(), 401 ) );

		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( Run::STATUS_FAILED, $run->status );
		$this->assertNotEmpty( $run->last_error );
		Queue::assertNotPushed( ImportPage::class );
	}

	/**
	 * HelpScout failing for a while is waited out.
	 *
	 * @return void
	 */
	public function test_outage_is_waited_out(): void {
		$this->helpscout->on( 'GET', 'v2/conversations', FakeHelpScout::json( array(), 503 ) );

		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( Run::STATUS_RUNNING, $run->status );
		$this->assertStringContainsString( '503', (string) $run->last_error );
		Queue::assertPushed( ImportPage::class );
	}

	/**
	 * Anything else going wrong is tried again later, rather than leaving the run waiting.
	 *
	 * @return void
	 */
	public function test_unexpected_error_is_tried_again(): void {
		$this->app->bind(
			HelpScout::class,
			static function (): HelpScout {
				throw new \LogicException( 'Unexpected.' );
			}
		);

		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( Run::STATUS_RUNNING, $run->status );
		$this->assertSame( 'Unexpected.', $run->last_error );
		Queue::assertPushed( ImportPage::class );
	}

	/**
	 * Pausing stops the page after the conversation being imported.
	 *
	 * @return void
	 */
	public function test_pausing_stops_after_the_current_conversation(): void {
		$this->answer_page( array( $this->conversation(), $this->conversation( array( 'id' => 1002 ) ) ), 1 );
		$this->answer_threads( 1002, $this->threads( 100 ) );
		\Eventy::addAction(
			'conversation.updated',
			function (): void {
				Run::query()->whereKey( $this->run->id )->update(
					array(
						'status' => Run::STATUS_PAUSED,
						'token'  => 'paused',
					)
				);
			}
		);

		$this->handle();

		$this->assertSame( 1, Conversation::query()->where( 'mailbox_id', $this->mailbox->id )->count() );
		$this->assertSame( Run::STATUS_PAUSED, $this->run->fresh()->status );
		Queue::assertNotPushed( ImportPage::class );
	}

	/**
	 * Runs the current job.
	 *
	 * @return void
	 */
	private function handle(): void {
		( new ImportPage( (int) $this->run->id, (string) $this->run->fresh()->token ) )->handle();
	}

	/**
	 * Has HelpScout list conversations.
	 *
	 * @param array[] $conversations Conversations on the page.
	 * @param int     $pages         Number of pages.
	 * @return void
	 */
	private function answer_page( array $conversations, int $pages ): void {
		$this->helpscout->only(
			'GET',
			'v2/conversations',
			array(
				'_embedded' => array( 'conversations' => $conversations ),
				'page'      => array(
					'totalPages'    => $pages,
					'totalElements' => count( $conversations ) * $pages,
				),
			)
		);
	}
}
