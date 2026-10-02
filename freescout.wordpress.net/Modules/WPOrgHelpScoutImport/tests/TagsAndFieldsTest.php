<?php
/**
 * Tests for importing tags and custom fields into their modules.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use Modules\WPOrgHelpScoutImport\Entities\ImportedConversation;
use Modules\WPOrgHelpScoutImport\Services\CustomFields;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\Importer;
use Modules\WPOrgHelpScoutImport\Services\People;
use Modules\WPOrgHelpScoutImport\Services\Tags;
use Modules\WPOrgHelpScoutImport\Tests\Support\FakeHelpScout;
use Modules\WPOrgHelpScoutImport\Tests\Support\PaidModules;

require_once __DIR__ . '/ImportTestCase.php';
require_once __DIR__ . '/Support/PaidModules.php';

/**
 * Covers Tags and CustomFields, through the importer.
 */
final class TagsAndFieldsTest extends ImportTestCase {

	/**
	 * Importer under test.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * Creates the paid modules' tables before the test's transaction starts.
	 *
	 * @return void
	 */
	protected function refreshApplication(): void {
		parent::refreshApplication();

		PaidModules::create();
	}

	/**
	 * Switches the Tags and Custom Fields modules on, and has HelpScout list the mailbox's fields.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		PaidModules::switch( Tags::MODULE, true );
		PaidModules::switch( CustomFields::MODULE, true );
		$this->beforeApplicationDestroyed( array( PaidModules::class, 'drop' ) );

		$this->helpscout->only(
			'GET',
			'v2/mailboxes/77/fields',
			array(
				'_embedded' => array(
					'fields' => array(
						array(
							'id'      => 104,
							'name'    => 'Photo type',
							'type'    => 'dropdown',
							'order'   => 1,
							'options' => array(
								array(
									'id'    => 170,
									'order' => 2,
									'label' => 'Portrait',
								),
								array(
									'id'    => 168,
									'order' => 1,
									'label' => 'Landscape',
								),
							),
						),
						array(
							'id'    => 105,
							'name'  => 'Taken on',
							'type'  => 'date',
							'order' => 2,
						),
						array(
							'id'    => 106,
							'name'  => 'Camera',
							'type'  => 'singleline',
							'order' => 3,
						),
					),
				),
				'page'      => array( 'totalPages' => 1 ),
			)
		);

		$this->importer = new Importer( app( HelpScout::class ), new People( app( HelpScout::class ) ) );
	}

	/**
	 * Leaves the module list as other tests expect it.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		\App\Module::clearModulesCache();

		parent::tearDown();
	}

	/**
	 * HelpScout's tags are the conversation's, created in the color nearest HelpScout's, and counted.
	 *
	 * @return void
	 */
	public function test_tags_are_imported(): void {
		\DB::table( 'tags' )->insert(
			array(
				'name'    => 'photos',
				'counter' => 4,
				'color'   => 2,
			)
		);

		$this->importer->import(
			$this->conversation(
				array(
					'tags' => array(
						array(
							'id'    => 1,
							'tag'   => 'Photos',
							'color' => '#929499',
						),
						array(
							'id'    => 2,
							'tag'   => ' Needs Review ',
							'color' => '#e52f28',
						),
					),
				)
			),
			$this->mailbox
		);

		$this->assertEqualsCanonicalizing( array( 'photos', 'needs review' ), $this->tag_names() );
		$this->assertSame( 5, (int) \DB::table( 'tags' )->where( 'name', 'photos' )->value( 'counter' ) );
		// The existing tag keeps its color; the new one gets the module's red.
		$this->assertSame( 2, (int) \DB::table( 'tags' )->where( 'name', 'photos' )->value( 'color' ) );
		$this->assertSame( 5, (int) \DB::table( 'tags' )->where( 'name', 'needs review' )->value( 'color' ) );
	}

	/**
	 * Importing again adds HelpScout's new tags, and keeps those agents added; nothing is counted twice.
	 *
	 * @return void
	 */
	public function test_import_again_only_adds_tags(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );
		$conversation_id = $this->conversation_id();
		$agents_tag      = (int) \DB::table( 'tags' )->insertGetId(
			array(
				'name'    => 'escalated',
				'counter' => 1,
			)
		);
		\DB::table( 'conversation_tag' )->insert(
			array(
				'conversation_id' => $conversation_id,
				'tag_id'          => $agents_tag,
			)
		);

		$this->importer->import(
			$this->conversation(
				array(
					'tags' => array(
						array(
							'id'  => 1,
							'tag' => 'photos',
						),
						array(
							'id'  => 3,
							'tag' => 'resolved',
						),
					),
				)
			),
			$this->mailbox
		);

		$this->assertEqualsCanonicalizing( array( 'photos', 'escalated', 'resolved' ), $this->tag_names() );
		$this->assertSame( 1, (int) \DB::table( 'tags' )->where( 'name', 'photos' )->value( 'counter' ) );
	}

	/**
	 * The mailbox gets HelpScout's fields, with dropdowns' options in HelpScout's order, and the conversation its values.
	 *
	 * @return void
	 */
	public function test_custom_fields_are_imported(): void {
		$this->importer->import( $this->conversation( array( 'customFields' => self::values() ) ), $this->mailbox );

		$fields = \DB::table( 'custom_fields' )->where( 'mailbox_id', $this->mailbox->id )->orderBy( 'sort_order' )->get();
		$this->assertSame( array( 'Photo type', 'Taken on', 'Camera' ), $fields->pluck( 'name' )->all() );
		$this->assertSame( array( 1, 5, 2 ), $fields->pluck( 'type' )->map( 'intval' )->all() );
		$this->assertSame(
			array(
				1 => 'Landscape',
				2 => 'Portrait',
			),
			json_decode( (string) $fields[0]->options, true )
		);

		$this->assertSame(
			array(
				'Photo type' => '2',
				'Taken on'   => '2026-08-30',
				'Camera'     => 'Fuji X100',
			),
			$this->values_by_name()
		);
	}

	/**
	 * Importing again reuses the fields, adds dropdown options HelpScout added since, and keeps agents' values.
	 *
	 * @return void
	 */
	public function test_import_again_keeps_agents_values(): void {
		$this->importer->import( $this->conversation( array( 'customFields' => self::values() ) ), $this->mailbox );
		$camera = (int) \DB::table( 'custom_fields' )->where( 'name', 'Camera' )->value( 'id' );
		\DB::table( 'conversation_custom_field' )->where( 'custom_field_id', $camera )->update( array( 'value' => 'Leica' ) );

		$values            = self::values();
		$values[0]['text'] = 'Macro';
		$values[2]['text'] = 'Canon';
		$this->answer_threads( 1002, $this->threads( 100 ) );
		$this->importer->import(
			$this->conversation(
				array(
					'id'           => 1002,
					'customFields' => $values,
				)
			),
			$this->mailbox
		);
		$this->importer->import( $this->conversation( array( 'customFields' => $values ) ), $this->mailbox );

		$this->assertSame( 3, \DB::table( 'custom_fields' )->where( 'mailbox_id', $this->mailbox->id )->count() );
		$this->assertSame( 'Leica', $this->values_by_name()['Camera'] );
		$this->assertSame( '2', $this->values_by_name()['Photo type'] );
		$this->assertSame( 'Macro', json_decode( (string) \DB::table( 'custom_fields' )->where( 'name', 'Photo type' )->value( 'options' ), true )[3] );
		$this->assertCount( 1, $this->helpscout->requests_to( 'v2/mailboxes/77/fields' ) );
	}

	/**
	 * Without HelpScout's field definitions, values are kept as text, in fields with HelpScout's names.
	 *
	 * @return void
	 */
	public function test_values_without_definitions_are_kept_as_text(): void {
		$this->helpscout->only( 'GET', 'v2/mailboxes/77/fields', FakeHelpScout::json( array(), 403 ) );

		$this->importer->import( $this->conversation( array( 'customFields' => self::values() ) ), $this->mailbox );

		$this->assertSame( array( 2 ), \DB::table( 'custom_fields' )->pluck( 'type' )->map( 'intval' )->unique()->values()->all() );
		$this->assertSame( 'Portrait', $this->values_by_name()['Photo type'] );
	}

	/**
	 * Without the modules, tags and values are only kept in the importer's own table.
	 *
	 * @return void
	 */
	public function test_nothing_is_written_without_the_modules(): void {
		PaidModules::switch( Tags::MODULE, false );
		PaidModules::switch( CustomFields::MODULE, false );

		$this->importer->import( $this->conversation( array( 'customFields' => self::values() ) ), $this->mailbox );

		$this->assertSame( 0, \DB::table( 'conversation_tag' )->count() );
		$this->assertSame( 0, \DB::table( 'custom_fields' )->count() );
		$this->assertCount( 0, $this->helpscout->requests_to( 'v2/mailboxes/77/fields' ) );
		$this->assertSame( '["photos"]', ImportedConversation::query()->where( 'helpscout_id', self::CONVERSATION_ID )->value( 'tags' ) );
	}

	/**
	 * HelpScout's values for the fixtures' fields, as a conversation has them.
	 *
	 * @return array[]
	 */
	private static function values(): array {
		return array(
			array(
				'id'    => 104,
				'name'  => 'Photo type',
				'value' => '170',
				'text'  => 'Portrait',
			),
			array(
				'id'    => 105,
				'name'  => 'Taken on',
				'value' => '2026-08-30',
				'text'  => '2026-08-30',
			),
			array(
				'id'    => 106,
				'name'  => 'Camera',
				'value' => 'Fuji X100',
				'text'  => 'Fuji X100',
			),
		);
	}

	/**
	 * The imported conversation's ID.
	 *
	 * @return int
	 */
	private function conversation_id(): int {
		return (int) ImportedConversation::query()->where( 'helpscout_id', self::CONVERSATION_ID )->value( 'conversation_id' );
	}

	/**
	 * The imported conversation's tags' names.
	 *
	 * @return string[]
	 */
	private function tag_names(): array {
		return \DB::table( 'conversation_tag' )
			->join( 'tags', 'tags.id', '=', 'conversation_tag.tag_id' )
			->where( 'conversation_id', $this->conversation_id() )
			->pluck( 'name' )
			->all();
	}

	/**
	 * The imported conversation's values, by their field's name.
	 *
	 * @return string[]
	 */
	private function values_by_name(): array {
		return \DB::table( 'conversation_custom_field' )
			->join( 'custom_fields', 'custom_fields.id', '=', 'conversation_custom_field.custom_field_id' )
			->where( 'conversation_id', $this->conversation_id() )
			->pluck( 'value', 'name' )
			->all();
	}
}
