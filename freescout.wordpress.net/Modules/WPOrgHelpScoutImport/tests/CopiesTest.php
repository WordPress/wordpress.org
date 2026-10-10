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
	 * queued until none are left.
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

		( new ReplaceCopies( (int) $this->mailbox->id ) )->handle();

		$state = Copies::get( (int) $this->mailbox->id );
		$this->assertSame( array( Copies::STATUS_RUNNING, 2 ), array( $state['status'], $state['sent'] ) );
		Queue::assertPushed( ReplaceCopies::class, 2 );

		( new ReplaceCopies( (int) $this->mailbox->id ) )->handle();

		$this->assertSame( Copies::STATUS_DONE, Copies::get( (int) $this->mailbox->id )['status'] );
	}

	/**
	 * A batch WordPress.org doesn't take, after its last try, stops the switch, which can carry on where it stopped.
	 *
	 * @return void
	 */
	public function test_failed_batches_stop_the_switch_until_carried_on(): void {
		$this->import( 1000000028 );
		Copies::start( (int) $this->mailbox->id );

		$queue_job = $this->createMock( DatabaseJob::class );
		$queue_job->method( 'attempts' )->willReturn( 3 );
		$job = new ReplaceCopies( (int) $this->mailbox->id );
		$job->setJob( $queue_job );
		$job->handle();

		$state = Copies::get( (int) $this->mailbox->id );
		$this->assertSame( array( Copies::STATUS_FAILED, 0 ), array( $state['status'], $state['after_id'] ) );
		$this->assertNotSame( '', $state['error'] );

		$this->assertTrue( Copies::start( (int) $this->mailbox->id ) );
		$this->assertFalse( Copies::start( (int) $this->mailbox->id ) );
	}

	/**
	 * Imports that finish after the switch send what they brought, new or imported again, from the start; before it,
	 * nothing is sent.
	 *
	 * @return void
	 */
	public function test_catches_up_on_imports_after_the_switch(): void {
		$this->import( 1000000027 );
		$again = $this->import( 1000000028 );
		ImportedConversation::query()->update( array( 'updated_at' => '2026-10-01 09:00:00' ) );

		Copies::catch_up( (int) $this->mailbox->id, '2026-10-02 10:00:00' );
		Queue::assertNotPushed( ReplaceCopies::class );

		Copies::save(
			(int) $this->mailbox->id,
			array(
				'status'      => Copies::STATUS_DONE,
				'after_id'    => 99,
				'sent'        => 2,
				'since'       => null,
				'started_at'  => '2026-10-01 10:00:00',
				'finished_at' => '2026-10-01 10:05:00',
				'error'       => '',
			)
		);
		$since = (string) now();
		\Eventy::action( 'wporghelpscoutimport.conversation_imported', $again, 1000000028 );
		$new = $this->import( 1000000029 );

		Copies::catch_up( (int) $this->mailbox->id, $since );

		Queue::assertPushed( ReplaceCopies::class, 1 );
		$state = Copies::get( (int) $this->mailbox->id );
		$this->assertSame( array( Copies::STATUS_RUNNING, 0, $since ), array( $state['status'], $state['after_id'], $state['since'] ) );
		$this->assertSame(
			array( (int) $again->id, (int) $new->id ),
			Copies::batch( (int) $this->mailbox->id, 0, 10, $since )->pluck( 'conversation_id' )->map( 'intval' )->all()
		);
	}

	/**
	 * Starting while it's running starts nothing more, as two chains of batches would send everything twice.
	 *
	 * @return void
	 */
	public function test_starts_once(): void {
		$this->assertTrue( Copies::start( (int) $this->mailbox->id ) );
		$this->assertFalse( Copies::start( (int) $this->mailbox->id ) );

		Queue::assertPushed( ReplaceCopies::class, 1 );
	}

	/**
	 * An import that finishes while a send runs has what it brought sent after it, from the start, as the conversations
	 * it imported again may be behind it; a second one widens that.
	 *
	 * @return void
	 */
	public function test_catching_up_while_sending_sends_after_it(): void {
		$sent  = $this->answer();
		$first = $this->import( 1000000027 );
		$again = $this->import( 1000000028 );
		ImportedConversation::query()->update( array( 'updated_at' => '2026-10-01 09:00:00' ) );
		Copies::start( (int) $this->mailbox->id );

		Copies::catch_up( (int) $this->mailbox->id, '2026-10-03 10:00:00' );
		Copies::catch_up( (int) $this->mailbox->id, '2026-10-02 10:00:00' );

		Queue::assertPushed( ReplaceCopies::class, 1 );
		$this->assertSame( '2026-10-02 10:00:00', Copies::get( (int) $this->mailbox->id )['pending'] );

		// Imported again after the send went past it.
		$this->run_batches();
		ImportedConversation::query()->where( 'conversation_id', $again->id )->update( array( 'updated_at' => '2026-10-02 11:00:00' ) );
		$this->run_batches();
		$this->run_batches();
		$this->run_batches();

		$this->assertCount( 2, $sent );
		$this->assertSame( array( (int) $first->id, (int) $again->id ), array_column( $sent[0]['copies'], 'id' ) );
		$this->assertSame( array( (int) $again->id ), array_column( $sent[1]['copies'], 'id' ) );

		$state = Copies::get( (int) $this->mailbox->id );
		$this->assertSame( array( Copies::STATUS_DONE, '2026-10-02 10:00:00' ), array( $state['status'], $state['since'] ) );
		$this->assertArrayNotHasKey( 'pending', $state );
	}

	/**
	 * Catching up on a send that failed starts it again from the start, with the wider of the two, so what it hadn't
	 * sent yet isn't left out.
	 *
	 * @return void
	 */
	public function test_catching_up_after_a_failure_sends_everything_it_had_not(): void {
		$this->answer();
		$this->import( 1000000028 );
		foreach ( array( null, '2026-10-01 10:00:00' ) as $failed_since ) {
			$this->save_state( Copies::STATUS_FAILED, $failed_since );

			Copies::catch_up( (int) $this->mailbox->id, '2026-10-02 10:00:00' );

			$state = Copies::get( (int) $this->mailbox->id );
			$this->assertSame( array( Copies::STATUS_RUNNING, 0, 0, $failed_since ), array( $state['status'], $state['after_id'], $state['sent'], $state['since'] ) );
		}

		Queue::assertPushed( ReplaceCopies::class, 2 );
	}

	/**
	 * Conversations deleted, for good or not, or spam, have their copies deleted, HelpScout's too; the others are
	 * pointed at. Those deleted for good from another mailbox are left to its switch.
	 *
	 * @return void
	 */
	public function test_deleted_and_spam_conversations_have_their_copies_deleted(): void {
		$sent    = $this->answer();
		$kept    = $this->import( 1000000025 );
		$trashed = $this->import( 1000000026 );
		$spam    = $this->import( 1000000027 );
		$gone    = $this->import( 1000000028 );
		$other   = $this->create_conversation( $this->create_mailbox( 'Themes' ), $this->create_sender() );
		$this->import( 1000000029, $other );

		$trashed->state = Conversation::STATE_DELETED;
		$trashed->save();
		$spam->status = Conversation::STATUS_SPAM;
		$spam->save();
		Conversation::deleteConversationsForever( array( (int) $gone->id, (int) $other->id ) );

		Copies::start( (int) $this->mailbox->id );
		$this->run_batches();

		$this->assertSame(
			array(
				(int) $kept->id    => false,
				(int) $trashed->id => true,
				(int) $spam->id    => true,
				(int) $gone->id    => true,
			),
			array_column( $sent[0]['copies'], 'delete', 'id' )
		);
		$this->assertSame( 1000000028, array_column( $sent[0]['copies'], 'helpscout_id', 'id' )[ (int) $gone->id ] );
	}

	/**
	 * A conversation merged into another before the switch is sent as the one it went into, followed on if that was
	 * merged away too, so its HelpScout copy is merged into that one's rather than deleted; one merged into a
	 * conversation deleted since goes.
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
					'merged' => true,
				),
				array_intersect_key( $copies[ $helpscout_id ], array_flip( array( 'id', 'number', 'delete', 'merged' ) ) )
			);
		}
		$this->assertSame( array( (int) $kept->id, false, false ), array( $copies[1000000025]['id'], $copies[1000000025]['delete'], $copies[1000000025]['merged'] ) );
		$this->assertSame( array( (int) $trashed->id, true, true ), array( $copies[1000000029]['id'], $copies[1000000029]['delete'], $copies[1000000029]['merged'] ) );
	}

	/**
	 * Events of an imported conversation deleted for good before they went out leave the copy alone until its mailbox
	 * switched, as for any imported conversation.
	 *
	 * @return void
	 */
	public function test_copy_filter_knows_conversations_deleted_since(): void {
		$gone = $this->import( 1000000028 );
		$id   = (int) $gone->id;
		Conversation::deleteConversationsForever( array( $id ) );

		$this->assertFalse( \Eventy::filter( 'wporgwebhooks.copy', true, null, $id, (int) $this->mailbox->id ) );
		$this->assertSame( 1000000028, \Eventy::filter( 'wporgwebhooks.helpscout_id', 0, null, $id ) );

		$this->save_state( Copies::STATUS_DONE, null );
		$this->assertTrue( \Eventy::filter( 'wporgwebhooks.copy', true, null, $id, (int) $this->mailbox->id ) );
	}

	/**
	 * Runs the next batch, as the queue would.
	 *
	 * @return void
	 */
	private function run_batches(): void {
		( new ReplaceCopies( (int) $this->mailbox->id ) )->handle();
	}

	/**
	 * Saves the mailbox's switch.
	 *
	 * @param string      $status Status.
	 * @param string|null $since  Only conversations imported since then; null for all.
	 * @return void
	 */
	private function save_state( string $status, ?string $since ): void {
		Copies::save(
			(int) $this->mailbox->id,
			array(
				'status'      => $status,
				'after_id'    => 5,
				'sent'        => 5,
				'since'       => $since,
				'started_at'  => '2026-10-01 10:00:00',
				'finished_at' => null,
				'error'       => Copies::STATUS_FAILED === $status ? 'Request to replace-copies.php got a 500 response.' : '',
			)
		);
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
	 * A conversation imported from HelpScout, into the mailbox unless it's given one.
	 *
	 * @param int               $helpscout_id HelpScout's ID for it.
	 * @param Conversation|null $conversation The conversation it was imported as; a new one in the mailbox if null.
	 * @return Conversation
	 */
	private function import( int $helpscout_id, ?Conversation $conversation = null ): Conversation {
		$conversation = $conversation ?? $this->create_conversation( $this->mailbox, $this->create_sender() );
		ImportedConversation::query()->create(
			array(
				'helpscout_id'     => $helpscout_id,
				'helpscout_number' => (int) $conversation->number,
				'conversation_id'  => (int) $conversation->id,
				'mailbox_id'       => (int) $conversation->mailbox_id,
			)
		);

		return $conversation;
	}
}
