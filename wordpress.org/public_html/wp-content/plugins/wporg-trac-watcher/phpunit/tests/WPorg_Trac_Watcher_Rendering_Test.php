<?php
/**
 * Tests for how the commits list table renders stored props.
 *
 * @package wporg-trac-watcher
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

/**
 * Covers the props and message columns, which interpolate a stored prop name
 * that anyone able to reach the save handler controls.
 */
class WPorg_Trac_Watcher_Rendering_Test extends WPorg_Trac_Watcher_TestCase {

	/**
	 * A prop name carrying markup.
	 *
	 * @var string
	 */
	const MARKUP_PROP = '<img src=x onerror=alert(1)>';

	/**
	 * A user the props resolve to.
	 *
	 * @var int
	 */
	protected int $alice;

	/**
	 * Creates the user the resolvable props map to.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->alice = $this->factory()->user->create(
			array(
				'user_login'   => 'alice',
				'display_name' => 'Alice Example',
				'role'         => 'editor',
			)
		);
	}

	/*
	 * The props column.
	 */

	/**
	 * An unresolved prop is the case the screen exists to let people fix, so it is
	 * the branch a stored value is most likely to reach.
	 */
	public function test_props_column_escapes_an_unresolved_prop_name(): void {
		$item = $this->make_item( 'Fix the thing.', array( self::MARKUP_PROP => null ) );

		$this->assertMarkupIsInert( $this->render_column( $item, 'props' ) );
	}

	/**
	 * The name is also written into a data attribute the edit dialog reads back.
	 */
	public function test_props_column_escapes_the_prop_data_attribute(): void {
		$item = $this->make_item( 'Fix the thing.', array( self::MARKUP_PROP => null ) );

		$this->assertStringContainsString(
			'data-prop="&lt;img src=x onerror=alert(1)&gt;"',
			$this->render_column( $item, 'props' )
		);
	}

	/**
	 * A resolved prop renders the account's display name instead of the stored name.
	 */
	public function test_props_column_escapes_a_display_name(): void {
		global $wpdb;

		/*
		 * wp_insert_user() runs display_name through sanitize_text_field(), so the
		 * fixture has to be written directly to represent data that predates it or
		 * arrived from profiles.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Deliberately bypassing wp_update_user(), which would sanitise the fixture away.
		$wpdb->update( $wpdb->users, array( 'display_name' => '<b>Alice</b>' ), array( 'ID' => $this->alice ) );
		clean_user_cache( $this->alice );

		$this->assertSame(
			'<b>Alice</b>',
			get_user_by( 'ID', $this->alice )->display_name,
			'The fixture did not take, so the escaping assertion below would pass vacuously.'
		);

		$output = $this->render_column( $this->make_item( 'Fix the thing.', array( 'alice' => $this->alice ) ), 'props' );

		$this->assertStringNotContainsString( '<b>Alice</b>', $output );
		$this->assertStringContainsString( '&lt;b&gt;Alice&lt;/b&gt;', $output );
	}

	/*
	 * The message column.
	 */

	/**
	 * A prop absent from the commit message is appended as a "missed" note, which
	 * happens after format_trac_markup() has already escaped the message.
	 */
	public function test_message_column_escapes_a_missed_prop(): void {
		$item = $this->make_item( 'Fix the thing.', array( self::MARKUP_PROP => null ) );

		$output = $this->render_column( $item, 'message' );

		$this->assertStringContainsString( 'Missed Prop:', $output, 'The prop did not take the missed-prop branch.' );
		$this->assertMarkupIsInert( $output );
	}

	/**
	 * Guards the escaping change in the message column: the prop is escaped before
	 * it is matched against the message, so an ordinary name has to still match.
	 */
	public function test_message_column_still_highlights_a_matching_prop(): void {
		$item = $this->make_item( 'Fix the thing. Props alice.', array( 'alice' => $this->alice ) );

		$this->assertStringContainsString( '<ins>alice</ins>', $this->render_column( $item, 'message' ) );
	}

	/**
	 * A prop that resolves to an account under a different name is shown as a typo.
	 */
	public function test_message_column_marks_a_mis_propped_name(): void {
		$item = $this->make_item( 'Fix the thing. Props Aliec.', array( 'Aliec' => $this->alice ) );

		$this->assertStringContainsString(
			"<del class='replace'>Aliec</del><ins>alice</ins>",
			$this->render_column( $item, 'message' )
		);
	}

	/**
	 * An unresolved prop that does appear in the message is struck through in place,
	 * which is the other branch that interpolates the stored name.
	 */
	public function test_message_column_escapes_a_prop_present_in_the_message(): void {
		$item = $this->make_item( 'Fix the thing. Props ' . self::MARKUP_PROP . '.', array( self::MARKUP_PROP => null ) );

		$this->assertMarkupIsInert( $this->render_column( $item, 'message' ) );
	}

	/*
	 * The edit affordances.
	 */

	/**
	 * The edit links have to track what the save handler will actually accept,
	 * otherwise the UI offers an action that fails.
	 */
	public function test_props_column_offers_editing_to_a_user_who_can_edit_others_posts(): void {
		wp_set_current_user( $this->factory()->user->create( array( 'role' => 'editor' ) ) );

		$output = $this->render_column( $this->make_item( 'Fix the thing.', array( 'bobby' => null ) ), 'props' );

		$this->assertStringContainsString( 'class="edit dashicons', $output );
	}

	/**
	 * An author holds publish_posts, which is what the handler used to require.
	 */
	public function test_props_column_withholds_editing_from_an_author(): void {
		$author = $this->factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );

		$this->assertTrue( user_can( $author, 'publish_posts' ), 'The fixture no longer represents the role that used to pass the old check.' );

		$output = $this->render_column( $this->make_item( 'Fix the thing.', array( 'bobby' => null ) ), 'props' );

		$this->assertStringNotContainsString( 'class="edit dashicons', $output );
		$this->assertStringNotContainsString( 'class="reparse"', $output );
	}
}
