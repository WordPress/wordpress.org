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
use Illuminate\Support\Facades\Storage;
use Modules\WPOrgHelpScoutImport\Entities\Agent;
use Modules\WPOrgHelpScoutImport\Entities\ImportedConversation;
use Modules\WPOrgHelpScoutImport\Entities\ImportedThread;
use Modules\WPOrgHelpScoutImport\Entities\Person;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\Importer;
use Modules\WPOrgHelpScoutImport\Services\People;
use Modules\WPOrgHelpScoutImport\Tests\Support\FakeHelpScout;

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

		$this->importer = new Importer( app( HelpScout::class ), new People( app( HelpScout::class ) ) );
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
	 * New senders, with an email or without, aren't announced, like to webhooks; senders FreeScout has are reused.
	 *
	 * @return void
	 */
	public function test_new_senders_arent_announced(): void {
		$created = array();
		\Eventy::addAction(
			'customer.created',
			static function ( $customer ) use ( &$created ): void {
				$created[] = $customer->id;
			}
		);
		$existing = $this->create_sender( 'friend@example.org' );
		$people   = new People();

		$this->importer->import( $this->conversation(), $this->mailbox );
		$caller = $people->sender(
			array(
				'id'    => 4244,
				'type'  => 'customer',
				'first' => 'Cal',
				'last'  => 'Ler',
			)
		);
		$friend = $people->sender( self::sender( 'Friend@Example.org.', 'Fri', 'End' ) );

		$this->assertSame( array(), $created );
		$sender = $this->imported_conversation()->customer;
		$this->assertSame( 'Sam', $sender->first_name );
		$this->assertSame( (int) $sender->id, (int) \App\Email::query()->where( 'email', 'sam@example.org' )->value( 'customer_id' ) );
		$this->assertSame( 'Cal', $caller->first_name );
		$this->assertNotNull( $caller->id );
		$this->assertSame( (int) $existing->id, (int) $friend->id );
	}

	/**
	 * A HelpScout user without a FreeScout user gets one, which can log in while HelpScout lists them.
	 *
	 * @return void
	 */
	public function test_helpscout_user_without_freescout_user_gets_one(): void {
		$this->agent->email = 'ada@wordpress.example';
		$this->agent->save();

		$this->importer->import( $this->conversation(), $this->mailbox );

		$user = User::query()->where( 'email', 'agent@example.org' )->firstOrFail();
		$this->assertSame( 'Ada Agent', $user->getFullName() );
		$this->assertSame( User::STATUS_ACTIVE, (int) $user->status );
		$this->assertSame( User::ROLE_USER, (int) $user->role );
		$this->assertSame( 'Europe/Berlin', $user->timezone );

		$reply = $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_MESSAGE )->first();
		$this->assertSame( (int) $user->id, (int) $reply->created_by_user_id );
		$this->assertSame( (int) $user->id, (int) $this->imported_conversation()->closed_by_user_id );
		$this->assertSame( (int) $user->id, (int) Agent::query()->where( 'helpscout_user_id', 55 )->value( 'user_id' ) );
	}

	/**
	 * Someone HelpScout no longer lists gets a disabled user, and their email is never a mailbox's.
	 *
	 * @return void
	 */
	public function test_user_helpscout_no_longer_lists_gets_a_disabled_one(): void {
		$shared        = $this->create_mailbox( 'Shared' );
		$shared->email = 'shared@example.org';
		$shared->save();
		$former                  = array(
			'id'    => 66,
			'type'  => 'user',
			'email' => 'shared@example.org',
			'first' => 'Fay',
			'last'  => 'Former',
		);
		$threads                 = $this->threads();
		$threads[2]['createdBy'] = $former;
		$this->answer_threads( self::CONVERSATION_ID, $threads );

		$this->importer->import( $this->conversation(), $this->mailbox );

		$note = $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_NOTE )->first();
		$user = User::query()->findOrFail( $note->created_by_user_id );
		$this->assertSame( 'Fay Former', $user->getFullName() );
		$this->assertSame( User::STATUS_DISABLED, (int) $user->status );
		$this->assertSame( 'helpscout-66@helpscout.invalid', $user->email );
		$this->assertSame( 0, $user->mailboxes()->count() );
	}

	/**
	 * What HelpScout itself did, without a user, is credited to a disabled robot.
	 *
	 * @return void
	 */
	public function test_helpscouts_own_notes_are_credited_to_the_robot(): void {
		$threads                 = $this->threads();
		$threads[2]['createdBy'] = array(
			'id'   => 0,
			'type' => 'user',
		);
		$this->answer_threads( self::CONVERSATION_ID, $threads );

		$this->importer->import( $this->conversation(), $this->mailbox );

		$robot = User::query()->where( 'email', People::ROBOT_EMAIL )->firstOrFail();
		$this->assertSame( User::TYPE_ROBOT, (int) $robot->type );
		$this->assertSame( User::STATUS_DISABLED, (int) $robot->status );

		$note = $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_NOTE )->first();
		$this->assertSame( (int) $robot->id, (int) $note->created_by_user_id );
	}

	/**
	 * Conversations assigned to a HelpScout team are assigned to the FreeScout team with its name.
	 *
	 * @return void
	 */
	public function test_team_assignments_go_to_freescout_teams(): void {
		\App\Module::clearModulesCache();
		\App\Module::setActive( People::TEAMS_MODULE, true );
		\App\Module::clearModulesCache();
		$team           = factory( User::class )->create(
			array(
				'first_name' => 'Photo',
				'last_name'  => 'Moderators',
				'email'      => 'team-1@example.org',
				'type'       => User::TYPE_ROBOT,
			)
		);
		$helpscout_team = array(
			'id'    => 90,
			'type'  => 'team',
			'first' => 'Photo Moderators',
			'last'  => '',
		);

		$this->importer->import(
			$this->conversation(
				array(
					'status'   => 'active',
					'assignee' => $helpscout_team,
				)
			),
			$this->mailbox
		);

		$this->assertSame( (int) $team->id, (int) $this->imported_conversation()->user_id );

		// Without a team of that name, it's unassigned.
		$this->assertNull(
			( new People() )->assignee(
				array(
					'id'    => 91,
					'type'  => 'team',
					'first' => 'Legal',
				)
			)
		);
	}

	/**
	 * An email FreeScout has in another conversation keeps its Message-ID there; one it has in this one isn't added again.
	 *
	 * @return void
	 */
	public function test_emails_freescout_has_already_keep_their_message_id(): void {
		$other = $this->create_conversation( $this->create_mailbox( 'Themes' ), $this->create_sender() );
		$this->with_message_id( $this->create_thread( $other, Thread::TYPE_CUSTOMER, 'Hello', null, '2026-09-01 09:00:00' ), 'thread-2001@mail.example.org' );

		$this->importer->import( $this->conversation(), $this->mailbox );

		$email = $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_CUSTOMER )->first();
		$this->assertNull( $email->message_id );
		$this->assertSame( 1, Thread::query()->where( 'message_id', 'thread-2001@mail.example.org' )->count() );

		// FreeScout fetched the next email itself, after the mailbox moved.
		$conversation = $this->imported_conversation();
		$fetched      = $this->with_message_id( $this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Fetched', null, '2026-09-06 08:00:00' ), 'thread-2008@mail.example.org' );
		$threads      = $this->threads();
		$threads[]    = array_replace(
			$threads[0],
			array(
				'id'        => 2008,
				'createdAt' => '2026-09-06T08:00:00Z',
				'_embedded' => array(),
			)
		);
		$this->answer_threads( self::CONVERSATION_ID, $threads );

		$this->importer->import( $this->conversation(), $this->mailbox );

		$this->assertSame( 1, Thread::query()->where( 'message_id', 'thread-2008@mail.example.org' )->count() );
		$this->assertSame( (int) $fetched->id, (int) ImportedThread::query()->where( 'helpscout_id', 2008 )->value( 'thread_id' ) );
	}

	/**
	 * Once agents worked on a conversation in FreeScout, importing it again adds HelpScout's new threads only.
	 *
	 * @return void
	 */
	public function test_conversation_worked_on_in_freescout_keeps_its_status(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );
		$this->create_thread( $this->imported_conversation(), Thread::TYPE_NOTE, 'On it.', $this->agent, '2026-09-10 08:00:00' );

		$threads   = $this->threads();
		$threads[] = array_replace(
			$threads[0],
			array(
				'id'        => 2009,
				'body'      => 'Still there?',
				'createdAt' => '2026-09-05T08:00:00Z',
				'_embedded' => array(),
			)
		);
		$this->answer_threads( self::CONVERSATION_ID, $threads );

		$this->assertSame( Importer::UPDATED, $this->importer->import( $this->conversation( array( 'status' => 'active' ) ), $this->mailbox ) );

		$conversation = $this->imported_conversation();
		$this->assertSame( Conversation::STATUS_CLOSED, (int) $conversation->status );
		$this->assertSame( 1, $conversation->threads()->where( 'body', 'Still there?' )->count() );
	}

	/**
	 * Attachments HelpScout found a virus in aren't downloaded; the rest of the thread is imported.
	 *
	 * @return void
	 */
	public function test_infected_attachments_are_left_out(): void {
		$threads = $this->threads();
		$threads[0]['_embedded']['attachments'][0]['state'] = 'virus';
		$this->answer_threads( self::CONVERSATION_ID, $threads );

		$this->assertSame( Importer::IMPORTED, $this->importer->import( $this->conversation(), $this->mailbox ) );

		$this->assertCount( 0, $this->helpscout->requests_to( 'v2/conversations/1001/attachments/3001/file' ) );
		$this->assertSame( 3, $this->imported_conversation()->threads()->count() );
	}

	/**
	 * A reply hidden from what the sender is sent, or that bounced, stays visible to agents, as a note; drafts don't.
	 *
	 * @return void
	 */
	public function test_hidden_and_bounced_replies_become_notes(): void {
		$threads             = $this->threads();
		$threads[1]['state'] = 'hidden';
		$threads[]           = array_replace(
			$threads[1],
			array(
				'id'    => 2010,
				'state' => 'draft',
			)
		);
		$threads[]           = array_replace(
			$threads[1],
			array(
				'id'        => 2011,
				'state'     => 'bounced',
				'createdAt' => '2026-09-03T10:00:00Z',
			)
		);
		$this->answer_threads( self::CONVERSATION_ID, $threads );

		$this->importer->import( $this->conversation(), $this->mailbox );

		$types = $this->imported_conversation()->threads()->orderBy( 'created_at' )->orderBy( 'id' )->pluck( 'type' )->map( 'intval' )->all();
		$this->assertSame( array( Thread::TYPE_CUSTOMER, Thread::TYPE_NOTE, Thread::TYPE_NOTE, Thread::TYPE_NOTE ), $types );
		$this->assertNull( ImportedThread::query()->where( 'helpscout_id', 2010 )->first() );
	}

	/**
	 * PDFs are attached too, and checked for scripts like core checks uploads.
	 *
	 * @return void
	 */
	public function test_pdf_attachments_are_imported(): void {
		$threads                                   = $this->threads();
		$threads[0]['_embedded']['attachments'][0] = array(
			'id'       => 3001,
			'filename' => 'report.pdf',
			'mimeType' => 'application/pdf',
		);
		$this->answer_threads( self::CONVERSATION_ID, $threads );
		$this->helpscout->only( 'GET', 'v2/conversations/1001/attachments/3001/file', new \GuzzleHttp\Psr7\Response( 200, array(), "%PDF-1.4\n/JavaScript (app.alert(1))" ) );

		$this->assertSame( Importer::IMPORTED, $this->importer->import( $this->conversation(), $this->mailbox ) );

		$email      = $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_CUSTOMER )->first();
		$attachment = Attachment::query()->where( 'thread_id', $email->id )->firstOrFail();
		$this->assertSame( 'report.pdf_', $attachment->file_name );
		$this->assertSame( "%PDF-1.4\n/JavaScript (app.alert(1))", $attachment->getFileContents() );
	}

	/**
	 * A conversation HelpScout moved to another mailbox moves too; once agents worked on it, it only gets new threads.
	 *
	 * @return void
	 */
	public function test_conversation_helpscout_moved_moves_too(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );
		$themes = $this->create_mailbox( 'Themes' );

		$this->assertSame( Importer::UPDATED, $this->importer->import( $this->conversation(), $themes ) );
		$this->assertSame( (int) $themes->id, (int) $this->imported_conversation()->mailbox_id );
		$this->assertSame( (int) $themes->id, (int) $this->imported_conversation()->folder->mailbox_id );

		$this->create_thread( $this->imported_conversation(), Thread::TYPE_NOTE, 'On it.', $this->agent, '2026-09-10 08:00:00' );
		$threads   = $this->threads();
		$threads[] = array_replace(
			$threads[0],
			array(
				'id'        => 2012,
				'body'      => 'Any news?',
				'createdAt' => '2026-09-11T08:00:00Z',
				'_embedded' => array(),
			)
		);
		$this->answer_threads( self::CONVERSATION_ID, $threads );

		$this->assertSame( Importer::UPDATED, $this->importer->import( $this->conversation(), $this->mailbox ) );
		$this->assertSame( (int) $themes->id, (int) $this->imported_conversation()->mailbox_id );
		$this->assertSame( 1, $this->imported_conversation()->threads()->where( 'body', 'Any news?' )->count() );
	}

	/**
	 * A conversation marked spam in HelpScout after it was imported is spam in FreeScout too.
	 *
	 * @return void
	 */
	public function test_conversation_marked_spam_since_is_spam(): void {
		$this->importer->import( $this->conversation( array( 'status' => 'active' ) ), $this->mailbox );

		$this->assertSame( Importer::UPDATED, $this->importer->import( $this->conversation( array( 'status' => 'spam' ) ), $this->mailbox ) );
		$this->assertSame( Conversation::STATUS_SPAM, (int) $this->imported_conversation()->status );
		$this->assertSame( Folder::TYPE_SPAM, (int) $this->imported_conversation()->folder->type );
	}

	/**
	 * A sender without an email, like a caller, is imported without one, and found again by their HelpScout ID.
	 *
	 * @return void
	 */
	public function test_senders_without_email_are_imported(): void {
		$caller = array(
			'id'    => 4242,
			'type'  => 'customer',
			'first' => 'Cal',
			'last'  => 'Ler',
		);

		$this->assertSame(
			Importer::IMPORTED,
			$this->importer->import(
				$this->conversation(
					array(
						'type'            => 'phone',
						'primaryCustomer' => $caller,
						'createdBy'       => $caller,
					)
				),
				$this->mailbox
			)
		);

		$customer = $this->imported_conversation()->customer;
		$this->assertSame( 'Cal', $customer->first_name );
		$this->assertNull( $this->imported_conversation()->customer_email );
		$this->assertSame( (int) $customer->id, (int) ( new People() )->sender( $caller )->id );
	}

	/**
	 * A sender whose email isn't one is imported like one without an email.
	 *
	 * @return void
	 */
	public function test_senders_with_a_broken_email_are_imported(): void {
		$sender = array(
			'id'    => 4243,
			'type'  => 'customer',
			'email' => 'not an email',
			'first' => 'Bro',
			'last'  => 'Ken',
		);

		$this->assertSame( Importer::IMPORTED, $this->importer->import( $this->conversation( array( 'primaryCustomer' => $sender ) ), $this->mailbox ) );
		$this->assertSame( 'Bro', $this->imported_conversation()->customer->first_name );
	}

	/**
	 * A conversation merged or deleted in HelpScout between being listed and read is gone, not failed.
	 *
	 * @return void
	 */
	public function test_conversation_gone_since_it_was_listed_is_skipped(): void {
		$this->helpscout->only( 'GET', 'v2/conversations/1001/threads', FakeHelpScout::json( array(), 404 ) );

		$this->assertSame( Importer::SKIPPED_GONE, $this->importer->import( $this->conversation(), $this->mailbox ) );

		// Merged: HelpScout redirects to the conversation it was merged into.
		$this->helpscout->only( 'GET', 'v2/conversations/1001/threads', FakeHelpScout::json( array(), 301, array( 'Location' => 'https://api.helpscout.net/v2/conversations/2002/threads' ) ) );

		$this->assertSame( Importer::SKIPPED_GONE, $this->importer->import( $this->conversation(), $this->mailbox ) );
	}

	/**
	 * An attachment deleted from HelpScout since is left out; the rest of the conversation is imported.
	 *
	 * @return void
	 */
	public function test_deleted_attachment_is_left_out(): void {
		$this->helpscout->only( 'GET', 'v2/conversations/1001/attachments/3001/file', FakeHelpScout::json( array(), 404 ) );

		$this->assertSame( Importer::IMPORTED, $this->importer->import( $this->conversation(), $this->mailbox ) );

		$email = $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_CUSTOMER )->first();
		$this->assertSame( 0, Attachment::query()->where( 'thread_id', $email->id )->count() );
	}

	/**
	 * An empty attachment is imported, empty.
	 *
	 * @return void
	 */
	public function test_empty_attachment_is_imported(): void {
		$this->helpscout->only( 'GET', 'v2/conversations/1001/attachments/3001/file', new \GuzzleHttp\Psr7\Response( 200, array(), '' ) );

		$this->assertSame( Importer::IMPORTED, $this->importer->import( $this->conversation(), $this->mailbox ) );

		$email = $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_CUSTOMER )->first();
		$this->assertSame( '', Attachment::query()->where( 'thread_id', $email->id )->firstOrFail()->getFileContents() );
	}

	/**
	 * Whoever a conversation is assigned to gets access to its mailbox, to see it.
	 *
	 * @return void
	 */
	public function test_assignee_gets_access_to_the_mailbox(): void {
		$this->agent->role = User::ROLE_USER;
		$this->agent->save();

		$this->importer->import(
			$this->conversation(
				array(
					'status'   => 'active',
					'assignee' => self::agent_person(),
				)
			),
			$this->mailbox
		);

		$this->assertSame( array( (int) $this->mailbox->id ), $this->agent->mailboxes()->pluck( 'mailboxes.id' )->map( 'intval' )->all() );
		$this->assertTrue( Folder::query()->where( 'user_id', $this->agent->id )->where( 'type', Folder::TYPE_MINE )->exists() );
	}

	/**
	 * Choosing someone else for a HelpScout user moves only what was imported for that HelpScout user.
	 *
	 * @return void
	 */
	public function test_choosing_someone_else_moves_only_that_helpscout_users_work(): void {
		People::choose( 66, $this->agent );
		$threads                 = $this->threads();
		$threads[2]['createdBy'] = array(
			'id'    => 66,
			'type'  => 'user',
			'email' => 'ada@old.example',
			'first' => 'Ada',
			'last'  => 'Agent',
		);
		$this->answer_threads( self::CONVERSATION_ID, $threads );
		$this->importer->import( $this->conversation(), $this->mailbox );

		$other = factory( User::class )->create();
		People::choose( 66, $other );

		$conversation = $this->imported_conversation();
		$this->assertSame( (int) $other->id, (int) $conversation->threads()->where( 'type', Thread::TYPE_NOTE )->value( 'created_by_user_id' ) );
		$this->assertSame( (int) $this->agent->id, (int) $conversation->threads()->where( 'type', Thread::TYPE_MESSAGE )->value( 'created_by_user_id' ) );
		$this->assertSame( (int) $this->agent->id, (int) $conversation->closed_by_user_id );
	}

	/**
	 * HelpScout keeps emails' originals for 2 years: older ones aren't asked for.
	 *
	 * @return void
	 */
	public function test_old_emails_arent_asked_for_their_message_id(): void {
		$threads                 = $this->threads();
		$threads[0]['createdAt'] = '2020-01-01T09:00:00Z';
		$this->answer_threads( self::CONVERSATION_ID, $threads );
		$this->helpscout->only( 'GET', 'v2/conversations/1001/threads/2002/original-source', FakeHelpScout::json( array(), 400 ) );

		$this->assertSame( Importer::IMPORTED, $this->importer->import( $this->conversation(), $this->mailbox ) );

		$this->assertCount( 0, $this->helpscout->requests_to( 'v2/conversations/1001/threads/2001/original-source' ) );
		$this->assertNull( $this->imported_conversation()->threads()->where( 'type', Thread::TYPE_MESSAGE )->value( 'message_id' ) );
	}

	/**
	 * Replies go to the email the sender wrote from, even when FreeScout knows them by another.
	 *
	 * @return void
	 */
	public function test_replies_go_to_the_conversations_own_email(): void {
		$customer = $this->create_sender();
		$customer->syncEmails( array( 'sam@old.example', 'sam@example.org' ) );

		$this->importer->import( $this->conversation(), $this->mailbox );

		$conversation = $this->imported_conversation();
		$this->assertSame( (int) $customer->id, (int) $conversation->customer_id );
		$this->assertSame( 'sam@example.org', $conversation->customer_email );
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
		$this->assertSame( Importer::SKIPPED_SPAM, $this->importer->import( $this->conversation( array( 'status' => 'spam' ) ), $this->mailbox ) );
		$this->assertSame( Importer::SKIPPED_UNPUBLISHED, $this->importer->import( $this->conversation( array( 'state' => 'draft' ) ), $this->mailbox ) );
		$this->assertSame( Importer::SKIPPED_NO_SENDER, $this->importer->import( $this->conversation( array( 'primaryCustomer' => array( 'type' => 'customer' ) ) ), $this->mailbox ) );

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

		$this->assertSame( Importer::SKIPPED_DELETED, $this->importer->import( $this->conversation(), $this->mailbox ) );
		$this->assertSame( 0, Conversation::query()->where( 'mailbox_id', $this->mailbox->id )->count() );
	}

	/**
	 * A failure while writing leaves nothing behind, files included, so importing again starts clean.
	 *
	 * @return void
	 */
	public function test_failure_leaves_nothing_half_imported(): void {
		$threads                                  = $this->threads();
		$threads[0]['_embedded']['attachments'][] = array(
			'id'       => 3002,
			'filename' => 'second.jpg',
		);
		$this->answer_threads( self::CONVERSATION_ID, $threads );

		$attachments = 0;
		\Eventy::addAction(
			'attachment.created',
			static function () use ( &$attachments ): void {
				if ( 2 === ++$attachments ) {
					throw new \RuntimeException( 'Disk full.' );
				}
			}
		);

		try {
			$this->importer->import( $this->conversation(), $this->mailbox );
			$this->fail( 'The import should have failed.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'Disk full.', $e->getMessage() );
		}

		$this->assertSame( 0, Conversation::query()->where( 'mailbox_id', $this->mailbox->id )->count() );
		$this->assertSame( 0, ImportedConversation::query()->count() );
		$this->assertSame( array(), Storage::disk( 'local_app' )->allFiles( '' ) );
	}

	/**
	 * A failure while reading writes nothing.
	 *
	 * @return void
	 */
	public function test_unreadable_attachment_writes_nothing(): void {
		$this->helpscout->only( 'GET', 'v2/conversations/1001/attachments/3001/file', FakeHelpScout::json( array(), 400 ) );

		try {
			$this->importer->import( $this->conversation(), $this->mailbox );
			$this->fail( 'The import should have failed.' );
		} catch ( \RuntimeException $e ) {
			$this->assertStringContainsString( '3001', $e->getMessage() );
		}

		$this->assertSame( 0, Conversation::query()->where( 'mailbox_id', $this->mailbox->id )->count() );
	}

	/**
	 * Gives a thread a Message-ID, as FreeScout does for an email it fetched.
	 *
	 * @param Thread $thread     Thread.
	 * @param string $message_id Message-ID.
	 * @return Thread
	 */
	private function with_message_id( Thread $thread, string $message_id ): Thread {
		Thread::query()->whereKey( $thread->id )->update( array( 'message_id' => $message_id ) );

		return $thread->fresh();
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
