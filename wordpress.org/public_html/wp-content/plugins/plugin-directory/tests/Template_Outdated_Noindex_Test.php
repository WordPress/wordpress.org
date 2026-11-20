<?php
/**
 * Tests how far behind a plugin's "Tested up to" can fall before it is noindexed.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * Covers the 5-major cutoff for noindexing single plugin pages.
 *
 * Extends the plain PHPUnit TestCase: WP_UnitTestCase is not compatible with
 * the PHPUnit 11 runner used by this suite.
 */
class Template_Outdated_Noindex_Test extends TestCase {

	/**
	 * Counter to give every test plugin a unique slug.
	 *
	 * @var int
	 */
	private static int $plugin_count = 0;

	/**
	 * The plugin post under test.
	 *
	 * @var WP_Post|null
	 */
	private ?WP_Post $plugin = null;

	/**
	 * Removes the plugin fixture and restores the main query.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( $this->plugin ) {
			wp_delete_post( $this->plugin->ID, true );
			$this->plugin = null;
		}

		// phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- The test replaces the main query, not just the post.
		wp_reset_query();

		parent::tearDown();
	}

	/**
	 * Creates a published plugin tested up to a number of majors behind the current one.
	 *
	 * @param int $majors_behind How many major releases "Tested up to" lags the current one.
	 * @return WP_Post The plugin post.
	 */
	private function create_plugin( int $majors_behind ): WP_Post {
		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'outdated-noindex-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Outdated Noindex Test Plugin',
				'post_status' => 'publish',
			)
		);

		$this->assertInstanceOf( WP_Post::class, $plugin );
		$this->plugin = $plugin;

		$tested = (string) ( Template::get_current_major_wp_version() - $majors_behind / 10 );
		update_post_meta( $plugin->ID, 'tested', $tested );

		return $plugin;
	}

	/**
	 * Majors behind and whether the single plugin page is noindexed.
	 *
	 * @return array<string, array{0: int, 1: bool}>
	 */
	public static function data_noindex(): array {
		return array(
			'4 majors behind is indexed'   => array( 4, false ),
			'5 majors behind is noindexed' => array( 5, true ),
		);
	}

	/**
	 * Tests that a single plugin page stays indexed until it falls 5 majors behind.
	 *
	 * @dataProvider data_noindex
	 *
	 * @param int  $majors_behind How many major releases "Tested up to" lags the current one.
	 * @param bool $expected      Whether the request should be noindexed.
	 * @return void
	 */
	#[DataProvider( 'data_noindex' )]
	public function test_noindex_cutoff_is_five_majors( int $majors_behind, bool $expected ): void {
		global $wp_query, $post;

		$plugin = $this->create_plugin( $majors_behind );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Simulates the single plugin page request.
		$wp_query = new WP_Query(
			array(
				'p'         => $plugin->ID,
				'post_type' => 'plugin',
			)
		);
		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- The template reads the global post.
		$post = $wp_query->post;

		$this->assertSame( $expected, Template::should_noindex_request( false ) );
	}
}
