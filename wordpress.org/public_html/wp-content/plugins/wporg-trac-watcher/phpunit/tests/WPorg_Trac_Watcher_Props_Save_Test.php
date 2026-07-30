<?php // phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit only finds a test class in a file named after it.
/**
 * Tests for the props save handler.
 *
 * The handler's rejection paths call die() rather than wp_die(), so they cannot
 * be exercised in process -- a refused request would take the test runner down
 * with it. What the capability actually gates is covered from the rendering side
 * instead, in WPorg_Trac_Watcher_Rendering_Test.
 *
 * @package wporg-trac-watcher
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || die();

/**
 * Covers what reaches the database when a prop is added or edited.
 */
class WPorg_Trac_Watcher_Props_Save_Test extends WPorg_Trac_Watcher_TestCase {

	/**
	 * Signs the request in as someone the handler will accept.
	 */
	public function setUp(): void {
		parent::setUp();

		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'editor' ) ) );

		$this->seed_revision( 'Fix the thing.' );
	}

	/**
	 * Runs the save handler with the given request.
	 *
	 * @param array $request Request arguments, merged over the required ones.
	 * @return void
	 */
	protected function save( array $request ): void {
		$_REQUEST = array_merge(
			array(
				'svn'      => 'core',
				'revision' => self::REVISION,
				'user_id'  => '',
				'_wpnonce' => wp_create_nonce( 'edit_svn_prop' ),
			),
			$request
		);

		// The handler ends by echoing the replacement row.
		ob_start();
		do_action( 'admin_post_svn_save' );
		ob_end_clean();
	}

	/**
	 * Returns the prop names stored against the seeded revision.
	 *
	 * @return string[]
	 */
	protected function stored_prop_names(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table name comes from get_svns(), and the plugin's tables have no caching layer.
		return $wpdb->get_col( $wpdb->prepare( "SELECT prop_name FROM {$this->svn['props_table']} WHERE revision = %d", self::REVISION ) );
	}

	/**
	 * Markup submitted as a new prop name is stripped before it is stored.
	 */
	public function test_adding_a_prop_strips_markup_from_the_name(): void {
		$this->save(
			array(
				'what'      => 'add',
				'prop_name' => 'alice<img src=x onerror=alert(1)>',
			)
		);

		$this->assertSame( array( 'alice' ), $this->stored_prop_names() );
	}

	/**
	 * The name also falls back to the user_id field when the first is left blank,
	 * which is a second way the same value reaches the table.
	 */
	public function test_adding_a_prop_strips_markup_from_the_fallback_field(): void {
		$this->save(
			array(
				'what'      => 'add',
				'prop_name' => '',
				'user_id'   => 'bobby<b>x</b>',
			)
		);

		$this->assertSame( array( 'bobbyx' ), $this->stored_prop_names() );
	}

	/**
	 * Editing an existing prop goes through a separate assignment.
	 */
	public function test_editing_a_prop_strips_markup_from_the_name(): void {
		$this->seed_props( array( 'bobby' => null ) );

		$this->save(
			array(
				'what'           => 'edit',
				'prop_name_orig' => 'bobby',
				'prop_name'      => 'robert<img src=x onerror=alert(1)>',
			)
		);

		$this->assertSame( array( 'robert' ), $this->stored_prop_names() );
	}

	/**
	 * Sanitising on the way in does not make the output escaping redundant: rows
	 * predating it are still in the table, so the renderer has to hold on its own.
	 */
	public function test_a_row_that_predates_sanitisation_still_renders_inert(): void {
		$this->seed_props( array( '<img src=x onerror=alert(1)>' => null ) );

		$table = new WordPressdotorg\Trac\Watcher\Commits_List_Table( $this->svn );
		$table->prepare_items( array( 'revision' => self::REVISION ) );

		$this->assertNotEmpty( $table->items, 'The seeded revision was not loaded, so nothing was rendered.' );

		$this->assertMarkupIsInert( $table->column_default( $table->items[0], 'props' ) );
	}
}
