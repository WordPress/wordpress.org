<?php
/**
 * Tests for HelpScout's links to conversations.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use App\Conversation;
use App\User;
use Modules\WPOrgHelpScoutImport\Entities\ImportedConversation;
use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use Modules\WPOrgSite\Services\HelpScoutLinks;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers opening a HelpScout link with FreeScout's address, and keeping the HelpScout IDs it needs.
 */
final class HelpScoutLinksTest extends TestCase {

	/**
	 * HelpScout's ID of the conversation.
	 *
	 * @var int
	 */
	private const HELPSCOUT_ID = 2876543210;

	/**
	 * The conversation it was imported as.
	 *
	 * @var Conversation
	 */
	private $conversation;

	/**
	 * Registers the module, and imports a conversation.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSiteServiceProvider::class );
		$this->app['router']->getRoutes()->refreshNameLookups();

		$this->conversation = $this->create_conversation( $this->create_mailbox(), $this->create_sender() );
		HelpScoutLinks::record( self::HELPSCOUT_ID, (int) $this->conversation->id );
	}

	/**
	 * Both forms of a HelpScout link open the conversation.
	 *
	 * @dataProvider data_links
	 *
	 * @param string $path Link's path.
	 * @return void
	 */
	public function test_redirects_to_conversation( string $path ): void {
		$this->actingAs( $this->create_user() );

		$this->get( $path )->assertRedirect( $this->conversation->url() );
	}

	/**
	 * HelpScout's links: as HelpScout made them, and with only the ID, which is core's route.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function data_links(): array {
		return array(
			'ID and number' => array( '/conversation/' . self::HELPSCOUT_ID . '/123456/?folderId=42' ),
			'ID only'       => array( '/conversation/' . self::HELPSCOUT_ID ),
		);
	}

	/**
	 * A FreeScout conversation isn't redirected, even when its ID is also a recorded HelpScout ID.
	 *
	 * @return void
	 */
	public function test_leaves_freescout_conversations_alone(): void {
		$other = $this->create_conversation( $this->create_mailbox( 'Data' ), $this->create_sender() );
		HelpScoutLinks::record( (int) $this->conversation->id, (int) $other->id );
		$this->actingAs( $this->create_user() );

		\DB::enableQueryLog();
		$response = $this->get( $this->conversation->url() );
		$queries  = implode( "\n", array_column( \DB::getQueryLog(), 'query' ) );

		// Core's template fails on PHP 8.4 deprecations under test, so only the redirect is checked.
		$this->assertFalse( $response->isRedirect() );
		$this->assertStringNotContainsString( HelpScoutLinks::TABLE, $queries );
	}

	/**
	 * An agent without access to the conversation's mailbox isn't told where it is.
	 *
	 * @return void
	 */
	public function test_hides_conversation_from_agent_without_access(): void {
		$this->actingAs( $this->create_user( User::ROLE_USER ) );

		$this->get( '/conversation/' . self::HELPSCOUT_ID . '/123456' )->assertStatus( 404 );
	}

	/**
	 * The importer's IDs are copied as the table is created, and those it saves later are added.
	 *
	 * @return void
	 */
	public function test_keeps_importer_ids(): void {
		\DB::table( HelpScoutLinks::TABLE )->delete();
		\DB::table( HelpScoutLinks::IMPORTER_TABLE )->insert(
			array(
				'helpscout_id'     => self::HELPSCOUT_ID,
				'helpscout_number' => 123456,
				'conversation_id'  => $this->conversation->id,
			)
		);

		HelpScoutLinks::copy_imported();

		$imported = $this->create_conversation( $this->create_mailbox( 'Data' ), $this->create_sender() );
		ImportedConversation::query()->create(
			array(
				'helpscout_id'     => self::HELPSCOUT_ID + 1,
				'helpscout_number' => 123457,
				'conversation_id'  => $imported->id,
			)
		);

		$this->assertSame(
			array(
				self::HELPSCOUT_ID     => (int) $this->conversation->id,
				self::HELPSCOUT_ID + 1 => (int) $imported->id,
			),
			\DB::table( HelpScoutLinks::TABLE )->orderBy( 'helpscout_id' )->pluck( 'conversation_id', 'helpscout_id' )->map( 'intval' )->all()
		);
	}
}
