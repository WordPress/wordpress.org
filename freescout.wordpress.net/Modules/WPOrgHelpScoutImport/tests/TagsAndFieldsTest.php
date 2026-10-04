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
							'id'       => 105,
							'name'     => 'Taken on',
							'type'     => 'date',
							'order'    => 2,
							'required' => true,
						),
						array(
							'id'    => 107,
							'name'  => 'Rolls',
							'type'  => 'number',
							'order' => 4,
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
	 * Importing again leaves away tags agents removed, or administrators deleted, rather than adding them again.
	 *
	 * @return void
	 */
	public function test_import_again_keeps_tags_agents_removed(): void {
		$tags = array(
			'tags' => array(
				array(
					'id'  => 1,
					'tag' => 'photos',
				),
				array(
					'id'  => 2,
					'tag' => 'urgent',
				),
			),
		);
		$this->importer->import( $this->conversation( $tags ), $this->mailbox );
		$urgent = (int) \DB::table( 'tags' )->where( 'name', 'urgent' )->value( 'id' );
		\DB::table( 'conversation_tag' )->where( 'tag_id', $urgent )->delete();
		\DB::table( 'tags' )->where( 'name', 'photos' )->delete();
		\DB::table( 'conversation_tag' )->where( 'conversation_id', $this->conversation_id() )->delete();

		$this->importer->import( $this->conversation( $tags ), $this->mailbox );

		$this->assertSame( array(), $this->tag_names() );
		$this->assertFalse( \DB::table( 'tags' )->where( 'name', 'photos' )->exists() );
	}

	/**
	 * Importing again takes away tags HelpScout removed, if the import added them; those agents added stay.
	 *
	 * @return void
	 */
	public function test_import_again_removes_tags_helpscout_removed(): void {
		$agents_tag = (int) \DB::table( 'tags' )->insertGetId(
			array(
				'name'    => 'escalated',
				'counter' => 0,
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
							'id'  => 4,
							'tag' => 'urgent',
						),
					),
				)
			),
			$this->mailbox
		);
		\DB::table( 'conversation_tag' )->insert(
			array(
				'conversation_id' => $this->conversation_id(),
				'tag_id'          => $agents_tag,
			)
		);

		// HelpScout added the tag the agent had, then removed it, and the import's.
		$this->importer->import(
			$this->conversation(
				array(
					'tags' => array(
						array(
							'id'  => 1,
							'tag' => 'photos',
						),
						array(
							'id'  => 5,
							'tag' => 'escalated',
						),
					),
				)
			),
			$this->mailbox
		);
		$this->importer->import( $this->conversation(), $this->mailbox );

		$this->assertEqualsCanonicalizing( array( 'photos', 'escalated' ), $this->tag_names() );
		$this->assertSame( 0, (int) \DB::table( 'tags' )->where( 'name', 'urgent' )->value( 'counter' ) );
	}

	/**
	 * Tags given as names, rather than objects, are imported too.
	 *
	 * @return void
	 */
	public function test_tags_given_as_names_are_imported(): void {
		$this->importer->import( $this->conversation( array( 'tags' => array( 'Photos', array( 'name' => 'Urgent' ) ) ) ), $this->mailbox );

		$this->assertEqualsCanonicalizing( array( 'photos', 'urgent' ), $this->tag_names() );
		$this->assertSame( '["Photos","Urgent"]', ImportedConversation::query()->where( 'helpscout_id', self::CONVERSATION_ID )->value( 'tags' ) );
	}

	/**
	 * The mailbox gets HelpScout's fields, with dropdowns' options in HelpScout's order, and the conversation its values.
	 *
	 * @return void
	 */
	public function test_custom_fields_are_imported(): void {
		$this->importer->import( $this->conversation( array( 'customFields' => self::values() ) ), $this->mailbox );

		$fields = \DB::table( 'custom_fields' )->where( 'mailbox_id', $this->mailbox->id )->orderBy( 'sort_order' )->get();
		$this->assertSame( array( 'Photo type', 'Taken on', 'Camera', 'Rolls' ), $fields->pluck( 'name' )->all() );
		$this->assertSame( array( 1, 5, 2, 4 ), $fields->pluck( 'type' )->map( 'intval' )->all() );
		$this->assertSame( array( false, true, false, false ), array_map( 'boolval', $fields->pluck( 'required' )->all() ) );
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
	 * Importing again reuses the fields, adds dropdown options HelpScout added since, and brings values up to date,
	 * except those agents changed.
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
		unset( $values[1] );
		$this->importer->import( $this->conversation( array( 'customFields' => array_values( $values ) ) ), $this->mailbox );

		$this->assertSame( 4, \DB::table( 'custom_fields' )->where( 'mailbox_id', $this->mailbox->id )->count() );
		$this->assertSame( 'Macro', json_decode( (string) \DB::table( 'custom_fields' )->where( 'name', 'Photo type' )->value( 'options' ), true )[3] );
		$this->assertSame(
			array(
				'Photo type' => '3',
				'Camera'     => 'Leica',
			),
			$this->values_by_name()
		);
		$this->assertCount( 1, $this->helpscout->requests_to( 'v2/mailboxes/77/fields' ) );
	}

	/**
	 * The mailbox gets all of HelpScout's fields, even those no conversation has a value for.
	 *
	 * @return void
	 */
	public function test_fields_without_values_are_created(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );

		$this->assertSame( array( 'Photo type', 'Taken on', 'Camera', 'Rolls' ), \DB::table( 'custom_fields' )->orderBy( 'sort_order' )->pluck( 'name' )->all() );
	}

	/**
	 * Importing again doesn't create fields, or add dropdown options, deleted in FreeScout.
	 *
	 * @return void
	 */
	public function test_import_again_keeps_fields_and_options_deleted(): void {
		$this->importer->import( $this->conversation( array( 'customFields' => self::values() ) ), $this->mailbox );
		$camera = (int) \DB::table( 'custom_fields' )->where( 'name', 'Camera' )->value( 'id' );
		\DB::table( 'custom_fields' )->where( 'id', $camera )->delete();
		\DB::table( 'conversation_custom_field' )->where( 'custom_field_id', $camera )->delete();

		// Like the module does when an option is deleted.
		$photo_type = \DB::table( 'custom_fields' )->where( 'name', 'Photo type' )->first();
		\DB::table( 'custom_fields' )->where( 'id', $photo_type->id )->update( array( 'options' => json_encode( array( 1 => 'Landscape' ) ) ) );
		\DB::table( 'conversation_custom_field' )->where( 'custom_field_id', $photo_type->id )->delete();

		$this->answer_threads( 1002, $this->threads( 100 ) );
		$this->importer->import(
			$this->conversation(
				array(
					'id'           => 1002,
					'customFields' => self::values(),
				)
			),
			$this->mailbox
		);

		$this->assertFalse( \DB::table( 'custom_fields' )->where( 'name', 'Camera' )->exists() );
		$this->assertSame( array( 1 => 'Landscape' ), json_decode( (string) \DB::table( 'custom_fields' )->where( 'id', $photo_type->id )->value( 'options' ), true ) );
		$this->assertSame( array( 'Taken on' => '2026-08-30' ), $this->values_by_name( 1002 ) );
	}

	/**
	 * Numbers and dates that aren't ones are left out.
	 *
	 * @return void
	 */
	public function test_values_that_are_not_numbers_or_dates_are_left_out(): void {
		$values = array(
			array(
				'id'   => 105,
				'text' => 'next friday',
			),
			array(
				'id'   => 107,
				'text' => '12 rolls',
			),
		);
		$this->importer->import( $this->conversation( array( 'customFields' => $values ) ), $this->mailbox );

		$this->assertSame( array(), $this->values_by_name() );

		$values[0]['text'] = '2026-08-30T10:00:00Z';
		$values[1]['text'] = '12';
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

		$this->assertSame(
			array(
				'Taken on' => '2026-08-30',
				'Rolls'    => '12',
			),
			$this->values_by_name( 1002 )
		);
	}

	/**
	 * A conversation HelpScout moved, which agents didn't work on, takes its values to the other mailbox's fields.
	 *
	 * @return void
	 */
	public function test_moved_conversation_leaves_no_values_behind(): void {
		$this->importer->import( $this->conversation( array( 'customFields' => self::values() ) ), $this->mailbox );
		$themes = $this->create_mailbox( 'Themes' );

		$this->importer->import( $this->conversation( array( 'customFields' => self::values() ) ), $themes );

		$fields = \DB::table( 'conversation_custom_field' )
			->join( 'custom_fields', 'custom_fields.id', '=', 'conversation_custom_field.custom_field_id' )
			->where( 'conversation_id', $this->conversation_id() )
			->pluck( 'mailbox_id' )
			->map( 'intval' )
			->unique()
			->all();
		$this->assertSame( array( (int) $themes->id ), array_values( $fields ) );
	}

	/**
	 * A conversation HelpScout moved, but agents worked on, keeps its values in the fields of the mailbox it stays in.
	 *
	 * @return void
	 */
	public function test_moved_conversation_agents_worked_on_keeps_its_mailbox_fields(): void {
		$this->importer->import( $this->conversation(), $this->mailbox );
		$this->create_thread( \App\Conversation::find( $this->conversation_id() ), \App\Thread::TYPE_NOTE, 'On it.', $this->agent, '2026-09-10 08:00:00' );
		$themes = $this->create_mailbox( 'Themes' );

		$this->importer->import( $this->conversation( array( 'customFields' => self::values() ) ), $themes );

		$this->assertSame( 0, \DB::table( 'custom_fields' )->where( 'mailbox_id', $themes->id )->count() );
		$this->assertSame( 4, \DB::table( 'custom_fields' )->where( 'mailbox_id', $this->mailbox->id )->count() );
		$this->assertSame( 'Fuji X100', $this->values_by_name()['Camera'] );
	}

	/**
	 * A multiselect dropdown FreeScout has by a field's name keeps HelpScout's value as one of its options' labels.
	 *
	 * @return void
	 */
	public function test_value_in_a_multiselect_dropdown_is_an_option(): void {
		\DB::table( 'custom_fields' )->insert(
			array(
				'mailbox_id' => $this->mailbox->id,
				'name'       => 'Photo type',
				'type'       => 8,
				'options'    => json_encode(
					array(
						1 => 'Portrait',
						2 => 'Other',
					)
				),
			)
		);

		$this->importer->import( $this->conversation( array( 'customFields' => self::values() ) ), $this->mailbox );

		$this->assertSame( 'Portrait', $this->values_by_name()['Photo type'] );
		// HelpScout's other option is added.
		$this->assertContains( 'Landscape', json_decode( (string) \DB::table( 'custom_fields' )->where( 'name', 'Photo type' )->value( 'options' ), true ) );
	}

	/**
	 * Fields FreeScout has by the name, which split their values at commas, get HelpScout's values without them.
	 *
	 * @return void
	 */
	public function test_values_in_fields_split_at_commas_have_none(): void {
		foreach ( array(
			'Photo type' => 8,
			'Camera'     => 7,
		) as $name => $type ) {
			\DB::table( 'custom_fields' )->insert(
				array(
					'mailbox_id' => $this->mailbox->id,
					'name'       => $name,
					'type'       => $type,
					'options'    => 8 === $type ? json_encode( array( 1 => 'Portrait' ) ) : null,
				)
			);
		}

		$values            = self::values();
		$values[0]['text'] = 'Square, cropped';
		$values[2]['text'] = 'Fuji, X100';
		$this->importer->import( $this->conversation( array( 'customFields' => $values ) ), $this->mailbox );

		$this->assertSame( 'Square cropped', $this->values_by_name()['Photo type'] );
		$this->assertSame( 'Fuji X100', $this->values_by_name()['Camera'] );
		$this->assertContains( 'Square cropped', json_decode( (string) \DB::table( 'custom_fields' )->where( 'name', 'Photo type' )->value( 'options' ), true ) );
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
	 * An imported conversation's values, by their field's name.
	 *
	 * @param int $helpscout_id HelpScout conversation ID.
	 * @return string[]
	 */
	private function values_by_name( int $helpscout_id = self::CONVERSATION_ID ): array {
		return \DB::table( 'conversation_custom_field' )
			->join( 'custom_fields', 'custom_fields.id', '=', 'conversation_custom_field.custom_field_id' )
			->where( 'conversation_id', (int) ImportedConversation::query()->where( 'helpscout_id', $helpscout_id )->value( 'conversation_id' ) )
			->pluck( 'value', 'name' )
			->all();
	}
}
