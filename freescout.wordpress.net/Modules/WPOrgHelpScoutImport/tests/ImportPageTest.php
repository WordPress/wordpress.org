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
		$this->assertNull( $run->page_done );
		$this->assertSame( array( 1001, 1002 ), $run->previous_page );
		$this->assertSame( 1, (int) $run->imported );
		$this->assertSame( 1, (int) $run->skipped );
		$this->assertSame( array( 'spam' => 1 ), $run->skips );
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
	 * An empty page finishes the run.
	 *
	 * @return void
	 */
	public function test_empty_page_finishes_the_run(): void {
		$this->answer_page( array( $this->conversation() ), 1 );

		$this->handle();
		$this->assertSame( Run::STATUS_RUNNING, $this->run->fresh()->status );

		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( Run::STATUS_DONE, $run->status );
		$this->assertNotNull( $run->finished_at );
		$this->assertSame( 1, (int) $run->imported );
		$this->assertSame( 0, (int) $run->updated );
		Queue::assertPushed( ImportPage::class, 1 );
	}

	/**
	 * Conversations that moved onto a page already read, because one before them was deleted, are imported too.
	 *
	 * @return void
	 */
	public function test_conversations_moved_onto_read_pages_are_imported(): void {
		foreach ( array( 1002, 1003, 1004 ) as $index => $id ) {
			$this->answer_threads( $id, $this->threads( 100 * ( $index + 1 ) ) );
		}
		$conversations = array_map(
			function ( int $id ): array {
				return $this->conversation( array( 'id' => $id ) );
			},
			array( 1001, 1002, 1003, 1004 )
		);
		$this->answer_list( 1, array_slice( $conversations, 0, 2 ), 2 );
		$this->answer_list( 2, array_slice( $conversations, 2, 2 ), 2 );

		$this->handle();

		// 1001 is deleted in HelpScout: 1003 moves onto page 1.
		$this->answer_list( 1, array_slice( $conversations, 1, 2 ), 2 );
		$this->answer_list( 2, array_slice( $conversations, 3, 1 ), 2 );
		$this->handle();

		$this->answer_list( 3, array(), 2 );
		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( 4, (int) $run->imported );
		$this->assertSame( 0, (int) $run->updated );
		$this->assertSame( Run::STATUS_DONE, $run->status );
	}

	/**
	 * What HelpScout sends but can't be read fails that conversation; the page isn't tried again for it.
	 *
	 * @return void
	 */
	public function test_unreadable_content_fails_only_its_conversation(): void {
		$this->answer_page( array( $this->conversation() ), 1 );
		$this->helpscout->only( 'GET', 'v2/conversations/1001/attachments/3001/file', FakeHelpScout::json( array(), 400 ) );

		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( 1, (int) $run->failed );
		$this->assertSame( array( 1001 ), array_keys( (array) $run->failures ) );
		$this->assertSame( 2, (int) $run->page );
		Queue::assertPushed(
			ImportPage::class,
			static function ( ImportPage $job ): bool {
				return null === $job->delay;
			}
		);
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
		$this->assertSame( array( 1001 ), $run->page_done );
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
		$this->assertSame( 2, (int) $run->page );
	}

	/**
	 * A conversation the rate limit cut off part way waits for the limit on its next try, so it can finish.
	 *
	 * @return void
	 */
	public function test_conversation_cut_off_by_the_rate_limit_waits_next_time(): void {
		$this->answer_page( array( $this->conversation() ), 1 );
		$threads = array(
			'_embedded' => array( 'threads' => array_reverse( $this->threads() ) ),
			'page'      => array( 'totalPages' => 1 ),
		);
		$limited = FakeHelpScout::json( array(), 429, array( 'X-RateLimit-Retry-After' => '30' ) );
		$this->helpscout->only( 'GET', 'v2/conversations/1001/threads', $limited )->on( 'GET', 'v2/conversations/1001/threads', $limited )->on( 'GET', 'v2/conversations/1001/threads', $threads );

		$this->handle();

		$this->assertSame( 1001, (int) $this->run->fresh()->waiting_on );
		$this->assertSame( array(), $this->helpscout->slept );

		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( array( 30 ), $this->helpscout->slept );
		$this->assertSame( 1, (int) $run->imported );
		$this->assertNull( $run->waiting_on );
	}

	/**
	 * A run that retries failed conversations gets each from HelpScout, and is done once they are.
	 *
	 * @return void
	 */
	public function test_retry_imports_its_conversations(): void {
		$this->run->retry_ids = array( 1001, 1005 );
		$this->run->save();
		$this->helpscout->only( 'GET', 'v2/conversations/1001', $this->conversation() );

		$this->handle();

		$run = $this->run->fresh();
		$this->assertSame( 1, (int) $run->imported );
		$this->assertSame( array( 'gone' => 1 ), $run->skips );
		$this->assertSame( Run::STATUS_DONE, $run->status );
		$this->assertCount( 0, $this->helpscout->requests_to( 'v2/conversations' ) );
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
		$this->assertStringContainsString( '404', (string) $run->failures[1003] );
		$this->assertSame( 2, (int) $run->page );
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
		$this->helpscout->only( 'GET', 'v2/conversations', self::listing( array(), $pages ) );
		$this->answer_list( 1, $conversations, $pages );
	}

	/**
	 * Has HelpScout list one page of conversations.
	 *
	 * @param int     $page          Page number.
	 * @param array[] $conversations Conversations on the page.
	 * @param int     $pages         Number of pages.
	 * @return void
	 */
	private function answer_list( int $page, array $conversations, int $pages ): void {
		$this->helpscout->only_page( 'v2/conversations', $page, self::listing( $conversations, $pages ) );
	}

	/**
	 * A page of HelpScout's conversation list.
	 *
	 * @param array[] $conversations Conversations on the page.
	 * @param int     $pages         Number of pages.
	 * @return array
	 */
	private static function listing( array $conversations, int $pages ): array {
		return array(
			'_embedded' => array( 'conversations' => $conversations ),
			'page'      => array(
				'totalPages'    => $pages,
				'totalElements' => count( $conversations ) * $pages,
			),
		);
	}
}
