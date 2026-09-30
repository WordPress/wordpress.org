<?php
/**
 * Tests for the sidebar conversation payload.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Tests;

use App\Attachment;
use App\Conversation;
use App\Thread;
use App\User;
use Modules\WPOrgSidebar\Services\ConversationPayload;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Pins the payload fields api.wordpress.org/dotorg/freescout/ reads.
 */
final class ConversationPayloadTest extends TestCase {

	/**
	 * Agent replying in the conversation.
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
	 * Sets up a conversation in the Plugins mailbox.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->agent        = $this->create_user();
		$this->conversation = $this->create_conversation( $this->create_mailbox( 'Plugins' ), $this->create_sender( 'sender@example.org' ) );
	}

	/**
	 * Sender and mailbox are where the endpoints look for them.
	 *
	 * @return void
	 */
	public function test_includes_sender_and_mailbox(): void {
		$payload = ConversationPayload::build( $this->conversation );

		$this->assertSame( 'sender@example.org', $payload['sender']['email'] );
		$this->assertSame( array( 'sender@example.org' ), $payload['sender']['emails'] );
		$this->assertSame( 'Jane', $payload['sender']['first_name'] );
		$this->assertSame( 'Plugins', $payload['mailbox']['name'] );
		$this->assertSame( (int) $this->conversation->id, $payload['conversation']['id'] );
		$this->assertSame( (string) $this->conversation->subject, $payload['conversation']['subject'] );
		$this->assertSame( 'active', $payload['conversation']['status'] );
	}

	/**
	 * Threads are newest first, with their type and author.
	 *
	 * @return void
	 */
	public function test_threads_are_newest_first(): void {
		$this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, 'Question', null, '2026-09-01 10:00:00' );
		$this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, 'Answer', $this->agent, '2026-09-01 11:00:00' );
		$this->create_thread( $this->conversation, Thread::TYPE_NOTE, 'Note', $this->agent, '2026-09-01 12:00:00' );

		$threads = ConversationPayload::build( $this->conversation )['threads'];

		$this->assertSame( array( 'note', 'message', 'customer' ), array_column( $threads, 'type' ) );
		$this->assertSame( 'user', $threads[1]['created_by']['type'] );
		$this->assertSame( (string) $this->agent->email, $threads[1]['created_by']['email'] );
		$this->assertSame( 'customer', $threads[2]['created_by']['type'] );
		$this->assertSame( '2026-09-01T10:00:00Z', $threads[2]['created_at'] );
	}

	/**
	 * Unpublished threads, like drafts, stay private.
	 *
	 * @return void
	 */
	public function test_excludes_drafts(): void {
		$draft        = $this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, 'Draft', $this->agent, '2026-09-01 11:00:00' );
		$draft->state = Thread::STATE_DRAFT;
		$draft->save();

		$this->assertSame( array(), ConversationPayload::build( $this->conversation )['threads'] );
	}

	/**
	 * Small text attachments carry their content, which bounce detection reads.
	 *
	 * @return void
	 */
	public function test_includes_text_attachment_content_only(): void {
		$thread = $this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, 'Bounce', null, '2026-09-01 10:00:00' );
		Attachment::create( 'details.txt', 'text/plain', Attachment::TYPE_TEXT, 'Final-Recipient: rfc822; author@example.org', null, false, $thread->id );
		Attachment::create( 'logo.png', 'image/png', Attachment::TYPE_IMAGE, 'not really a png', null, false, $thread->id );

		$attachments = ConversationPayload::build( $this->conversation )['threads'][0]['attachments'];
		$by_name     = array_column( $attachments, 'content', 'file_name' );

		$this->assertSame( 'Final-Recipient: rfc822; author@example.org', $by_name['details.txt'] );
		$this->assertNull( $by_name['logo.png'] );
	}
}
