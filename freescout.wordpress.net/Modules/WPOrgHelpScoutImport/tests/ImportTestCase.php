<?php
/**
 * Base test case for the importer: a fake HelpScout, and its data.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use App\Mailbox;
use App\User;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;
use Modules\WPOrgHelpScoutImport\Providers\WPOrgHelpScoutImportServiceProvider;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Tests\Support\FakeHelpScout;
use Modules\WPOrgHelpScoutImport\Tests\Support\PaidModules;
use WordPressdotorg\FreeScout\Tests\TestCase;

require_once __DIR__ . '/Support/FakeHelpScout.php';
require_once __DIR__ . '/Support/PaidModules.php';

/**
 * Registers the module against a fake HelpScout with one closed conversation of four threads, and its mailbox's user.
 */
abstract class ImportTestCase extends TestCase {

	/**
	 * HelpScout conversation ID used throughout.
	 *
	 * @var int
	 */
	protected const CONVERSATION_ID = 1001;

	/**
	 * Fake HelpScout API.
	 *
	 * @var FakeHelpScout
	 */
	protected $helpscout;

	/**
	 * Mailbox imported into.
	 *
	 * @var Mailbox
	 */
	protected $mailbox;

	/**
	 * Agent with a FreeScout user.
	 *
	 * @var User
	 */
	protected $agent;

	/**
	 * Registers the module, fakes HelpScout and file storage, and creates the agent and mailbox.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgHelpScoutImportServiceProvider::class );
		$this->app['router']->getRoutes()->refreshNameLookups();
		Storage::fake( 'local_app' );

		$this->helpscout = new FakeHelpScout();
		$this->app->instance( HelpScout::class, $this->helpscout->client() );

		$this->agent   = factory( User::class )->create(
			array(
				'email'      => 'agent@example.org',
				'first_name' => 'Ada',
				'last_name'  => 'Agent',
			)
		);
		$this->mailbox = $this->create_mailbox( 'Photos' );

		$this->answer_directory( array( self::helpscout_user( 55, 'Ada', 'Agent', 'agent@example.org' ) ) );
		$this->answer_threads( self::CONVERSATION_ID, $this->threads() );
	}

	/**
	 * Forgets the teams the test made, which the database forgets with them.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PaidModules::forget_teams();

		parent::tearDown();
	}

	/**
	 * Has HelpScout list its Photos mailbox (77), and users who can all see it.
	 *
	 * @param array[] $users HelpScout users, as HelpScout lists them.
	 * @return void
	 */
	protected function answer_directory( array $users ): void {
		$this->helpscout->only(
			'GET',
			'v2/mailboxes',
			self::list(
				'mailboxes',
				array(
					array(
						'id'    => 77,
						'name'  => 'Photos',
						'email' => 'photos@wordpress.org',
					),
				)
			)
		);
		$this->helpscout->only( 'GET', 'v2/users', self::list( 'users', $users ) );
		$this->helpscout->only_mailbox_users( 77, self::list( 'users', $users ) );
	}

	/**
	 * A HelpScout list's single page.
	 *
	 * @param string  $key   Key in `_embedded`.
	 * @param array[] $items Items.
	 * @return array
	 */
	protected static function list( string $key, array $items ): array {
		return array(
			'_embedded' => array( $key => $items ),
			'page'      => array( 'totalPages' => 1 ),
		);
	}

	/**
	 * A HelpScout user, as HelpScout lists its users.
	 *
	 * @param int    $id    HelpScout user ID.
	 * @param string $first First name.
	 * @param string $last  Last name.
	 * @param string $email Email.
	 * @return array
	 */
	protected static function helpscout_user( int $id, string $first, string $last, string $email ): array {
		return array(
			'id'        => $id,
			'type'      => 'user',
			'firstName' => $first,
			'lastName'  => $last,
			'email'     => $email,
			'timezone'  => 'Europe/Berlin',
		);
	}

	/**
	 * A HelpScout conversation.
	 *
	 * @param array $changes Fields to change.
	 * @return array
	 */
	protected function conversation( array $changes = array() ): array {
		$sender = self::sender( 'sam@example.org', 'Sam', 'Sender' );

		return array_replace(
			array(
				'id'              => self::CONVERSATION_ID,
				'number'          => 501,
				'type'            => 'email',
				'status'          => 'closed',
				'state'           => 'published',
				'subject'         => 'My photo was rejected',
				'mailboxId'       => 77,
				'createdBy'       => $sender,
				'primaryCustomer' => $sender,
				'assignee'        => null,
				'closedByUser'    => self::agent_person(),
				'closedAt'        => '2026-09-02T10:00:00Z',
				'createdAt'       => '2026-09-01T09:00:00Z',
				'userUpdatedAt'   => '2026-09-02T10:00:00Z',
				'source'          => array(
					'type' => 'email',
					'via'  => 'customer',
				),
				'tags'            => array(
					array(
						'id'  => 1,
						'tag' => 'photos',
					),
				),
				'cc'              => array(),
				'bcc'             => array(),
				'customFields'    => array(),
			),
			$changes
		);
	}

	/**
	 * The conversation's threads: the sender's email with an attachment, the agent's reply, a note, and a line item.
	 *
	 * @param int $offset Added to the thread and attachment IDs, which are unique across conversations.
	 * @return array[]
	 */
	protected function threads( int $offset = 0 ): array {
		$sender = self::sender( 'sam@example.org', 'Sam', 'Sender' );

		$threads = array(
			array(
				'id'        => 2001,
				'type'      => 'customer',
				'status'    => 'active',
				'state'     => 'published',
				'body'      => '<p>Why was my photo rejected?</p>',
				'source'    => array(
					'type' => 'email',
					'via'  => 'customer',
				),
				'customer'  => $sender,
				'createdBy' => $sender,
				'to'        => array( 'photos@wordpress.org' ),
				'cc'        => array( 'friend@example.org' ),
				'bcc'       => array(),
				'createdAt' => '2026-09-01T09:00:00Z',
				'_embedded' => array(
					'attachments' => array(
						array(
							'id'       => 3001,
							'filename' => 'photo.jpg',
							'mimeType' => 'image/jpeg',
						),
					),
				),
			),
			array(
				'id'         => 2002,
				'type'       => 'message',
				'status'     => 'closed',
				'state'      => 'published',
				'body'       => '<p>It was blurry.</p>',
				'source'     => array(
					'type' => 'email',
					'via'  => 'user',
				),
				'customer'   => $sender,
				'createdBy'  => self::agent_person(),
				'assignedTo' => self::agent_person(),
				'to'         => array( 'sam@example.org' ),
				'cc'         => array(),
				'bcc'        => array(),
				'createdAt'  => '2026-09-02T10:00:00Z',
			),
			array(
				'id'        => 2003,
				'type'      => 'note',
				'status'    => 'closed',
				'state'     => 'published',
				'body'      => 'Checked the original.',
				'source'    => array(
					'type' => 'web',
					'via'  => 'user',
				),
				'createdBy' => self::agent_person(),
				'createdAt' => '2026-09-02T09:59:00Z',
			),
			array(
				'id'        => 2004,
				'type'      => 'lineitem',
				'status'    => 'closed',
				'state'     => 'published',
				'action'    => array( 'text' => 'Closed by Ada' ),
				'createdBy' => self::agent_person(),
				'createdAt' => '2026-09-02T10:00:01Z',
			),
		);

		foreach ( $threads as &$thread ) {
			$thread['id'] += $offset;
			foreach ( $thread['_embedded']['attachments'] ?? array() as $index => $attachment ) {
				$thread['_embedded']['attachments'][ $index ]['id'] += $offset;
			}
		}

		return $threads;
	}

	/**
	 * Has HelpScout answer a conversation's threads, their original emails, and attachments.
	 *
	 * @param int     $conversation_id HelpScout conversation ID.
	 * @param array[] $threads         Threads.
	 * @return void
	 */
	protected function answer_threads( int $conversation_id, array $threads ): void {
		$this->helpscout->only(
			'GET',
			'v2/conversations/' . $conversation_id . '/threads',
			array(
				'_embedded' => array( 'threads' => array_reverse( $threads ) ),
				'page'      => array( 'totalPages' => 1 ),
			)
		);

		foreach ( $threads as $thread ) {
			$this->helpscout->only(
				'GET',
				'v2/conversations/' . $conversation_id . '/threads/' . $thread['id'] . '/original-source',
				new Response( 200, array( 'Content-Type' => 'message/rfc822' ), "Subject: Hi\r\nMessage-ID: <thread-{$thread['id']}@mail.example.org>\r\n\r\nBody" )
			);

			foreach ( (array) ( $thread['_embedded']['attachments'] ?? array() ) as $attachment ) {
				$this->helpscout->only(
					'GET',
					'v2/conversations/' . $conversation_id . '/attachments/' . $attachment['id'] . '/file',
					new Response( 200, array( 'Content-Type' => 'image/jpeg' ), 'jpeg bytes' )
				);
			}
		}
	}

	/**
	 * A HelpScout customer.
	 *
	 * @param string $email Email.
	 * @param string $first First name.
	 * @param string $last  Last name.
	 * @return array
	 */
	protected static function sender( string $email, string $first, string $last ): array {
		return array(
			'id'    => crc32( $email ),
			'type'  => 'customer',
			'email' => $email,
			'first' => $first,
			'last'  => $last,
		);
	}

	/**
	 * The agent, as HelpScout refers to them.
	 *
	 * @return array
	 */
	protected static function agent_person(): array {
		return array(
			'id'    => 55,
			'type'  => 'user',
			'email' => 'agent@example.org',
			'first' => 'Ada',
			'last'  => 'Agent',
		);
	}
}
