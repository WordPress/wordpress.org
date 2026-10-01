<?php
/**
 * Tests for checking new conversations with Akismet, and reporting what agents mark.
 *
 * @package WordPressdotorg\FreeScout\WPOrgAkismet
 */

declare( strict_types = 1 );

namespace Modules\WPOrgAkismet\Tests;

use App\Conversation;
use App\Folder;
use App\Thread;
use App\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;
use Modules\WPOrgAkismet\Jobs\ReportToAkismet;
use Modules\WPOrgAkismet\Providers\WPOrgAkismetServiceProvider;
use Modules\WPOrgAkismet\Services\Akismet;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers what's checked, what a verdict changes, and when agents' corrections are reported.
 */
final class SpamCheckTest extends TestCase {

	/**
	 * Akismet's queued answers.
	 *
	 * @var MockHandler
	 */
	private $answers;

	/**
	 * Requests sent to Akismet.
	 *
	 * @var array
	 */
	private $requests = array();

	/**
	 * New conversation under test, not yet through the filter.
	 *
	 * @var Conversation
	 */
	private $conversation;

	/**
	 * The sender's email that started it.
	 *
	 * @var Thread
	 */
	private $thread;

	/**
	 * Agent marking conversations.
	 *
	 * @var User
	 */
	private $agent;

	/**
	 * Registers the module with a fake Akismet, and creates a conversation from a sender.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgAkismetServiceProvider::class );
		$this->use_akismet( 'test-key' );

		$this->agent        = $this->create_user();
		$this->conversation = $this->create_conversation( $this->create_mailbox( 'Themes' ), $this->create_sender( 'jane@example.org' ) );
		$this->thread       = $this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, 'Hello', null, '2026-10-01 10:00:00' );

		$this->thread->headers = "Received: from example.org (example.org [93.184.216.34]) by mx.wordpress.org\r\n";
		$this->thread->save();
	}

	/**
	 * Spam goes to the Spam folder before anyone is notified, and the verdict is recorded.
	 *
	 * @return void
	 */
	public function test_spam_is_marked(): void {
		$this->answers->append( new Response( 200, array(), 'true' ) );

		$conversation = $this->filter();

		$this->assertTrue( $conversation->isSpam() );
		$this->assertSame( Folder::TYPE_SPAM, (int) Folder::find( $conversation->folder_id )->type );
		$this->assertSame( array( 'verdict' => Akismet::SPAM ), $conversation->getMeta( WPOrgAkismetServiceProvider::META ) );

		$sent = $this->sent( 0 );
		$this->assertStringEndsWith( '/comment-check', (string) $this->requests[0]['request']->getUri() );
		$this->assertSame( 'test-key', $sent['api_key'] );
		$this->assertSame( '93.184.216.34', $sent['user_ip'] );
		$this->assertSame( 'jane@example.org', $sent['comment_author_email'] );
	}

	/**
	 * Legitimate email comes in as usual.
	 *
	 * @return void
	 */
	public function test_ham_comes_in_as_usual(): void {
		$this->answers->append( new Response( 200, array(), 'false' ) );

		$conversation = $this->filter();

		$this->assertFalse( $conversation->isSpam() );
		$this->assertSame( array( 'verdict' => Akismet::HAM ), $conversation->getMeta( WPOrgAkismetServiceProvider::META ) );
	}

	/**
	 * Imported conversations aren't checked.
	 *
	 * @return void
	 */
	public function test_imported_conversations_are_not_checked(): void {
		$this->conversation->imported = true;

		$this->answers->append( new Response( 200, array(), 'true' ) );

		$this->filter();

		$this->assertCount( 0, $this->requests );
		$this->assertNull( $this->conversation->getMeta( WPOrgAkismetServiceProvider::META ) );
	}

	/**
	 * Without a key, nothing is sent.
	 *
	 * @return void
	 */
	public function test_nothing_is_checked_without_a_key(): void {
		$this->use_akismet( '' );

		$this->answers->append( new Response( 200, array(), 'true' ) );

		$this->filter();

		$this->assertCount( 0, $this->requests );
		$this->assertNull( $this->conversation->getMeta( WPOrgAkismetServiceProvider::META ) );
	}

	/**
	 * Akismet requires an IP address, so email without one isn't checked.
	 *
	 * @return void
	 */
	public function test_email_without_sender_ip_is_not_checked(): void {
		$this->thread->headers = "Received: from greenmail (greenmail [172.18.0.4]) by greenmail\r\n";

		$this->answers->append( new Response( 200, array(), 'true' ) );

		$this->filter();

		$this->assertCount( 0, $this->requests );
		$this->assertNull( $this->conversation->getMeta( WPOrgAkismetServiceProvider::META ) );
	}

	/**
	 * Without a verdict, the conversation comes in as usual, and fetching goes on.
	 *
	 * @return void
	 */
	public function test_errors_let_email_through(): void {
		$this->answers->append( new Response( 200, array( 'X-akismet-debug-help' => 'Empty "blog" value' ), 'invalid' ) );
		$this->answers->append( new Response( 200, array(), 'invalid' ) );
		$this->answers->append( new Response( 500 ) );

		foreach ( array( 'invalid with help', 'invalid key', 'server error' ) as $error ) {
			$conversation = $this->filter();

			$this->assertFalse( $conversation->isSpam(), $error );
			$this->assertNull( $conversation->getMeta( WPOrgAkismetServiceProvider::META ), $error );
		}
	}

	/**
	 * Marking a conversation Akismet passed as spam reports it.
	 *
	 * @return void
	 */
	public function test_missed_spam_is_reported(): void {
		Queue::fake();
		$this->record( array( 'verdict' => Akismet::HAM ) );

		$this->conversation->changeStatus( Conversation::STATUS_SPAM, $this->agent );

		Queue::assertPushed(
			ReportToAkismet::class,
			function ( ReportToAkismet $job ): bool {
				return Akismet::SPAM === $job->verdict && (int) $this->conversation->id === $job->conversation_id;
			}
		);
	}

	/**
	 * Taking a conversation out of spam that Akismet flagged reports it as legitimate.
	 *
	 * @return void
	 */
	public function test_false_positive_is_reported(): void {
		Queue::fake();
		$this->record( array( 'verdict' => Akismet::SPAM ) );
		$this->conversation->setStatus( Conversation::STATUS_SPAM );
		$this->conversation->save();

		$this->conversation->changeStatus( Conversation::STATUS_ACTIVE, $this->agent );

		Queue::assertPushed(
			ReportToAkismet::class,
			static function ( ReportToAkismet $job ): bool {
				return Akismet::HAM === $job->verdict;
			}
		);
	}

	/**
	 * Agreeing with Akismet, changes made without an agent, and conversations never checked aren't reported.
	 *
	 * @return void
	 */
	public function test_agreement_is_not_reported(): void {
		Queue::fake();
		$this->record( array( 'verdict' => Akismet::SPAM ) );
		$this->conversation->changeStatus( Conversation::STATUS_SPAM, $this->agent );

		$this->record( array( 'verdict' => Akismet::HAM ) );
		$this->conversation->changeStatus( Conversation::STATUS_ACTIVE );
		$this->conversation->changeStatus( Conversation::STATUS_SPAM );

		$this->conversation->meta = null;
		$this->conversation->changeStatus( Conversation::STATUS_ACTIVE, $this->agent );
		$this->conversation->changeStatus( Conversation::STATUS_SPAM, $this->agent );

		Queue::assertNotPushed( ReportToAkismet::class );
	}

	/**
	 * Undoing a correction reports the conversation back.
	 *
	 * @return void
	 */
	public function test_undone_correction_is_reported(): void {
		Queue::fake();
		$this->record(
			array(
				'verdict'  => Akismet::HAM,
				'reported' => Akismet::SPAM,
			)
		);
		$this->conversation->setStatus( Conversation::STATUS_SPAM );
		$this->conversation->save();

		$this->conversation->changeStatus( Conversation::STATUS_ACTIVE, $this->agent );

		Queue::assertPushed(
			ReportToAkismet::class,
			static function ( ReportToAkismet $job ): bool {
				return Akismet::HAM === $job->verdict;
			}
		);
	}

	/**
	 * The report sends what the check sent, and is recorded without counting as activity on the conversation.
	 *
	 * @return void
	 */
	public function test_report_is_sent_and_recorded(): void {
		$this->record( array( 'verdict' => Akismet::HAM ) );
		$updated_at = (string) $this->conversation->fresh()->updated_at;
		$this->answers->append( new Response( 200, array(), 'Thanks for making the web a better place.' ) );

		( new ReportToAkismet( (int) $this->conversation->id, Akismet::SPAM ) )->handle();

		$this->assertStringEndsWith( '/submit-spam', (string) $this->requests[0]['request']->getUri() );
		$this->assertSame( 'jane@example.org', $this->sent( 0 )['comment_author_email'] );

		$conversation = $this->conversation->fresh();
		$this->assertSame( Akismet::SPAM, $conversation->getMeta( WPOrgAkismetServiceProvider::META )['reported'] );
		$this->assertSame( $updated_at, (string) $conversation->updated_at );
	}

	/**
	 * A report Akismet didn't accept, like one with an invalid key, isn't recorded, so it's tried again.
	 *
	 * @return void
	 */
	public function test_rejected_report_is_not_recorded(): void {
		$this->record( array( 'verdict' => Akismet::HAM ) );
		$this->answers->append( new Response( 200, array(), 'invalid' ) );

		( new ReportToAkismet( (int) $this->conversation->id, Akismet::SPAM ) )->handle();

		$this->assertCount( 1, $this->requests );
		$this->assertArrayNotHasKey( 'reported', $this->conversation->fresh()->getMeta( WPOrgAkismetServiceProvider::META ) );
	}

	/**
	 * Unexpected hook arguments from a core change don't interrupt the action that fired the hook.
	 *
	 * @return void
	 */
	public function test_unexpected_arguments_do_not_throw(): void {
		$this->assertSame( 'not a conversation', \Eventy::filter( 'conversation.created_by_customer', 'not a conversation' ) );
		\Eventy::action( 'conversation.status_changed', 'not a conversation', 'not a user' );

		$this->assertCount( 0, $this->requests );
	}

	/**
	 * Runs the conversation through the filter core applies before saving a new conversation from a sender.
	 *
	 * @return Conversation
	 */
	private function filter(): Conversation {
		return \Eventy::filter( 'conversation.created_by_customer', $this->conversation, $this->thread, $this->conversation->customer );
	}

	/**
	 * Saves a verdict on the conversation, as a check would have.
	 *
	 * @param array $result Verdict and report.
	 * @return void
	 */
	private function record( array $result ): void {
		$this->conversation->setMeta( WPOrgAkismetServiceProvider::META, $result );
		$this->conversation->save();
	}

	/**
	 * Replaces Akismet with one answering from a queue, and recording what it's sent.
	 *
	 * @param string $key API key.
	 * @return void
	 */
	private function use_akismet( string $key ): void {
		$this->answers  = new MockHandler();
		$this->requests = array();

		$stack = HandlerStack::create( $this->answers );
		$stack->push( Middleware::history( $this->requests ) );

		$this->app->instance( Akismet::class, new Akismet( $key, 'https://rest.akismet.test/1.1/', new \GuzzleHttp\Client( array( 'handler' => $stack ) ) ) );
	}

	/**
	 * Decodes the fields of a request sent to Akismet.
	 *
	 * @param int $index Request number.
	 * @return array
	 */
	private function sent( int $index ): array {
		parse_str( (string) $this->requests[ $index ]['request']->getBody(), $fields );

		return $fields;
	}
}
