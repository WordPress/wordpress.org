<?php
/**
 * Tests for handbook previous/next page navigation.
 *
 * @package handbook
 */

defined( 'ABSPATH' ) || die();

/**
 * Tests that adjacent page lookups respect what the current user may read.
 */
class WPorg_Handbook_Navigation_Test extends WPorg_Handbook_TestCase {

	/**
	 * The handbook page acting as the parent of the siblings under test.
	 *
	 * @var int
	 */
	protected $parent_id;

	/**
	 * A published handbook page, the one being navigated from.
	 *
	 * @var int
	 */
	protected $published_id;

	/**
	 * A private handbook page, sitting immediately after the published one.
	 *
	 * @var int
	 */
	protected $private_id;

	/**
	 * Title of the private page, used to assert whether it was offered.
	 *
	 * @var string
	 */
	const PRIVATE_TITLE = 'Private Handbook Page';

	/**
	 * Creates a handbook parent with a published and a private child page.
	 */
	public function setUp(): void {
		parent::setUp();

		WPorg_Handbook_Init::init();

		$this->parent_id = $this->factory->post->create(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'publish',
				'post_title'  => 'Team Handbook',
			)
		);

		$this->published_id = $this->factory->post->create(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'publish',
				'post_title'  => 'Meeting Notes',
				'post_parent' => $this->parent_id,
				'menu_order'  => 1,
			)
		);

		$this->private_id = $this->factory->post->create(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'private',
				'post_title'  => self::PRIVATE_TITLE,
				'post_parent' => $this->parent_id,
				'menu_order'  => 2,
			)
		);
	}

	/**
	 * Discards the handbook objects registered for this test.
	 */
	public function tearDown(): void {
		parent::tearDown();

		WPorg_Handbook_Init::reset( true );
	}

	/**
	 * Calls the protected method that resolves adjacent handbook pages.
	 *
	 * @param int $post_id The post to find adjacent pages for.
	 * @return array The previous and next page.
	 */
	protected function get_adjacent_posts( $post_id ) {
		$method = new ReflectionMethod( 'WPorg_Handbook_Navigation', 'get_adjacent_posts_via_handbook_pages_widget' );
		$method->setAccessible( true );

		return $method->invoke( null, $post_id );
	}

	/**
	 * Returns the ID of a user able to read private handbook pages.
	 *
	 * @return int
	 */
	protected function create_handbook_editor() {
		return $this->factory->user->create( array( 'role' => 'editor' ) );
	}

	/**
	 * Confirms the fixture user really does hold the capability under test.
	 */
	public function test_editor_can_read_private_handbook_pages() {
		wp_set_current_user( $this->create_handbook_editor() );

		$this->assertTrue( current_user_can( get_post_type_object( 'handbook' )->cap->read_private_posts ) );
	}

	/**
	 * A user who can read private posts is navigated to the private sibling.
	 */
	public function test_private_sibling_is_offered_to_reader_of_private_posts() {
		wp_set_current_user( $this->create_handbook_editor() );

		list( $prev, $next ) = $this->get_adjacent_posts( $this->published_id );

		$this->assertNotFalse( $next );
		$this->assertStringContainsString( self::PRIVATE_TITLE, $next->title );
	}

	/**
	 * A user who cannot read private posts is not offered the private sibling.
	 */
	public function test_private_sibling_is_not_offered_to_non_reader_of_private_posts() {
		wp_set_current_user( 0 );

		list( $prev, $next ) = $this->get_adjacent_posts( $this->published_id );

		$this->assertFalse( $next );
	}

	/**
	 * A cache entry populated by a reader of private posts is not reused for
	 * someone who cannot read them.
	 */
	public function test_cache_populated_by_reader_of_private_posts_is_not_reused() {
		wp_set_current_user( $this->create_handbook_editor() );
		$this->get_adjacent_posts( $this->published_id );

		wp_set_current_user( 0 );
		list( $prev, $next ) = $this->get_adjacent_posts( $this->published_id );

		$this->assertFalse( $next, 'A private page was served from the cache to a user who cannot read it.' );
	}

	/**
	 * And the same in reverse, so that populating the cache first never
	 * decides what a later request with different capabilities sees.
	 */
	public function test_cache_populated_by_non_reader_of_private_posts_is_not_reused() {
		wp_set_current_user( 0 );
		$this->get_adjacent_posts( $this->published_id );

		wp_set_current_user( $this->create_handbook_editor() );
		list( $prev, $next ) = $this->get_adjacent_posts( $this->published_id );

		$this->assertNotFalse( $next );
		$this->assertStringContainsString( self::PRIVATE_TITLE, $next->title );
	}
}
