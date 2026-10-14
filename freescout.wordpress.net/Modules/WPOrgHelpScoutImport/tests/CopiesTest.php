<?php
/**
 * Tests pointing WordPress.org's copies at imported conversations.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use App\Conversation;
use App\Mailbox;
use App\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Support\Facades\Queue;
use Modules\WPOrgHelpScoutImport\Entities\ImportedConversation;
use Modules\WPOrgHelpScoutImport\Jobs\ReplaceCopies;
use Modules\WPOrgHelpScoutImport\Providers\WPOrgHelpScoutImportServiceProvider;
use Modules\WPOrgHelpScoutImport\Services\Copies;
use Modules\WPOrgWebhooks\Providers\WPOrgWebhooksServiceProvider;
use Modules\WPOrgWebhooks\Services\Client;
use Psr\Http\Message\RequestInterface;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Tests for Copies and ReplaceCopies.
 */
final class CopiesTest extends TestCase {

	/**
	 * The mailbox conversations are imported into.
	 *
	 * @var Mailbox
	 */
	private $mailbox;

	/**
	 * Registers the modules, fakes the queue, and logs an administrator in.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgWebhooksServiceProvider::class );
		$this->app->register( WPOrgHelpScoutImportServiceProvider::class );
		$this->app['router']->getRoutes()->refreshNameLookups();

		Queue::fake();
		\Option::$cache = array();
		$this->mailbox  = $this->create_mailbox();
		$this->actingAs( $this->create_user( User::ROLE_ADMIN ) );
	}

	/**
	 * Forgets the switches the test saved, which the database forgets with it.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		\Option::$cache = array();

		parent::tearDown();
	}

	/**
	 * Imported conversations' events leave WordPress.org's copy alone until their mailbox switches; others' don't.
	 *
	 * @return void
	 */
	public function test_imported_conversations_are_copied_once_their_mailbox_switched(): void {
		$imported = $this->import( 1000000028 );
		$new      = $this->create_conversation( $this->mailbox, $this->create_sender() );

		$this->assertFalse( \Eventy::filter( 'wporgwebhooks.copy', true, $imported ) );
		$this->assertTrue( \Eventy::filter( 'wporgwebhooks.copy', true, $new ) );

		$this->post( route( 'wporghelpscoutimport.copies' ), array( 'mailbox_id' => $this->mailbox->id ) );

		$this->assertTrue( \Eventy::filter( 'wporgwebhooks.copy', true, $imported ) );
		Queue::assertPushed( ReplaceCopies::class, 1 );
	}

	/**
	 * Each batch is sent, signed, to api.wordpress.org, which the local environment's mock answers, and the next is
	 * queued after it until none are left, counting what was sent.
	 *
	 * @return void
	 */
	public function test_sends_batches_until_done(): void {
		config(
			array(
				'wporgwebhooks.api_url' => 'http://mock-api:8080/',
				'wporgwebhooks.secret'  => 'local-secret',
			)
		);
		$this->import( 1000000028 );
		$this->import( 1000000029 );
		Copies::start( (int) $this->mailbox->id );
		$run  = $this->send_token();
		$last = (int) ImportedConversation::query()->max( 'id' );

		( new ReplaceCopies( (int) $this->mailbox->id, $run ) )->handle();

		Queue::assertPushed(
			ReplaceCopies::class,
			static function ( ReplaceCopies $job ) use ( $run, $last ): bool {
				return $run === $job->run && $last === $job->after_id;
			}
		);
		$this->assertSame( array( 2, null ), array( $this->state()['sent'], $this->state()['finished_at'] ) );

		( new ReplaceCopies( (int) $this->mailbox->id, $run, $last ) )->handle();

		Queue::assertPushed( ReplaceCopies::class, 2 );
		$this->assertNotNull( $this->state()['finished_at'] );
	}

	/**
	 * A batch WordPress.org refuses on its last try ends the send, noting why until it's sent again.
	 *
	 * @return void
	 */
	public function test_failed_batches_are_noted_until_sent_again(): void {
		$this->import( 1000000028 );
		Copies::start( (int) $this->mailbox->id );

		$queue_job = $this->createMock( DatabaseJob::class );
		$queue_job->method( 'attempts' )->willReturn( 3 );
		$job = new ReplaceCopies( (int) $this->mailbox->id, $this->send_token() );
		$job->setJob( $queue_job );
		$job->handle();

		$this->assertNotSame( '', $this->state()['error'] );
		Queue::assertPushed( ReplaceCopies::class, 1 );

		Copies::start( (int) $this->mailbox->id );

		$this->assertSame( '', $this->state()['error'] );
		Queue::assertPushed( ReplaceCopies::class, 2 );
	}

	/**
	 * Imports finishing after the switch send everything again, which stops the send before and keeps the switch's
	 * date; before it, nothing is sent.
	 *
	 * @return void
	 */
	public function test_imports_after_the_switch_send_everything_again(): void {
		config( array( 'wporgwebhooks.secret' => 'test-secret' ) );
		$this->import( 1000000028 );

		Copies::send_again( (int) $this->mailbox->id );
		Queue::assertNotPushed( ReplaceCopies::class );

		Copies::start( (int) $this->mailbox->id );
		$before = $this->state();
		Copies::send_again( (int) $this->mailbox->id );

		$this->assertNotSame( $before['run'], $this->send_token() );
		$this->assertSame( $before['switched_at'], $this->state()['switched_at'] );
		Queue::assertPushed( ReplaceCopies::class, 2 );

		( new ReplaceCopies( (int) $this->mailbox->id, $before['run'] ) )->handle();

		Queue::assertPushed( ReplaceCopies::class, 2 );
	}

	/**
	 * Copies of conversations deleted or spam go, HelpScout's too; others are pointed at.
	 *
	 * @return void
	 */
	public function test_deleted_and_spam_conversations_have_their_copies_deleted(): void {
		$sent    = $this->answer();
		$kept    = $this->import( 1000000025 );
		$trashed = $this->import( 1000000026 );
		$spam    = $this->import( 1000000027 );

		$trashed->state = Conversation::STATE_DELETED;
		$trashed->save();
		$spam->status = Conversation::STATUS_SPAM;
		$spam->save();

		Copies::start( (int) $this->mailbox->id );
		$this->run_batches();

		$this->assertSame(
			array(
				(int) $kept->id    => false,
				(int) $trashed->id => true,
				(int) $spam->id    => true,
			),
			array_column( $sent[0]['copies'], 'delete', 'id' )
		);
	}

	/**
	 * A conversation merged away before the switch is sent as the one it went into, following further merges; one
	 * merged into a conversation deleted since goes.
	 *
	 * @return void
	 */
	public function test_conversations_merged_away_are_sent_as_the_one_they_went_into(): void {
		$sent    = $this->answer();
		$agent   = $this->create_user();
		$kept    = $this->import( 1000000025 );
		$merged  = $this->import( 1000000026 );
		$twice   = $this->import( 1000000027 );
		$trashed = $this->import( 1000000028 );
		$lost    = $this->import( 1000000029 );

		$twice->mergeConversations( $merged, $agent );
		$kept->mergeConversations( $twice, $agent );
		$trashed->mergeConversations( $lost, $agent );
		$trashed->state = Conversation::STATE_DELETED;
		$trashed->save();

		Copies::start( (int) $this->mailbox->id );
		$this->run_batches();

		$copies = array_column( $sent[0]['copies'], null, 'helpscout_id' );
		foreach ( array( 1000000026, 1000000027 ) as $helpscout_id ) {
			$this->assertSame(
				array(
					'id'     => (int) $kept->id,
					'number' => (int) $kept->number,
					'delete' => false,
				),
				array_intersect_key( $copies[ $helpscout_id ], array_flip( array( 'id', 'number', 'delete' ) ) )
			);
		}
		$this->assertSame( array( (int) $kept->id, false ), array( $copies[1000000025]['id'], $copies[1000000025]['delete'] ) );
		$this->assertSame( array( (int) $trashed->id, true ), array( $copies[1000000029]['id'], $copies[1000000029]['delete'] ) );
	}

	/**
	 * Events of an imported conversation deleted for good before they went out leave the copy alone until its mailbox
	 * switches.
	 *
	 * @return void
	 */
	public function test_copy_filter_knows_conversations_deleted_since(): void {
		$gone = $this->import( 1000000028 );
		$id   = (int) $gone->id;
		Conversation::deleteConversationsForever( array( $id ) );

		$this->assertFalse( \Eventy::filter( 'wporgwebhooks.copy', true, null, $id, (int) $this->mailbox->id ) );
		$this->assertSame( 1000000028, \Eventy::filter( 'wporgwebhooks.helpscout_id', 0, null, $id ) );

		Copies::start( (int) $this->mailbox->id );
		$this->assertTrue( \Eventy::filter( 'wporgwebhooks.copy', true, null, $id, (int) $this->mailbox->id ) );
	}

	/**
	 * Runs the next batch, as the queue would.
	 *
	 * @return void
	 */
	private function run_batches(): void {
		( new ReplaceCopies( (int) $this->mailbox->id, $this->send_token() ) )->handle();
	}

	/**
	 * The mailbox's switch.
	 *
	 * @return array
	 */
	private function state(): array {
		return (array) Copies::get( (int) $this->mailbox->id );
	}

	/**
	 * The token of the mailbox's latest send.
	 *
	 * @return string
	 */
	private function send_token(): string {
		return (string) ( $this->state()['run'] ?? '' );
	}

	/**
	 * Answers WordPress.org's requests, every one taken.
	 *
	 * @return \ArrayObject The payloads it was sent, as they arrive.
	 */
	private function answer(): \ArrayObject {
		$sent = new \ArrayObject();
		$this->app->instance(
			Client::class,
			new Client(
				'https://api.wordpress.test/',
				'test-secret',
				5,
				static function ( RequestInterface $request, array $options ) use ( $sent ): PromiseInterface {
					$sent[] = json_decode( (string) $request->getBody(), true );

					return ( new MockHandler( array( new Response( 200, array(), '{"replaced":1}' ) ) ) )( $request, $options );
				}
			)
		);

		return $sent;
	}

	/**
	 * A conversation imported from HelpScout into the mailbox.
	 *
	 * @param int $helpscout_id HelpScout's ID for it.
	 * @return Conversation
	 */
	private function import( int $helpscout_id ): Conversation {
		$conversation = $this->create_conversation( $this->mailbox, $this->create_sender() );
		ImportedConversation::query()->create(
			array(
				'helpscout_id'     => $helpscout_id,
				'helpscout_number' => (int) $conversation->number,
				'conversation_id'  => (int) $conversation->id,
			)
		);

		return $conversation;
	}
}
