<?php
/**
 * Tests for keeping the Reports module's metrics up to date with imports.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use App\Conversation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgHelpScoutImport\Entities\ImportedConversation;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\Importer;
use Modules\WPOrgHelpScoutImport\Services\People;

require_once __DIR__ . '/ImportTestCase.php';

/**
 * Covers flagging conversations for the Reports module to work their metrics out again, through the importer.
 */
final class ReportsTest extends ImportTestCase {

	/**
	 * The Reports module's column, which flags conversations whose metrics are up to date.
	 *
	 * @var string
	 */
	private const COLUMN = 'rpt_ready';

	/**
	 * Whether the test added the column, so it's dropped again.
	 *
	 * @var bool
	 */
	private static $added = false;

	/**
	 * Importer under test.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * Adds the module's column before the test's transaction starts: altering a table would commit it.
	 *
	 * @return void
	 */
	protected function refreshApplication(): void {
		parent::refreshApplication();

		self::$added = ! Schema::hasColumn( 'conversations', self::COLUMN );
		if ( self::$added ) {
			Schema::table(
				'conversations',
				static function ( Blueprint $table ): void {
					$table->boolean( self::COLUMN )->default( false );
				}
			);
		}
	}

	/**
	 * Creates the importer, and has it look for the column again, before and after the test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		self::forget_columns();
		$this->beforeApplicationDestroyed( array( self::class, 'forget_columns' ) );
		$this->beforeApplicationDestroyed( array( self::class, 'drop_column' ) );

		$this->importer = new Importer( app( HelpScout::class ), new People( app( HelpScout::class ) ) );
	}

	/**
	 * Importing new threads into a conversation flags it, without changing when it was updated.
	 *
	 * @return void
	 */
	public function test_new_threads_flag_the_conversation(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );
		$this->mark_ready();

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
		$this->importer->import( $this->conversation( array( 'userUpdatedAt' => '2026-09-05T08:00:00Z' ) ), $this->mailbox );

		$conversation = $this->imported_conversation();
		$this->assertFalse( (bool) $conversation->{self::COLUMN} );
		$this->assertSame( '2026-09-05 08:00:00', $conversation->updated_at->setTimezone( 'UTC' )->format( 'Y-m-d H:i:s' ) );
	}

	/**
	 * Importing a conversation HelpScout reopened flags it; one that didn't change isn't.
	 *
	 * @return void
	 */
	public function test_status_changes_flag_the_conversation(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );
		$this->mark_ready();

		$this->importer->import( $this->conversation(), $this->mailbox );
		$this->assertTrue( (bool) $this->imported_conversation()->{self::COLUMN} );

		$this->importer->import( $this->conversation( array( 'status' => 'active' ) ), $this->mailbox );
		$this->assertFalse( (bool) $this->imported_conversation()->{self::COLUMN} );
	}

	/**
	 * Makes the importer look for core's columns again.
	 *
	 * @return void
	 */
	public static function forget_columns(): void {
		$columns = new \ReflectionProperty( Importer::class, 'columns' );
		$columns->setAccessible( true );
		$columns->setValue( null, array() );
	}

	/**
	 * Drops the column again, if the test added it, once its transaction is rolled back.
	 *
	 * @return void
	 */
	public static function drop_column(): void {
		if ( ! self::$added ) {
			return;
		}

		Schema::table(
			'conversations',
			static function ( Blueprint $table ): void {
				$table->dropColumn( self::COLUMN );
			}
		);
		self::$added = false;
	}

	/**
	 * Marks the imported conversation's metrics as up to date, as the Reports module does once it worked them out.
	 *
	 * @return void
	 */
	private function mark_ready(): void {
		Conversation::query()->whereKey( $this->imported_conversation()->id )->update( array( self::COLUMN => true ) );
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
