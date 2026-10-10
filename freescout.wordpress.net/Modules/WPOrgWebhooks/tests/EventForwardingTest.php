<?php
/**
 * Tests for forwarding conversation events.
 *
 * @package WordPressdotorg\FreeScout\WPOrgWebhooks
 */

declare( strict_types = 1 );

namespace Modules\WPOrgWebhooks\Tests;

use App\Conversation;
use App\Thread;
use App\User;
use Illuminate\Support\Facades\Queue;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgWebhooks\Jobs\SendEvent;
use Modules\WPOrgWebhooks\Providers\WPOrgWebhooksServiceProvider;
use Modules\WPOrgWebhooks\Services\EventPayload;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers which hooks are forwarded, and whom they're attributed to.
 */
final class EventForwardingTest extends TestCase {

	/**
	 * Agent acting on the conversation.
	 *
	 * @var User
	 */
	private $agent;

	/**
	 * Conversation under test.
	 *
	 * @var Conversation
	 */
	private $conversation;

	/**
	 * Registers the module, fakes the queue, and creates a conversation.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgWebhooksServiceProvider::class );
		Queue::fake();

		$this->agent        = $this->create_user();
		$this->conversation = $this->create_conversation( $this->create_mailbox( 'Plugins' ), $this->create_sender() );
	}

	/**
	 * Agent replies are attributed to the agent, for contributor stats.
	 *
	 * @return void
	 */
	public function test_agent_reply_is_attributed_to_the_agent(): void {
		$thread = $this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, 'Answer', $this->agent, '2026-09-01 11:00:00' );

		\Eventy::action( 'conversation.user_replied', $this->conversation, $thread );

		$this->assert_queued( 'conversation.user_replied', (string) $this->agent->email );
	}

	/**
	 * An agent connected to a WordPress.org account is credited by its username, not by an address.
	 *
	 * @return void
	 */
	public function test_connected_agent_is_attributed_by_username(): void {
		Account::connect( (int) $this->agent->id, 'rita' );
		$thread = $this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, 'Answer', $this->agent, '2026-09-01 11:00:00' );

		\Eventy::action( 'conversation.user_replied', $this->conversation, $thread );

		Queue::assertPushed(
			SendEvent::class,
			static function ( SendEvent $job ): bool {
				return 'rita' === $job->payload['agent']['wporg_username'];
			}
		);
	}

	/**
	 * Replies undone within the undo window are turned back into drafts, and aren't counted.
	 *
	 * @return void
	 */
	public function test_undone_reply_is_not_forwarded(): void {
		$sent = $this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, 'Answer', $this->agent, '2026-09-01 11:00:00' );
		Thread::where( 'id', $sent->id )->update( array( 'state' => Thread::STATE_DRAFT ) );

		// Core's delayed hook passes the reply as it was when sent.
		\Eventy::action( 'conversation.user_replied', $this->conversation, $sent );

		Queue::assertNotPushed( SendEvent::class );
	}

	/**
	 * Undoing a reply and sending it again within the undo window fires the hook twice, but it's one reply.
	 *
	 * @return void
	 */
	public function test_resent_reply_is_counted_once(): void {
		$thread = $this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, 'Answer', $this->agent, '2026-09-01 11:00:00' );

		\Eventy::action( 'conversation.user_replied', $this->conversation, $thread );
		\Eventy::action( 'conversation.user_replied', $this->conversation, $thread );

		Queue::assertPushed( SendEvent::class, 1 );
	}

	/**
	 * Unexpected hook arguments from a core change don't interrupt the action that fired the hook.
	 *
	 * @return void
	 */
	public function test_unexpected_arguments_do_not_throw(): void {
		\Eventy::action( 'conversation.user_replied', $this->conversation );
		\Eventy::action( 'conversation.status_changed', 'not a conversation', 'not a user' );

		Queue::assertNotPushed( SendEvent::class );
	}

	/**
	 * Conversations started by a sender have no agent to credit.
	 *
	 * @return void
	 */
	public function test_sender_conversation_has_no_agent(): void {
		\Eventy::action( 'conversation.created_by_customer', $this->conversation, null, null );

		$this->assert_queued( 'conversation.created_by_customer', null );
	}

	/**
	 * Status changes carry the agent that made them.
	 *
	 * @return void
	 */
	public function test_status_change_is_attributed_to_the_agent(): void {
		\Eventy::action( 'conversation.status_changed', $this->conversation, $this->agent, false, Conversation::STATUS_ACTIVE );

		$this->assert_queued( 'conversation.status_changed', (string) $this->agent->email );
	}

	/**
	 * Moving a conversation to the trash is attributed to the agent.
	 *
	 * @return void
	 */
	public function test_deletion_is_attributed_to_the_agent(): void {
		$this->conversation->deleteToFolder( $this->agent );

		$this->assert_queued( 'conversation.deleted', (string) $this->agent->email );
	}

	/**
	 * Merging deletes the merged conversation, which counts only as the merge.
	 *
	 * @return void
	 */
	public function test_merge_is_not_also_counted_as_a_deletion(): void {
		$second = $this->create_conversation( $this->conversation->mailbox, $this->create_sender() );

		$this->conversation->mergeConversations( $second, $this->agent );

		$this->assert_queued( 'conversation.merged', (string) $this->agent->email );
		Queue::assertPushed( SendEvent::class, 1 );

		// WordPress.org drops its copy of the merged conversation.
		$this->assertSame( (int) $second->id, $this->payload()['merged_id'] );
	}

	/**
	 * Restoring a conversation from Deleted is sent, so WordPress.org copies it again; other state changes aren't.
	 *
	 * @return void
	 */
	public function test_restoring_a_conversation_is_forwarded(): void {
		\Eventy::action( 'conversation.state_changed', $this->conversation, $this->agent, Conversation::STATE_DRAFT );
		Queue::assertNotPushed( SendEvent::class );

		\Eventy::action( 'conversation.state_changed', $this->conversation, $this->agent, Conversation::STATE_DELETED );
		$this->assert_queued( 'conversation.restored', (string) $this->agent->email );
	}

	/**
	 * Events carry the conversation as WordPress.org keeps a copy of it: its details, and sender; one that doesn't add a
	 * thread, no threads.
	 *
	 * @return void
	 */
	public function test_events_carry_the_conversation(): void {
		$this->conversation->update(
			array(
				'subject' => '[WordPress Plugin Directory] Review in Progress: My Plugin',
				'status'  => Conversation::STATUS_CLOSED,
			)
		);
		$this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, 'Please review', null, '2026-01-01 10:00:00' );
		$this->create_thread( $this->conversation, Thread::TYPE_NOTE, 'https://wordpress.org/plugins/my-plugin/', $this->agent, '2026-01-02 10:00:00' );

		\Eventy::action( 'conversation.status_changed', $this->conversation->fresh(), $this->agent, false, Conversation::STATUS_ACTIVE );

		$email = $this->payload()['email'];
		$this->assertSame( (int) $this->conversation->number, $email['number'] );
		$this->assertSame( '[WordPress Plugin Directory] Review in Progress: My Plugin', $email['subject'] );
		$this->assertSame( 'closed', $email['status'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', (string) $email['created_at'] );
		$this->assertSame( $this->conversation->customer_email, $email['sender']['email'] );
		$this->assertSame( array( 'Jane', 'Sender' ), array( $email['sender']['first_name'], $email['sender']['last_name'] ) );
		$this->assertSame( array(), $email['threads'] );
	}

	/**
	 * A reply carries the threads since the previous reply, newest first: itself, and the notes before it.
	 *
	 * @return void
	 */
	public function test_replies_carry_the_threads_since_the_previous_reply(): void {
		$this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, 'Please review', null, '2026-01-01 10:00:00' );
		$this->create_thread( $this->conversation, Thread::TYPE_NOTE, 'https://wordpress.org/plugins/my-plugin/', $this->agent, '2026-01-02 10:00:00' );
		$reply = $this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, 'Thanks', $this->agent, '2026-01-03 10:00:00' );

		\Eventy::action( 'conversation.user_replied', $this->conversation, $reply );

		$this->assertSame(
			array(
				array(
					'type' => 'message',
					'body' => 'Thanks',
				),
				array(
					'type' => 'note',
					'body' => 'https://wordpress.org/plugins/my-plugin/',
				),
			),
			$this->payload()['email']['threads']
		);
	}

	/**
	 * A note goes out with itself, for the plugins and themes it mentions.
	 *
	 * @return void
	 */
	public function test_notes_carry_themselves(): void {
		$this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, 'Please review', null, '2026-01-01 10:00:00' );
		$note = $this->create_thread( $this->conversation, Thread::TYPE_NOTE, 'https://wordpress.org/plugins/my-plugin/', $this->agent, '2026-01-02 10:00:00' );

		\Eventy::action( 'conversation.note_added', $this->conversation, $note );

		$this->assert_queued( 'conversation.note_added', (string) $this->agent->email );
		$this->assertSame( array( 'https://wordpress.org/plugins/my-plugin/' ), array_column( $this->payload()['email']['threads'], 'body' ) );
	}

	/**
	 * A reply's threads end with it, even when replies came after it before the event was sent.
	 *
	 * @return void
	 */
	public function test_late_replies_carry_their_own_threads(): void {
		$this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, 'Hello', $this->agent, '2026-01-01 10:00:00' );
		$reply = $this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, 'https://wordpress.org/plugins/my-plugin/', null, '2026-01-02 10:00:00' );

		\Eventy::action( 'conversation.customer_replied', $this->conversation, $reply );
		$this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, 'Thanks', $this->agent, '2026-01-03 10:00:00' );

		$this->assertSame( array( 'https://wordpress.org/plugins/my-plugin/' ), array_column( $this->payload()['email']['threads'], 'body' ) );
	}

	/**
	 * Events the filter keeps out of WordPress.org's copy, like an imported conversation's before WordPress.org points at
	 * it, still go out for stats, without the conversation.
	 *
	 * @return void
	 */
	public function test_events_kept_out_of_the_copy_still_count(): void {
		$filter = static function (): bool {
			return false;
		};
		\Eventy::addFilter( 'wporgwebhooks.copy', $filter );

		\Eventy::action( 'conversation.status_changed', $this->conversation, $this->agent, false, Conversation::STATUS_ACTIVE );
		$payload = $this->payload();
		\Eventy::removeFilter( 'wporgwebhooks.copy', $filter );

		$this->assertSame( (string) $this->agent->email, $payload['agent']['email'] );
		$this->assertArrayNotHasKey( 'email', $payload );
	}

	/**
	 * Every event of an imported conversation names the HelpScout conversation whose copy it replaces.
	 *
	 * @return void
	 */
	public function test_events_name_the_helpscout_conversation_they_replace(): void {
		$filter = static function (): int {
			return 1000000028;
		};
		\Eventy::addFilter( 'wporgwebhooks.helpscout_id', $filter );

		\Eventy::action( 'conversation.status_changed', $this->conversation, $this->agent, false, Conversation::STATUS_ACTIVE );
		$payload = $this->payload();
		\Eventy::removeFilter( 'wporgwebhooks.helpscout_id', $filter );

		$this->assertSame( 1000000028, $payload['helpscout_id'] );
	}

	/**
	 * A late event counts for the mailbox it happened in; the copy takes the one the conversation is in now.
	 *
	 * @return void
	 */
	public function test_late_events_keep_their_mailbox_for_stats(): void {
		\Eventy::action( 'conversation.status_changed', $this->conversation, $this->agent, false, Conversation::STATUS_ACTIVE );
		$this->conversation->mailbox_id = $this->create_mailbox( 'Themes' )->id;
		$this->conversation->save();

		$payload = $this->payload();

		$this->assertSame( 'Plugins', $payload['mailbox']['name'] );
		$this->assertSame( 'Themes', $payload['email']['mailbox']['name'] );
	}

	/**
	 * A conversation deleted for good before its event went out is deleted from the copy, with the HelpScout
	 * conversation it was imported from, which the filter is asked about by ID.
	 *
	 * @return void
	 */
	public function test_events_of_conversations_deleted_since_name_their_helpscout_conversation(): void {
		$id     = (int) $this->conversation->id;
		$filter = static function ( $helpscout_id = 0, $conversation = null, $conversation_id = 0 ) use ( $id ): int {
			return null === $conversation && $id === (int) $conversation_id ? 1000000028 : (int) $helpscout_id;
		};
		\Eventy::addFilter( 'wporgwebhooks.helpscout_id', $filter, 10, 3 );

		\Eventy::action( 'conversation.status_changed', $this->conversation, $this->agent, false, Conversation::STATUS_ACTIVE );
		Conversation::deleteConversationsForever( array( $id ) );
		$payload = $this->payload();
		\Eventy::removeFilter( 'wporgwebhooks.helpscout_id', $filter );

		$this->assertSame( array( 'state' => 'deleted' ), $payload['email'] );
		$this->assertSame( 1000000028, $payload['helpscout_id'] );
	}

	/**
	 * A merge names the HelpScout conversation the conversation merged away was imported from, whose copy goes too.
	 *
	 * @return void
	 */
	public function test_merges_name_the_helpscout_conversation_of_the_one_merged_away(): void {
		$second = $this->create_conversation( $this->conversation->mailbox, $this->create_sender() );
		$filter = static function ( $helpscout_id = 0, $conversation = null, $conversation_id = 0 ) use ( $second ): int {
			return (int) $second->id === (int) $conversation_id ? 1000000029 : (int) $helpscout_id;
		};
		\Eventy::addFilter( 'wporgwebhooks.helpscout_id', $filter, 10, 3 );

		\Eventy::action( 'conversation.merged', $this->conversation, $second, $this->agent );
		$payload = $this->payload();
		\Eventy::removeFilter( 'wporgwebhooks.helpscout_id', $filter );

		$this->assertSame( array( (int) $second->id, 1000000029 ), array( $payload['merged_id'], $payload['merged_helpscout_id'] ) );
		$this->assertArrayNotHasKey( 'helpscout_id', $payload );
	}

	/**
	 * Asked for all of the threads, an event carries them, and says so, whatever it is.
	 *
	 * @return void
	 */
	public function test_events_carry_all_threads_when_asked(): void {
		$this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, 'Please review', null, '2026-01-01 10:00:00' );
		$this->create_thread( $this->conversation, Thread::TYPE_NOTE, 'https://wordpress.org/plugins/my-plugin/', $this->agent, '2026-01-02 10:00:00' );

		\Eventy::action( 'conversation.status_changed', $this->conversation, $this->agent, false, Conversation::STATUS_ACTIVE );
		$job = Queue::pushed( SendEvent::class )->first();

		$this->assertFalse( EventPayload::refresh( $job->payload )['email']['all_threads'] );

		$email = EventPayload::refresh( array( 'all_threads' => true ) + $job->payload )['email'];
		$this->assertTrue( $email['all_threads'] );
		$this->assertSame( array( 'https://wordpress.org/plugins/my-plugin/', 'Please review' ), array_column( $email['threads'], 'body' ) );
	}

	/**
	 * Threads go as text, with a line for each block, and the addresses of their links, which is what WordPress.org
	 * looks for plugins and themes in.
	 *
	 * @return void
	 */
	public function test_threads_go_as_text(): void {
		$this->assertSame(
			"Hi Jane,\nSee your plugin and\nPlugin: my-plugin\nUse wp_enqueue script &\nhttps://wordpress.org/plugins/my-plugin/",
			EventPayload::text( '<style>p { color: red; }</style><p>Hi&nbsp;Jane,</p><p>See <a href="https://wordpress.org/plugins/my-plugin/">your plugin</a> and<br>Plugin: my-plugin</p><div>Use wp_enqueue &lt;script&gt; &amp;</div>' )
		);
	}

	/**
	 * A conversation's threads, sent as it moves, stop at a size WordPress.org takes, cutting the oldest short.
	 *
	 * @return void
	 */
	public function test_threads_stop_at_a_size(): void {
		$long = str_repeat( 'x', 100 * 1024 );
		foreach ( array( 1, 2, 3, 4 ) as $day ) {
			$this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, $long, null, '2026-01-0' . $day . ' 10:00:00' );
		}

		\Eventy::action( 'conversation.moved', $this->conversation, $this->agent );
		$threads = $this->payload()['email']['threads'];

		$this->assertCount( 3, $threads );
		$this->assertSame( array( 100 * 1024, 100 * 1024, 56 * 1024 ), array_map( 'strlen', array_column( $threads, 'body' ) ) );
	}

	/**
	 * Imported email isn't counted again: the service it came from counted it when it happened.
	 *
	 * @return void
	 */
	public function test_imported_threads_are_not_forwarded(): void {
		$sender = $this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, 'Question', null, '2026-01-01 10:00:00' );
		$reply  = $this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, 'Answer', $this->agent, '2026-01-01 11:00:00' );
		Thread::whereIn( 'id', array( $sender->id, $reply->id ) )->update( array( 'imported' => true ) );
		$sender->imported = true;
		$reply->imported  = true;

		\Eventy::action( 'conversation.created_by_customer', $this->conversation, $sender, null );
		\Eventy::action( 'conversation.customer_replied', $this->conversation, $sender, null );
		\Eventy::action( 'conversation.created_by_user', $this->conversation, $reply );
		\Eventy::action( 'conversation.user_replied', $this->conversation, $reply );

		Queue::assertNotPushed( SendEvent::class );
	}

	/**
	 * What agents do with an imported conversation in FreeScout counts.
	 *
	 * @return void
	 */
	public function test_agent_actions_on_imported_conversations_are_forwarded(): void {
		$this->conversation->imported = true;
		$this->conversation->save();

		\Eventy::action( 'conversation.status_changed', $this->conversation, $this->agent, false, Conversation::STATUS_ACTIVE );

		$this->assert_queued( 'conversation.status_changed', (string) $this->agent->email );
	}

	/**
	 * Automations, like Workflows, have no WordPress.org account to credit, so their events go out without an agent.
	 *
	 * @return void
	 */
	public function test_automations_are_not_credited(): void {
		$robot = $this->create_user();
		User::where( 'id', $robot->id )->update( array( 'type' => User::TYPE_ROBOT ) );
		$robot = $robot->fresh();

		\Eventy::action( 'conversation.status_changed', $this->conversation, $robot, false, Conversation::STATUS_ACTIVE );

		$reply = $this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, 'Automatic answer', $robot, '2026-09-01 11:00:00' );
		\Eventy::action( 'conversation.user_replied', $this->conversation, $reply );

		$this->assert_queued( 'conversation.status_changed', null );
		$this->assert_queued( 'conversation.user_replied', null );
	}

	/**
	 * Spam from senders isn't counted, but agents marking spam is.
	 *
	 * @return void
	 */
	public function test_spam_from_senders_is_not_forwarded(): void {
		$this->conversation->status = Conversation::STATUS_SPAM;
		$this->conversation->save();
		$thread = $this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, 'Buy now', null, '2026-09-01 10:00:00' );

		\Eventy::action( 'conversation.created_by_customer', $this->conversation, $thread, null );
		\Eventy::action( 'conversation.customer_replied', $this->conversation, $thread, null );
		Queue::assertNotPushed( SendEvent::class );

		\Eventy::action( 'conversation.status_changed', $this->conversation, $this->agent, false, Conversation::STATUS_ACTIVE );
		$this->assert_queued( 'conversation.status_changed', (string) $this->agent->email );
	}

	/**
	 * Without a secret nothing is queued.
	 *
	 * @return void
	 */
	public function test_nothing_is_queued_without_secret(): void {
		config( array( 'wporgwebhooks.secret' => '' ) );

		\Eventy::action( 'conversation.status_changed', $this->conversation, $this->agent, false, Conversation::STATUS_ACTIVE );

		Queue::assertNotPushed( SendEvent::class );
	}

	/**
	 * The payload of the one event queued.
	 *
	 * @return array
	 */
	private function payload(): array {
		$jobs = Queue::pushed( SendEvent::class );
		$this->assertCount( 1, $jobs );

		// As it's sent.
		return EventPayload::refresh( $jobs->first()->payload );
	}

	/**
	 * Asserts an event was queued for the conversation.
	 *
	 * @param string      $event       Expected event name.
	 * @param string|null $agent_email Expected agent email, or null for none.
	 * @return void
	 */
	private function assert_queued( string $event, ?string $agent_email ): void {
		Queue::assertPushed(
			SendEvent::class,
			function ( SendEvent $job ) use ( $event, $agent_email ): bool {
				return $event === $job->payload['event']
					&& (int) $this->conversation->id === $job->payload['conversation']['id']
					&& 'Plugins' === $job->payload['mailbox']['name']
					&& ( $job->payload['agent']['email'] ?? null ) === $agent_email;
			}
		);
	}
}
