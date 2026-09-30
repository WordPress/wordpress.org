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
use Modules\WPOrgWebhooks\Jobs\SendEvent;
use Modules\WPOrgWebhooks\Providers\WPOrgWebhooksServiceProvider;
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
