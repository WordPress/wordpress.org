<?php
/**
 * Tests for handbook breadcrumb output.
 *
 * @package handbook
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

/**
 * Tests that the breadcrumb trail never discloses an ancestor the visitor
 * cannot read.
 */
class WPorg_Handbook_Breadcrumbs_Test extends WPorg_Handbook_TestCase {

	/**
	 * The published handbook page being viewed.
	 *
	 * @var int
	 */
	protected int $child_id;

	/**
	 * The parent section, left unpublished in most tests.
	 *
	 * @var int
	 */
	protected int $parent_id;

	/**
	 * Title of the parent section, asserted present or absent in the trail.
	 *
	 * @var string
	 */
	const PARENT_TITLE = 'Secret Section Title';

	/**
	 * Registers the handbook and its pages.
	 */
	public function setUp(): void {
		parent::setUp();

		WPorg_Handbook_Init::init();

		$this->parent_id = $this->factory->post->create(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'draft',
				'post_title'  => self::PARENT_TITLE,
			)
		);

		$this->child_id = $this->factory->post->create(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'publish',
				'post_title'  => 'Published Child Page',
				'post_parent' => $this->parent_id,
			)
		);
	}

	/**
	 * Discards the handbook objects registered for this test.
	 */
	public function tearDown(): void {
		parent::tearDown();

		// Reset the widget flag this class forces on, so it doesn't leak into later tests.
		$using_widget = new ReflectionProperty( 'WPorg_Handbook_Breadcrumbs', 'using_pages_widget' );
		$using_widget->setAccessible( true );
		$using_widget->setValue( null, false );

		WPorg_Handbook_Init::reset( true );
	}

	/**
	 * Renders the breadcrumb trail for the child page as the current user.
	 *
	 * The pages-widget flag the output depends on is set directly, since a
	 * registered widget isn't otherwise present in the test environment.
	 *
	 * @return string The breadcrumb markup.
	 */
	protected function render_breadcrumbs(): string {
		$this->go_to( '?post_type=handbook&p=' . $this->child_id );
		if ( have_posts() ) {
			the_post();
		}

		$using_widget = new ReflectionProperty( 'WPorg_Handbook_Breadcrumbs', 'using_pages_widget' );
		$using_widget->setAccessible( true );
		$using_widget->setValue( null, true );

		ob_start();
		WPorg_Handbook_Breadcrumbs::output_breadcrumbs();
		return (string) ob_get_clean();
	}

	/**
	 * A draft ancestor's title and post ID must not appear in the trail.
	 */
	public function test_draft_ancestor_is_not_disclosed(): void {
		wp_set_current_user( 0 );

		$html = $this->render_breadcrumbs();

		$this->assertStringContainsString( 'handbook-breadcrumbs', $html, 'No breadcrumb was rendered to test.' );
		$this->assertStringNotContainsString( self::PARENT_TITLE, $html );
		$this->assertStringNotContainsString( 'p=' . $this->parent_id, $html );
	}

	/**
	 * A private ancestor is likewise withheld, including its "Private:" title.
	 */
	public function test_private_ancestor_is_not_disclosed(): void {
		wp_update_post(
			array(
				'ID'          => $this->parent_id,
				'post_status' => 'private',
			)
		);

		wp_set_current_user( 0 );
		$html = $this->render_breadcrumbs();

		$this->assertStringNotContainsString( self::PARENT_TITLE, $html );
		$this->assertStringNotContainsString( 'p=' . $this->parent_id, $html );
	}

	/**
	 * A published ancestor is still shown, so the fix doesn't hide legitimate
	 * trail entries.
	 */
	public function test_published_ancestor_is_shown(): void {
		wp_update_post(
			array(
				'ID'          => $this->parent_id,
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( 0 );
		$html = $this->render_breadcrumbs();

		$this->assertStringContainsString( self::PARENT_TITLE, $html );
	}
}
