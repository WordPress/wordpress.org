<?php
/**
 * Base test case for the importer: a fake other FreeScout, and its data.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Tests;

use App\Mailbox;
use App\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\WPOrgFreeScoutImport\Providers\WPOrgFreeScoutImportServiceProvider;
use Modules\WPOrgFreeScoutImport\Services\Source;
use Modules\WPOrgFreeScoutImport\Services\Tags;
use Modules\WPOrgFreeScoutImport\Tests\Support\FakeSource;
use WordPressdotorg\FreeScout\Tests\TestCase;

require_once __DIR__ . '/Support/FakeSource.php';

/**
 * Registers the module against a fake other FreeScout with one closed conversation, and the Tags module's tables.
 */
abstract class ImportTestCase extends TestCase {

	/**
	 * Source mailbox ID.
	 *
	 * @var int
	 */
	protected const SOURCE_MAILBOX_ID = 7;

	/**
	 * A 1x1 PNG.
	 *
	 * @var string
	 */
	protected const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

	/**
	 * Fake other FreeScout.
	 *
	 * @var FakeSource
	 */
	protected $source;

	/**
	 * Mailbox imported into.
	 *
	 * @var Mailbox
	 */
	protected $mailbox;

	/**
	 * Agent who has a user here with the same email as there.
	 *
	 * @var User
	 */
	protected $agent;

	/**
	 * Creates the Tags module's tables before the test's transaction starts: creating them inside would commit it.
	 *
	 * @return void
	 */
	protected function refreshApplication(): void {
		parent::refreshApplication();

		Schema::dropIfExists( 'conversation_tag' );
		Schema::dropIfExists( 'tags' );
		Schema::create(
			'tags',
			static function ( Blueprint $table ): void {
				$table->increments( 'id' );
				$table->string( 'name', 191 )->unique();
				$table->integer( 'counter' )->default( 0 );
				$table->unsignedTinyInteger( 'color' )->default( 0 );
			}
		);
		Schema::create(
			'conversation_tag',
			static function ( Blueprint $table ): void {
				$table->increments( 'id' );
				$table->integer( 'conversation_id' );
				$table->integer( 'tag_id' );
				$table->unique( array( 'conversation_id', 'tag_id' ) );
			}
		);
	}

	/**
	 * Registers the module, fakes the other FreeScout and file storage, and creates the agent and mailbox.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgFreeScoutImportServiceProvider::class );
		$this->app['router']->getRoutes()->refreshNameLookups();
		Storage::fake( 'local_app' );

		\App\Module::clearModulesCache();
		\App\Module::setActive( Tags::MODULE, true );
		\App\Module::clearModulesCache();
		$this->beforeApplicationDestroyed(
			static function (): void {
				Schema::dropIfExists( 'conversation_tag' );
				Schema::dropIfExists( 'tags' );
			}
		);

		$this->source = new FakeSource();
		$this->app->instance( Source::class, $this->source->client() );

		$this->agent   = factory( User::class )->create(
			array(
				'email'      => 'agent@example.org',
				'first_name' => 'Ada',
				'last_name'  => 'Agent',
				'role'       => User::ROLE_USER,
			)
		);
		$this->mailbox = $this->create_mailbox( 'WordCamp US' );

		$this->source->json(
			'api/tags',
			self::page(
				'tags',
				array(
					array(
						'id'    => 1,
						'name'  => 'Sponsors',
						'color' => 3,
					),
				)
			)
		);
		$this->source->file( FakeSource::URL . '/storage/attachment/1/pasted.png', (string) base64_decode( self::PNG, true ) );
		$this->source->file( FakeSource::URL . '/storage/attachment/2/notes.txt', 'Sponsor notes' );
	}

	/**
	 * A page of a list, as the API gives it.
	 *
	 * @param string  $key   Key of the items under `_embedded`.
	 * @param array[] $items Items.
	 * @return array
	 */
	protected static function page( string $key, array $items ): array {
		return array(
			'_embedded' => array( $key => $items ),
			'page'      => array(
				'size'          => 25,
				'totalElements' => count( $items ),
				'totalPages'    => $items ? 1 : 0,
				'number'        => 1,
			),
		);
	}

	/**
	 * A closed conversation, number 150, from a sender, answered by the agent, with a note by someone without a user
	 * here, a status change, a line item and a draft that aren't imported, an attachment, a pasted image, and a tag.
	 *
	 * @param array $changes Changes to the conversation.
	 * @return array As the API lists it, threads newest first.
	 */
	protected static function conversation( array $changes = array() ): array {
		$sender = self::sender();
		$agent  = self::user( 55, 'Ada', 'Agent', 'agent@example.org' );
		$nora   = self::user( 66, 'Nora', 'Note', 'nora@wcus.test' );

		return array_replace(
			array(
				'id'            => 501,
				'number'        => 150,
				'type'          => 'email',
				'status'        => 'closed',
				'state'         => 'published',
				'subject'       => 'Sponsorship',
				'mailboxId'     => self::SOURCE_MAILBOX_ID,
				'assignee'      => $agent,
				'createdBy'     => $sender,
				'createdAt'     => '2025-01-02T10:00:00Z',
				'closedByUser'  => $agent,
				'closedAt'      => '2025-01-02T11:06:00Z',
				'userUpdatedAt' => '2025-01-02T11:06:00Z',
				'source'        => array(
					'type' => 'email',
					'via'  => 'customer',
				),
				'cc'            => array(),
				'bcc'           => array(),
				'customer'      => $sender,
				'_embedded'     => array(
					'threads' => array(
						self::thread( 9006, 'message', $agent, '2025-01-02T11:08:00Z', array( 'state' => 'draft' ) ),
						self::thread( 9005, 'lineitem', $agent, '2025-01-02T11:07:00Z', array( 'action' => array( 'type' => 'moved-from-mailbox' ) ) ),
						self::thread(
							9004,
							'lineitem',
							$agent,
							'2025-01-02T11:06:00Z',
							array(
								'status' => 'closed',
								'action' => array( 'type' => 'changed-ticket-status' ),
							)
						),
						self::thread( 9003, 'note', $nora, '2025-01-02T11:05:00Z', array( 'body' => '<p>Called them.</p>' ) ),
						self::thread( 9002, 'message', $agent, '2025-01-02T11:00:00Z', array( 'body' => '<p>Thanks!</p>' ) ),
						self::thread(
							9001,
							'customer',
							$sender,
							'2025-01-02T10:00:00Z',
							array(
								'body'      => '<p>Hi</p><img src="' . FakeSource::URL . '/storage/attachment/1/pasted.png?id=1&amp;token=a"><img src="https://elsewhere.test/logo.png">',
								'_embedded' => array(
									'attachments' => array(
										array(
											'id'       => 2,
											'fileName' => 'notes.txt',
											'fileUrl'  => FakeSource::URL . '/storage/attachment/2/notes.txt?id=2&token=b',
											'mimeType' => 'text/plain',
										),
										array(
											'id'       => 3,
											'fileName' => 'elsewhere.txt',
											'fileUrl'  => 'https://elsewhere.test/storage/attachment/3/elsewhere.txt',
											'mimeType' => 'text/plain',
										),
									),
								),
							)
						),
					),
					'tags'    => array(
						array(
							'id'   => 1,
							'name' => 'Sponsors',
						),
					),
				),
			),
			$changes
		);
	}

	/**
	 * A thread, as the API gives it.
	 *
	 * @param int    $id         Thread ID.
	 * @param string $type       Type.
	 * @param array  $created_by Who wrote it.
	 * @param string $created_at When.
	 * @param array  $changes    Changes.
	 * @return array
	 */
	protected static function thread( int $id, string $type, array $created_by, string $created_at, array $changes = array() ): array {
		return array_replace(
			array(
				'id'         => $id,
				'type'       => $type,
				'status'     => 'active',
				'state'      => 'published',
				'action'     => array( 'type' => '' ),
				'body'       => '',
				'source'     => array(
					'type' => 'email',
					'via'  => $created_by['type'],
				),
				'customer'   => self::sender(),
				'createdBy'  => $created_by,
				'assignedTo' => self::user( 55, 'Ada', 'Agent', 'agent@example.org' ),
				'to'         => array( 'us@wordcamp.org' ),
				'cc'         => array(),
				'bcc'        => array(),
				'createdAt'  => $created_at,
				'openedAt'   => null,
				'_embedded'  => array( 'attachments' => array() ),
			),
			$changes
		);
	}

	/**
	 * A source user, as conversations name them.
	 *
	 * @param int    $id    User ID there.
	 * @param string $first First name.
	 * @param string $last  Last name.
	 * @param string $email Email.
	 * @return array
	 */
	protected static function user( int $id, string $first, string $last, string $email ): array {
		return array(
			'id'        => $id,
			'type'      => 'user',
			'firstName' => $first,
			'lastName'  => $last,
			'email'     => $email,
		);
	}

	/**
	 * The conversation's sender, as the API names them.
	 *
	 * @return array
	 */
	protected static function sender(): array {
		return array(
			'id'        => 9,
			'type'      => 'customer',
			'firstName' => 'Sam',
			'lastName'  => 'Sender',
			'email'     => 'sam@example.com',
		);
	}
}
