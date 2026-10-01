<?php
/**
 * Tests for importing one conversation.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use App\Attachment;
use App\Conversation;
use App\Folder;
use App\Thread;
use App\User;
use Illuminate\Support\Facades\Queue;
use Modules\WPOrgHelpScoutImport\Entities\Agent;
use Modules\WPOrgHelpScoutImport\Entities\ImportedConversation;
use Modules\WPOrgHelpScoutImport\Entities\ImportedThread;
use Modules\WPOrgHelpScoutImport\Entities\Person;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\Importer;
use Modules\WPOrgHelpScoutImport\Services\People;

require_once __DIR__ . '/ImportTestCase.php';

/**
 * Covers what a conversation becomes, and importing it again.
 */
final class ImporterTest extends ImportTestCase {

	/**
	 * Importer under test.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * Creates the importer.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->importer = new Importer( app( HelpScout::class ), new People() );
	}

	/**
	 * The conversation keeps HelpScout's status, dates, closer, sender, and subject, and is marked imported.
	 *
	 * @return void
	 */
	public function test_conversation_is_imported_as_it_was(): void {
		$this->assertSame( Importer::IMPORTED, $this->importer->import( $this->conversation(), $this->mailbox ) );

		$conversation = $this->imported_conversation();
		$this->assertTrue( (bool) $conversation->imported );
		$this->assertSame( 'My photo was rejected', $conversation->subject );
		$this->assertSame( Conversation::STATUS_CLOSED, (int) $conversation->status );
		$this->assertSame( (int) $this->mailbox->id, (int) $conversation->mailbox_id );
		$this->assertSame( 'sam@example.org', $conversation->customer_email );
		$this->assertSame( 'Sam', $conversation->customer->first_name );
		$this->assertSame( (int) $this->agent->id, (int) $conversation->closed_by_user_id );
		$this->assertSame( '2026-09-01 09:00:00', $conversation->created_at->setTimezone( 'UTC' )->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( '2026-09-02 10:00:00', $conversation->closed_at->setTimezone( 'UTC' )->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( '2026-09-02 10:00:00', $conversation->updated_at->setTimezone( 'UTC' )->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( Folder::TYPE_CLOSED, (int) $conversation->folder->type );
		$this->assertTrue( (bool) $conversation->read_by_user );
	}

	/**
	 * Threads keep their authors, recipients, and dates; line items are left out.
	 *
	 * @return void
	 */
	public function test_threads_are_imported_with_their_authors(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );

		$threads = $this->imported_conversation()->threads()->orderBy( 'created_at' )->get();
		$this->assertSame( array( Thread::TYPE_CUSTOMER, Thread::TYPE_NOTE, Thread::TYPE_MESSAGE ), $threads->pluck( 'type' )->map( 'intval' )->all() );

		list( $email, $note, $reply ) = $threads->all();
		$this->assertSame( 'sam@example.org', $email->from );
		$this->assertSame( array( 'friend@example.org' ), $email->getCcArray() );
		$this->assertSame( '2026-09-01 09:00:00', $email->created_at->setTimezone( 'UTC' )->format( 'Y-m-d H:i:s' ) );
		$this->assertTrue( (bool) $email->first );
		$this->assertTrue( (bool) $email->imported );
		$this->assertSame( (int) $this->agent->id, (int) $reply->created_by_user_id );
		$this->assertSame( (int) $this->agent->id, (int) $note->created_by_user_id );
		$this->assertSame( '<p>It was blurry.</p>', $reply->body );
	}

	/**
	 * What the conversation list shows comes from the threads, not from when they were imported.
	 *
	 * @return void
	 */
	public function test_last_reply_and_preview_come_from_the_threads(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );

		$conversation = $this->imported_conversation();
		$this->assertSame( 2, (int) $conversation->threads_count );
		$this->assertSame( '2026-09-02 10:00:00', $conversation->last_reply_at->setTimezone( 'UTC' )->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( Conversation::PERSON_USER, (int) $conversation->last_reply_from );
		$this->assertSame( 'It was blurry.', $conversation->preview );
		$this->assertTrue( (bool) $conversation->has_attachments );
	}

	/**
	 * Emails keep their Message-ID, so replies to them find the conversation.
	 *
	 * @return void
	 */
	public function test_emails_keep_their_message_id(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );

		$message_ids = $this->imported_conversation()->threads()->orderBy( 'created_at' )->pluck( 'message_id' )->all();
		$this->assertSame( array( 'thread-2001@mail.example.org', null, 'thread-2002@mail.example.org' ), $message_ids );
		$this->assertCount( 0, $this->helpscout->requests_to( 'v2/conversations/1001/threads/2003/original-source' ) );
	}

	/**
	 * Attachments are copied from HelpScout.
	 *
	 * @return void
	 */
	public function test_attachments_are_copied(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );

		$email      = $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_CUSTOMER )->first();
		$attachment = Attachment::query()->where( 'thread_id', $email->id )->first();

		$this->assertNotNull( $attachment );
		$this->assertSame( 'photo.jpg', $attachment->file_name );
		$this->assertSame( 'jpeg bytes', $attachment->getFileContents() );
		$this->assertTrue( (bool) $email->has_attachments );
	}

	/**
	 * Images HelpScout hosts are copied and linked from the body; others keep their links, and its tracker goes.
	 *
	 * @return void
	 */
	public function test_images_helpscout_hosts_are_copied(): void {
		$hosted  = 'https://d33v4339jhl8k0.cloudfront.net/inline/83653/abc/def/image.png';
		$missing = 'https://d33v4339jhl8k0.cloudfront.net/inline/83653/abc/gone/lost.png';
		$page    = 'https://d33v4339jhl8k0.cloudfront.net/inline/83653/abc/page/error.png';
		$other   = 'https://s.w.org/images/core/emoji/14.0.0/72x72/1f389.png';
		$tracker = 'https://secure.helpscout.net/notification/convo/1/2/3.png';

		$threads            = $this->threads();
		$threads[0]['body'] = '<p>Look:</p><img src="' . $hosted . '" alt="screenshot"><img src="' . $missing . '"><img src="' . $page . '"><img src="' . $other . '"><img src="' . $tracker . '" width="1">';
		$this->answer_threads( self::CONVERSATION_ID, $threads );
		$this->helpscout->on( 'GET', 'inline/83653/abc/def/image.png', new \GuzzleHttp\Psr7\Response( 200, array( 'Content-Type' => 'application/octet-stream' ), self::png() ) );
		$this->helpscout->on( 'GET', 'inline/83653/abc/gone/lost.png', new \GuzzleHttp\Psr7\Response( 404, array(), self::png() ) );
		$this->helpscout->on( 'GET', 'inline/83653/abc/page/error.png', new \GuzzleHttp\Psr7\Response( 200, array(), '<html>Not an image</html>' ) );

		$this->importer->import( $this->conversation(), $this->mailbox );

		$email = $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_CUSTOMER )->first();
		$image = Attachment::query()->where( 'thread_id', $email->id )->where( 'embedded', true )->first();

		$this->assertNotNull( $image );
		$this->assertSame( 'image.png', $image->file_name );
		$this->assertSame( 'image/png', $image->mime_type );
		$this->assertSame( self::png(), $image->getFileContents() );
		$this->assertStringContainsString( '<img src="' . $image->url() . '" alt="screenshot">', $email->body );
		$this->assertStringNotContainsString( $hosted, $email->body );
		$this->assertStringContainsString( $missing, $email->body );
		$this->assertStringContainsString( $page, $email->body );
		$this->assertSame( 1, Attachment::query()->where( 'thread_id', $email->id )->where( 'embedded', true )->count() );
		$this->assertStringContainsString( $other, $email->body );
		$this->assertStringNotContainsString( 'secure.helpscout.net', $email->body );
		$this->assertCount( 0, $this->helpscout->requests_to( 'images/core/emoji/14.0.0/72x72/1f389.png' ) );
		$this->assertSame( 1, Attachment::query()->where( 'thread_id', $email->id )->where( 'embedded', false )->count() );
	}

	/**
	 * Nothing is sent, and nothing reacts as if the email were new.
	 *
	 * @return void
	 */
	public function test_nothing_reacts_to_the_import(): void {
		Queue::fake();
		$fired = array();
		foreach ( array( 'conversation.created_by_customer', 'conversation.user_replied', 'thread.created' ) as $hook ) {
			\Eventy::addAction(
				$hook,
				static function () use ( $hook, &$fired ): void {
					$fired[] = $hook;
				}
			);
		}

		$this->importer->import( $this->conversation(), $this->mailbox );

		$this->assertSame( array(), $fired );
		Queue::assertNothingPushed();
	}

	/**
	 * HelpScout users without a FreeScout user are credited to a disabled robot, and their name is kept.
	 *
	 * @return void
	 */
	public function test_unknown_agent_is_credited_to_the_robot(): void {
		$this->agent->email = 'someone-else@example.org';
		$this->agent->save();

		$this->importer->import( $this->conversation(), $this->mailbox );

		$robot = User::query()->where( 'email', People::ROBOT_EMAIL )->firstOrFail();
		$this->assertSame( User::TYPE_ROBOT, (int) $robot->type );
		$this->assertSame( User::STATUS_DISABLED, (int) $robot->status );

		$reply = $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_MESSAGE )->first();
		$this->assertSame( (int) $robot->id, (int) $reply->created_by_user_id );
		$this->assertSame( array( 'author' => 'Ada Agent' ), $reply->getMeta( Importer::META ) );
		$this->assertNull( $this->imported_conversation()->closed_by_user_id );
	}

	/**
	 * A thread from someone else keeps its own sender; the conversation keeps its own.
	 *
	 * @return void
	 */
	public function test_other_senders_keep_their_threads(): void {
		$threads   = $this->threads();
		$other     = self::sender( 'pat@example.org', 'Pat', 'Other' );
		$threads[] = array_replace(
			$threads[0],
			array(
				'id'        => 2005,
				'customer'  => $other,
				'createdBy' => $other,
				'createdAt' => '2026-09-03T08:00:00Z',
				'_embedded' => array(),
			)
		);
		$this->answer_threads( self::CONVERSATION_ID, $threads );

		$this->importer->import( $this->conversation(), $this->mailbox );

		$conversation = $this->imported_conversation();
		$last         = $conversation->threads()->orderByDesc( 'created_at' )->first();
		$this->assertSame( 'pat@example.org', $last->from );
		$this->assertSame( 'pat@example.org', $last->created_by_customer->getMainEmail() );
		$this->assertSame( 'sam@example.org', $conversation->customer_email );
	}

	/**
	 * Importing again adds only new threads, and takes HelpScout's current status and assignee.
	 *
	 * @return void
	 */
	public function test_importing_again_adds_only_whats_new(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );

		$threads   = $this->threads();
		$threads[] = array_replace(
			$threads[0],
			array(
				'id'        => 2006,
				'body'      => 'One more question.',
				'createdAt' => '2026-09-05T08:00:00Z',
				'_embedded' => array(),
			)
		);
		$this->answer_threads( self::CONVERSATION_ID, $threads );

		$result = $this->importer->import(
			$this->conversation(
				array(
					'status'        => 'active',
					'assignee'      => self::agent_person(),
					'userUpdatedAt' => '2026-09-05T08:00:00Z',
				)
			),
			$this->mailbox
		);

		$this->assertSame( Importer::UPDATED, $result );
		$this->assertSame( 1, Conversation::query()->where( 'mailbox_id', $this->mailbox->id )->count() );

		$conversation = $this->imported_conversation();
		$this->assertSame( 4, $conversation->threads()->count() );
		$this->assertSame( Conversation::STATUS_ACTIVE, (int) $conversation->status );
		$this->assertSame( (int) $this->agent->id, (int) $conversation->user_id );
		$this->assertNull( $conversation->closed_at );
		$this->assertSame( Folder::TYPE_ASSIGNED, (int) $conversation->folder->type );
		$this->assertSame( 'One more question.', $conversation->preview );
		$this->assertSame( 1, Attachment::query()->whereIn( 'thread_id', $conversation->threads()->pluck( 'id' ) )->count() );
	}

	/**
	 * Only HelpScout users are matched to FreeScout users, even if a sender has an agent's email.
	 *
	 * @return void
	 */
	public function test_only_helpscout_users_are_matched_to_users(): void {
		$people = new People();

		$this->assertSame( (int) $this->agent->id, (int) $people->user( self::agent_person() )->id );
		$this->assertNull( $people->user( array( 'type' => 'customer' ) + self::agent_person() ) );
		$this->assertNull( $people->user( array( 'type' => 'team' ) + self::agent_person() ) );
	}

	/**
	 * The conversation keeps HelpScout's number, and the next number set for live email stays set.
	 *
	 * @return void
	 */
	public function test_conversation_keeps_helpscouts_number(): void {
		\Option::set( 'next_ticket', 2000000 );

		$this->importer->import( $this->conversation( array( 'number' => 1126167 ) ), $this->mailbox );

		$this->assertSame( 1126167, $this->stored_number() );
		$this->assertSame( 2000000, (int) \Option::get( 'next_ticket', 0, true, false ) );
	}

	/**
	 * A number a FreeScout conversation already has isn't given out twice.
	 *
	 * @return void
	 */
	public function test_number_taken_in_freescout_is_not_reused(): void {
		$live         = $this->create_conversation( $this->create_mailbox( 'Live' ), $this->create_sender() );
		$live->number = 1126167;
		$live->save();

		$this->importer->import( $this->conversation( array( 'number' => 1126167 ) ), $this->mailbox );

		$this->assertNotSame( 1126167, $this->stored_number() );
		$this->assertSame( 1, Conversation::query()->where( 'number', 1126167 )->count() );
	}

	/**
	 * A FreeScout user an administrator chose is credited, even over one with the same email.
	 *
	 * @return void
	 */
	public function test_chosen_user_is_credited(): void {
		$chosen = factory( User::class )->create( array( 'email' => 'ada@wordpress.example' ) );
		Agent::query()->create(
			array(
				'helpscout_user_id' => 55,
				'user_id'           => $chosen->id,
			)
		);

		$this->importer->import( $this->conversation(), $this->mailbox );

		$reply = $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_MESSAGE )->first();
		$this->assertSame( (int) $chosen->id, (int) $reply->created_by_user_id );
		$this->assertSame( (int) $chosen->id, (int) $this->imported_conversation()->closed_by_user_id );
	}

	/**
	 * Who wrote each reply and note is kept, with who they are, for crediting them again later.
	 *
	 * @return void
	 */
	public function test_authors_are_remembered(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );

		$this->assertSame( 55, (int) ImportedThread::query()->where( 'helpscout_id', 2002 )->value( 'helpscout_user_id' ) );
		$this->assertNull( ImportedThread::query()->where( 'helpscout_id', 2001 )->value( 'helpscout_user_id' ) );

		$person = Person::query()->where( 'helpscout_user_id', 55 )->firstOrFail();
		$this->assertSame( array( 'Ada', 'Agent', 'agent@example.org' ), array( $person->first_name, $person->last_name, $person->email ) );
	}

	/**
	 * HelpScout's number, tags, and custom fields are kept for later.
	 *
	 * @return void
	 */
	public function test_number_tags_and_custom_fields_are_kept(): void {
		$fields = array(
			array(
				'id'    => 7,
				'name'  => 'WordPress.org username',
				'value' => 'sam',
			),
		);

		$this->importer->import( $this->conversation( array( 'customFields' => $fields ) ), $this->mailbox );

		$imported = ImportedConversation::query()->where( 'helpscout_id', self::CONVERSATION_ID )->firstOrFail();
		$this->assertSame( 501, (int) $imported->helpscout_number );
		$this->assertSame( array( 'photos' ), json_decode( (string) $imported->tags, true ) );
		$this->assertSame( $fields, json_decode( (string) $imported->custom_fields, true ) );
	}

	/**
	 * Spam, drafts, and conversations without a sender's email are left out.
	 *
	 * @return void
	 */
	public function test_spam_drafts_and_senderless_are_skipped(): void {
		$this->assertSame( Importer::SKIPPED, $this->importer->import( $this->conversation( array( 'status' => 'spam' ) ), $this->mailbox ) );
		$this->assertSame( Importer::SKIPPED, $this->importer->import( $this->conversation( array( 'state' => 'draft' ) ), $this->mailbox ) );
		$this->assertSame( Importer::SKIPPED, $this->importer->import( $this->conversation( array( 'primaryCustomer' => array( 'type' => 'customer' ) ) ), $this->mailbox ) );

		$this->assertSame( 0, Conversation::query()->where( 'mailbox_id', $this->mailbox->id )->count() );
	}

	/**
	 * A conversation deleted in FreeScout since it was imported stays deleted, even when HelpScout has more of it.
	 *
	 * @return void
	 */
	public function test_conversation_deleted_since_stays_deleted(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );
		$this->imported_conversation()->delete();

		$threads   = $this->threads();
		$threads[] = array_replace(
			$threads[0],
			array(
				'id'        => 2007,
				'_embedded' => array(),
			)
		);
		$this->answer_threads( self::CONVERSATION_ID, $threads );

		$this->assertSame( Importer::SKIPPED, $this->importer->import( $this->conversation(), $this->mailbox ) );
		$this->assertSame( 0, Conversation::query()->where( 'mailbox_id', $this->mailbox->id )->count() );
	}

	/**
	 * A failure while writing leaves nothing behind, so importing again starts clean.
	 *
	 * @return void
	 */
	public function test_failure_leaves_nothing_half_imported(): void {
		$threads                 = $this->threads();
		$threads[1]['_embedded'] = array( 'attachments' => array( array( 'id' => 3002 ) ) );
		$this->answer_threads( self::CONVERSATION_ID, $threads );
		$this->helpscout->only( 'GET', 'v2/conversations/1001/attachments/3002/data', array( 'data' => 'not base64!' ) );

		try {
			$this->importer->import( $this->conversation(), $this->mailbox );
			$this->fail( 'The import should have failed.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( '3002', $e->getMessage() );
		}

		$this->assertSame( 0, Conversation::query()->where( 'mailbox_id', $this->mailbox->id )->count() );
		$this->assertSame( 0, ImportedConversation::query()->count() );
	}

	/**
	 * The imported conversation's number as stored; core shows its ID instead while custom numbers are off.
	 *
	 * @return int
	 */
	private function stored_number(): int {
		return (int) Conversation::query()->whereKey( $this->imported_conversation()->id )->toBase()->value( 'number' );
	}

	/**
	 * A 1×1 PNG.
	 *
	 * @return string
	 */
	private static function png(): string {
		return (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGD4DwABBAEAwS2OUAAAAABJRU5ErkJggg==' );
	}

	/**
	 * The conversation imported from HelpScout's.
	 *
	 * @return Conversation
	 */
	private function imported_conversation(): Conversation {
		$imported = ImportedConversation::query()->where( 'helpscout_id', self::CONVERSATION_ID )->firstOrFail();

		return Conversation::query()->findOrFail( $imported->conversation_id );
	}
}
