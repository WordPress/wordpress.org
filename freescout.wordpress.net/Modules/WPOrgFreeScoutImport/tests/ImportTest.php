<?php
/**
 * Tests importing another FreeScout's conversations.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Tests;

use App\Attachment;
use App\Conversation;
use App\Thread;
use App\User;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Modules\WPOrgFreeScoutImport\Entities\ImportedConversation;
use Modules\WPOrgFreeScoutImport\Entities\ImportedThread;
use Modules\WPOrgFreeScoutImport\Entities\Run;
use Modules\WPOrgFreeScoutImport\Entities\SourceUser;
use Modules\WPOrgFreeScoutImport\Services\Importer;
use Modules\WPOrgFreeScoutImport\Services\People;
use Modules\WPOrgFreeScoutImport\Services\Settings;
use Modules\WPOrgFreeScoutImport\Services\Source;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Services\Client;
use Psr\Http\Message\RequestInterface;

require_once __DIR__ . '/ImportTestCase.php';

/**
 * Covers a conversation's import, importing it again, Message-IDs, crediting users, and a whole run.
 */
final class ImportTest extends ImportTestCase {

	/**
	 * A conversation gets this FreeScout's next number, not its old one nor the Next Conversation # for live email,
	 * with its threads, files, people, and tags; and only the source's own files are downloaded.
	 *
	 * @return void
	 */
	public function test_imports_a_conversation_with_a_number_of_its_own(): void {
		\Option::set( 'next_ticket', 2000000 );
		$next = (int) Conversation::query()->max( 'number' ) + 1;

		$result = $this->import( self::conversation() );

		$conversation = $this->imported( 501 );
		$this->assertSame( Importer::IMPORTED, $result );
		// Raw: without custom numbers, core shows the ID as the number.
		$this->assertSame( $next, (int) $conversation->getOriginal( 'number' ) );
		$this->assertSame( 150, (int) ImportedConversation::query()->where( 'source_id', 501 )->value( 'source_number' ) );
		$this->assertSame( 2000000, (int) \Option::get( 'next_ticket', 0, true, false ) );

		$this->assertSame( Conversation::STATUS_CLOSED, (int) $conversation->status );
		$this->assertSame( (int) $this->agent->id, (int) $conversation->user_id );
		$this->assertSame( (int) $this->agent->id, (int) $conversation->closed_by_user_id );

		$threads = $conversation->threads()->orderBy( 'created_at' )->orderBy( 'id' )->get();
		$this->assertSame( array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE, Thread::TYPE_NOTE, Thread::TYPE_LINEITEM ), $threads->pluck( 'type' )->map( 'intval' )->all() );
		$this->assertSame( Thread::ACTION_TYPE_STATUS_CHANGED, (int) $threads[3]->action_type );
		$this->assertSame( (int) $this->agent->id, (int) $threads[1]->created_by_user_id );

		$nora = User::query()->where( 'email', 'nora@wcus.test' )->first();
		$this->assertSame( User::STATUS_DISABLED, (int) $nora->status );
		$this->assertSame( (int) $nora->id, (int) $threads[2]->created_by_user_id );

		$files = Attachment::query()->where( 'thread_id', $threads[0]->id )->get();
		$this->assertSame( array( 'notes.txt' ), $files->where( 'embedded', false )->pluck( 'file_name' )->all() );
		$image = $files->where( 'embedded', true )->first();
		$this->assertStringContainsString( $image->url(), (string) $threads[0]->body );
		$this->assertStringContainsString( 'https://elsewhere.test/logo.png', (string) $threads[0]->body );
		$this->assertStringNotContainsString( 'scout.test', (string) $threads[0]->body );
		$this->assertSame( array( 'scout.test' ), $this->source->hosts() );

		$tag = \DB::table( 'tags' )->where( 'name', 'sponsors' )->first();
		$this->assertSame( 3, (int) $tag->color );
		$this->assertTrue( \DB::table( 'conversation_tag' )->where( 'conversation_id', $conversation->id )->where( 'tag_id', $tag->id )->exists() );
	}

	/**
	 * Importing again adds the source's new threads once, and keeps the status agents set here.
	 *
	 * @return void
	 */
	public function test_importing_again_adds_new_threads_but_keeps_agents_changes(): void {
		$this->import( self::conversation() );
		$conversation = $this->imported( 501 );
		$this->create_thread( $conversation, Thread::TYPE_NOTE, 'Following up here.', $this->agent, '2025-01-03 09:00:00' );

		$source                           = self::conversation( array( 'status' => 'active' ) );
		$source['_embedded']['threads'][] = self::thread( 9007, 'customer', self::sender(), '2025-01-04T10:00:00Z', array( 'body' => '<p>One more thing</p>' ) );

		$this->assertSame( Importer::UPDATED, $this->import( $source ) );

		$conversation->refresh();
		$this->assertSame( 6, $conversation->threads()->count() );
		$this->assertSame( 1, ImportedThread::query()->where( 'source_id', 9007 )->count() );
		$this->assertSame( Conversation::STATUS_CLOSED, (int) $conversation->status );
	}

	/**
	 * Uploaded Message-IDs reach threads imported before and after, except one another thread has: it's unique.
	 *
	 * @return void
	 */
	public function test_uploaded_message_ids_go_to_threads_imported_before_and_after(): void {
		$this->import( self::conversation() );

		$other = $this->create_conversation( $this->mailbox, $this->create_sender( 'someone@example.com' ) );
		$taken = $this->create_thread( $other, Thread::TYPE_CUSTOMER, 'Hello', null, '2025-01-01 09:00:00' );
		Thread::query()->whereKey( $taken->id )->update( array( 'message_id' => 'taken@example.com' ) );

		$path = (string) tempnam( sys_get_temp_dir(), 'message-ids' );
		file_put_contents( $path, "9001,<sam-1@example.com>\n9002,FS_reply-9002-abcdef0123456789@wordcamp.org\n9101,taken@example.com\n9102,sam-2@example.com\n" );

		$this->actingAs( $this->create_user() )
			->post( route( 'wporgfreescoutimport.message_ids' ), array( 'message_ids' => new UploadedFile( $path, 'message-ids.csv', 'text/csv', null, null, true ) ) )
			->assertRedirect( route( 'wporgfreescoutimport.index' ) );

		$later = self::conversation(
			array(
				'id'        => 502,
				'number'    => 151,
				'_embedded' => array(
					'threads' => array(
						self::thread( 9102, 'customer', self::sender(), '2025-02-01T11:00:00Z', array( 'body' => '<p>Again</p>' ) ),
						self::thread( 9101, 'customer', self::sender(), '2025-02-01T10:00:00Z', array( 'body' => '<p>Hello</p>' ) ),
					),
				),
			)
		);
		$this->import( $later );

		$this->assertSame(
			array( 'sam-1@example.com', 'FS_reply-9002-abcdef0123456789@wordcamp.org', null, 'sam-2@example.com' ),
			array_map(
				static function ( int $source_id ): ?string {
					return Thread::query()->whereKey( ImportedThread::query()->where( 'source_id', $source_id )->value( 'thread_id' ) )->value( 'message_id' );
				},
				array( 9001, 9002, 9101, 9102 )
			)
		);
	}

	/**
	 * Choosing a user for a source user credits what was imported for them to that user.
	 *
	 * @return void
	 */
	public function test_choosing_a_user_credits_what_was_imported_for_them(): void {
		$this->import( self::conversation() );
		$nora = factory( User::class )->create(
			array(
				'email' => 'nora@example.org',
				'role'  => User::ROLE_USER,
			)
		);

		$this->actingAs( $this->create_user() )
			->post( route( 'wporgfreescoutimport.users.save' ), array( 'users' => array( 66 => $nora->id ) ) )
			->assertRedirect( route( 'wporgfreescoutimport.users' ) );

		$note = Thread::query()->whereKey( ImportedThread::query()->where( 'source_id', 9003 )->value( 'thread_id' ) )->first();
		$this->assertSame( (int) $nora->id, (int) $note->created_by_user_id );
	}

	/**
	 * A run started on the page imports page after page until one is empty, leaves out spam and deleted
	 * conversations, and the old number then finds what it imported.
	 *
	 * @return void
	 */
	public function test_a_run_imports_every_page_and_finds_conversations_by_their_old_number(): void {
		$this->source->json(
			'api/mailboxes',
			self::page(
				'mailboxes',
				array(
					array(
						'id'    => self::SOURCE_MAILBOX_ID,
						'name'  => 'WordCamp US',
						'email' => 'us@wordcamp.org',
					),
				)
			)
		);
		$listed = self::page(
			'conversations',
			array(
				self::conversation(),
				self::conversation(
					array(
						'id'     => 503,
						'number' => 152,
						'status' => 'spam',
					)
				),
				self::conversation(
					array(
						'id'     => 504,
						'number' => 153,
						'state'  => 'deleted',
					)
				),
			)
		);
		$this->source->json( 'api/conversations', $listed, 1 );
		$this->source->json( 'api/conversations', self::page( 'conversations', array() ), 2 );

		$admin = $this->create_user();
		$this->actingAs( $admin )->post(
			route( 'wporgfreescoutimport.start' ),
			array(
				'source_mailbox_id' => self::SOURCE_MAILBOX_ID,
				'mailbox_id'        => $this->mailbox->id,
			)
		);

		$run = Run::query()->latest( 'id' )->first();
		$this->assertSame( Run::STATUS_DONE, $run->status );
		$this->assertSame( 1, (int) $run->imported );
		$this->assertSame(
			array(
				Importer::SKIPPED_SPAM        => 1,
				Importer::SKIPPED_UNPUBLISHED => 1,
			),
			$run->skips
		);

		$this->actingAs( $admin )
			->get( route( 'wporgfreescoutimport.find', array( 'number' => '#150' ) ) )
			->assertRedirect( route( 'conversations.view', array( 'id' => $this->imported( 501 )->id ) ) );
	}

	/**
	 * A CSV connects users who'll work here: one an import created disabled is switched on, with access to its mailbox,
	 * and someone new gets a user; one who was here before, under another email than the account's, isn't connected.
	 *
	 * @return void
	 */
	public function test_csv_connects_users_who_will_work_here(): void {
		Queue::fake();
		$this->use_wordpress_org(
			array(
				'adaagent' => array( 'Ada', 'Agent', 'ada@wordpress.example' ),
				'noranote' => array( 'Nora', 'Note', 'nora@wordpress.example' ),
				'bonew'    => array( 'Bo', 'New', 'bo@wordpress.example' ),
			)
		);
		$this->source->json(
			'api/users',
			self::page(
				'users',
				array(
					self::user( 55, 'Ada', 'Agent', 'agent@example.org' ),
					self::user( 66, 'Nora', 'Note', 'nora@wcus.test' ),
					self::user( 77, 'Bo', 'New', 'bo@wcus.test' ),
				)
			)
		);
		$this->import( self::conversation() );

		$this->actingAs( $this->create_user() )
			->post(
				route( 'wporgfreescoutimport.users.connect' ),
				array(
					'csv'   => "source_id,wporg_username\n55,adaagent\n66,noranote\n77,bonew\n",
					'apply' => 1,
				)
			)
			->assertSessionHas( 'flash_success', 'Connected 2 users to WordPress.org accounts.' );

		$nora = User::query()->findOrFail( (int) SourceUser::query()->where( 'source_user_id', 66 )->value( 'user_id' ) );
		$this->assertSame( 'noranote', Account::username_for( (int) $nora->id ) );
		$this->assertSame( User::STATUS_ACTIVE, (int) $nora->status );
		$this->assertTrue( $nora->mailboxes()->where( 'mailboxes.id', $this->mailbox->id )->exists() );

		$bo = User::query()->findOrFail( (int) SourceUser::query()->where( 'source_user_id', 77 )->value( 'user_id' ) );
		$this->assertSame( 'bonew', Account::username_for( (int) $bo->id ) );
		$this->assertSame( User::STATUS_ACTIVE, (int) $bo->status );

		$this->assertSame( '', Account::username_for( (int) $this->agent->id ) );
	}

	/**
	 * The API key is kept encrypted, and saving the address again without one keeps it, unless it names another host.
	 *
	 * @return void
	 */
	public function test_settings_keep_the_saved_api_key_encrypted(): void {
		$admin = $this->create_user();
		$this->actingAs( $admin )->post(
			route( 'wporgfreescoutimport.settings' ),
			array(
				'url'     => 'https://scout.wc-us.org/',
				'api_key' => 'secret-key',
			)
		);
		$this->actingAs( $admin )->post(
			route( 'wporgfreescoutimport.settings' ),
			array(
				'url'     => 'https://scout.wc-us.org',
				'api_key' => '',
			)
		);

		$this->assertSame( 'https://scout.wc-us.org', Settings::url() );
		$this->assertSame( 'secret-key', Settings::api_key() );
		$this->assertStringNotContainsString( 'secret-key', (string) \Option::get( Settings::KEY_OPTION, '', true, false ) );

		$this->actingAs( $admin )->post(
			route( 'wporgfreescoutimport.settings' ),
			array(
				'url'     => 'https://elsewhere.test',
				'api_key' => '',
			)
		);
		$this->assertSame( '', Settings::api_key() );
	}

	/**
	 * Turns WP.org SSO on, with a fake account lookup on api.wordpress.org.
	 *
	 * @param array[] $accounts First name, last name, and email, by username.
	 * @return void
	 */
	private function use_wordpress_org( array $accounts ): void {
		$this->app->register( \Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider::class );

		$this->app->instance(
			Client::class,
			new Client(
				'https://api.wordpress.test/',
				'test-secret',
				10,
				static function ( RequestInterface $request ) use ( $accounts ): PromiseInterface {
					$username = strtolower( (string) ( json_decode( (string) $request->getBody(), true )['username'] ?? '' ) );
					$account  = isset( $accounts[ $username ] ) ? array(
						'username'   => $username,
						'first_name' => $accounts[ $username ][0],
						'last_name'  => $accounts[ $username ][1],
						'email'      => $accounts[ $username ][2],
					) : null;

					return new FulfilledPromise( new Response( 200, array(), (string) json_encode( array( 'user' => $account ) ) ) );
				}
			)
		);
	}

	/**
	 * Imports a source conversation into the mailbox.
	 *
	 * @param array $source Source conversation.
	 * @return string What the importer returned.
	 */
	private function import( array $source ): string {
		return ( new Importer( app( Source::class ), new People() ) )->import( $source, $this->mailbox );
	}

	/**
	 * The conversation a source conversation was imported as.
	 *
	 * @param int $source_id Source conversation ID.
	 * @return Conversation
	 */
	private function imported( int $source_id ): Conversation {
		return Conversation::query()->findOrFail( (int) ImportedConversation::query()->where( 'source_id', $source_id )->value( 'conversation_id' ) );
	}
}
