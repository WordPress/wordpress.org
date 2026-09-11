<?php
/**
 * Tests for the component page content rendered onto the_content.
 *
 * @package trac-notifications
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

// phpcs:disable WordPress.DB.DirectDatabaseQuery

/**
 * Renders a component page through the_content and inspects the output.
 */
class WPorg_Trac_Components_Rendering_Test extends WPorg_Trac_Components_TestCase {

	/**
	 * The editor who authors the component page.
	 *
	 * @var int
	 */
	protected int $editor;

	/**
	 * The contributor listed as the component's maintainer.
	 *
	 * @var int
	 */
	protected int $maintainer;

	/**
	 * The component page.
	 *
	 * @var int
	 */
	protected int $component;

	/**
	 * Builds a published component page maintained by one contributor, and stubs the
	 * Trac API so the ticket and follower sections resolve without HTTP.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->editor     = $this->factory->user->create( array( 'role' => 'editor' ) );
		$this->maintainer = $this->factory->user->create( array( 'role' => 'contributor' ) );

		$this->component = $this->factory->post->create(
			array(
				'post_type'    => Make_Core_Trac_Components::POST_TYPE_NAME,
				'post_status'  => 'publish',
				'post_author'  => $this->editor,
				'post_title'   => 'Media',
				'post_content' => 'Original component body.',
			)
		);

		update_post_meta( $this->component, '_active_maintainers', get_userdata( $this->maintainer )->user_login );

		$this->components->api = new class() {
			/**
			 * Returns an empty result for any Trac API call.
			 *
			 * @param string $name      The method called.
			 * @param array  $arguments The call arguments.
			 * @return array
			 */
			public function __call( $name, $arguments ) {
				return array();
			}
		};
	}

	/**
	 * Stores the maintainer's display name directly, bypassing profile write filters,
	 * to exercise the render path in isolation.
	 *
	 * @param string $value The display name to store.
	 */
	protected function set_display_name( string $value ): void {
		global $wpdb;
		$wpdb->update( $wpdb->users, array( 'display_name' => $value ), array( 'ID' => $this->maintainer ) );
		clean_user_cache( $this->maintainer );
	}

	/**
	 * Renders the component page through the full the_content filter chain.
	 *
	 * @return string
	 */
	protected function render_component(): string {
		$query = new WP_Query();
		$query->query(
			array(
				'p'         => $this->component,
				'post_type' => Make_Core_Trac_Components::POST_TYPE_NAME,
			)
		);

		// phpcs:disable WordPress.WP.GlobalVariablesOverride.Prohibited -- Point the main query at the component for this render.
		$GLOBALS['wp_query']     = $query;
		$GLOBALS['wp_the_query'] = $query;
		// phpcs:enable WordPress.WP.GlobalVariablesOverride.Prohibited

		// The callback requires wp_head to have fired.
		do_action( 'wp_head' );

		$html = '';
		while ( $query->have_posts() ) {
			$query->the_post();
			$html = apply_filters( 'the_content', get_the_content() );
		}
		wp_reset_postdata();

		return $html;
	}

	/**
	 * The maintainer section renders for a component with a listed maintainer.
	 */
	public function test_the_callback_renders_the_maintainer_block(): void {
		$this->set_display_name( 'Plain Name' );

		$html = $this->render_component();

		$this->assertStringContainsString( 'component-info', $html );
		$this->assertStringContainsString( 'Plain Name', $html );
	}

	/**
	 * The maintainer display name is HTML-escaped in the output.
	 */
	public function test_maintainer_display_name_is_escaped(): void {
		$this->set_display_name( 'Ann <b>Smith</b>' );

		$html = $this->render_component();

		$this->assertStringContainsString( 'Ann &lt;b&gt;Smith&lt;/b&gt;', $html );
		$this->assertStringNotContainsString( '<b>Smith</b>', $html );
	}

	/**
	 * A shortcode in the display name is rendered as text, not executed.
	 */
	public function test_shortcode_in_display_name_is_not_executed(): void {
		$this->set_display_name( 'Eve [caption width=300 caption=CAP]body[/caption]' );

		$html = $this->render_component();

		$this->assertStringContainsString( '[caption width=300 caption=CAP]body[/caption]', $html );
		$this->assertStringNotContainsString( 'wp-caption', $html );
	}

	/**
	 * The block is appended after the shortcode pass.
	 */
	public function test_the_content_runs_after_the_shortcode_pass(): void {
		$plugin_priority = has_filter( 'the_content', array( $this->components, 'the_content' ) );

		$this->assertSame( 99, $plugin_priority );
		$this->assertGreaterThan( has_filter( 'the_content', 'do_shortcode' ), $plugin_priority );
	}
}
