<?php
/**
 * Tests the WordPress and PHP versions a custom plugin blueprint is served with.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Covers the preferredVersions override in the `blueprint.json` route.
 *
 * @group api
 */
#[Group( 'api' )]
class Plugin_Blueprint_Versions_Test extends TestCase {

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
	 * Creates a fresh REST server, which registers the route.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		global $wp_rest_server;

		parent::setUp();

		$wp_rest_server = null;
		rest_get_server();
	}

	/**
	 * Removes the plugin fixture.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		global $wp_rest_server;

		if ( $this->plugin ) {
			wp_delete_post( $this->plugin->ID, true );
			$this->plugin = null;
		}

		$wp_rest_server = null;

		parent::tearDown();
	}

	/**
	 * Pairs of a stored preferred PHP version and the one the blueprint is served with.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function data_php_versions(): array {
		return array(
			'older than Requires PHP is raised' => array( '7.4', '8.1' ),
			'newer than Requires PHP is kept'   => array( '8.3', '8.3' ),
		);
	}

	/**
	 * A custom blueprint previews on the latest WordPress, and on a PHP version the plugin supports.
	 *
	 * @param string $stored_php   The preferred PHP version in the stored blueprint.
	 * @param string $expected_php The preferred PHP version the blueprint is served with.
	 * @return void
	 */
	#[DataProvider( 'data_php_versions' )]
	public function test_preferred_versions_are_overridden( string $stored_php, string $expected_php ): void {
		$this->plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'blueprint-versions-' . ( ++self::$plugin_count ),
				'post_title'  => 'Blueprint Versions Test Plugin',
				'post_status' => 'publish',
			)
		);

		$this->assertInstanceOf( WP_Post::class, $this->plugin );

		update_post_meta( $this->plugin->ID, 'requires_php', '8.1.0' );
		update_post_meta(
			$this->plugin->ID,
			'assets_blueprints',
			array(
				'blueprint.json' => array(
					'filename' => 'blueprint.json',
					'revision' => 1,
					'contents' => wp_json_encode(
						array(
							'landingPage'       => '/wp-admin/plugins.php',
							'preferredVersions' => array(
								'php' => $stored_php,
								'wp'  => '6.4',
							),
						)
					),
				),
			)
		);

		$response = rest_do_request(
			new WP_REST_Request( 'GET', '/plugins/v1/plugin/' . $this->plugin->post_name . '/blueprint.json' )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertEquals(
			(object) array(
				'php' => $expected_php,
				'wp'  => 'latest',
			),
			$response->get_data()->preferredVersions // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Blueprint schema key.
		);
	}
}
