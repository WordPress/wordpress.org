<?php
/**
 * Tests for Import::rebuild_affected_zips() on plugins with an empty trunk.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests that tag-only plugins don't request a trunk ZIP, which can't be built
 * from an empty trunk and fails the whole build when it's the only version.
 *
 * Builder::build() bails early without PLUGIN_ZIP_SVN_URL, so a `true` return
 * means the Builder was reached and a `false` one means nothing was left to build.
 *
 * @group jobs
 */
#[Group( 'jobs' )]
class Import_Empty_Trunk_Zip_Test extends TestCase {

	/**
	 * The plugin post under test.
	 *
	 * @var \WP_Post
	 */
	private static \WP_Post $plugin;

	/**
	 * Create a published plugin.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'empty-trunk-zip-test-' . wp_rand(),
				'post_title'  => 'Empty Trunk ZIP Test Plugin',
				'post_status' => 'publish',
			)
		);

		self::assertInstanceOf( \WP_Post::class, $plugin );
		self::$plugin = $plugin;
	}

	/**
	 * Delete the plugin post.
	 */
	public static function tearDownAfterClass(): void {
		wp_delete_post( self::$plugin->ID, true );

		parent::tearDownAfterClass();
	}

	/**
	 * Runs rebuild_affected_zips() for the given changed tags.
	 *
	 * @param bool  $trunk_has_files Whether the plugin's trunk has files.
	 * @param array $changed_tags    The tags the import was triggered for.
	 * @return bool Whether any versions were handed to the Builder.
	 */
	private function rebuild( bool $trunk_has_files, array $changed_tags ): bool {
		$importer = new Import();

		( new ReflectionProperty( Import::class, 'trunk_has_files' ) )->setValue( $importer, $trunk_has_files );

		return ( new ReflectionMethod( Import::class, 'rebuild_affected_zips' ) )->invoke(
			$importer,
			self::$plugin->post_name,
			'1.0',
			'1.0',
			$changed_tags
		);
	}

	/**
	 * An empty trunk is not built.
	 */
	public function test_skips_trunk_when_empty(): void {
		$this->assertFalse( $this->rebuild( false, array( 'trunk' ) ) );
	}

	/**
	 * Tags are still built alongside an empty trunk.
	 */
	public function test_builds_tags_when_trunk_empty(): void {
		$this->assertTrue( $this->rebuild( false, array( 'trunk', '1.0' ) ) );
	}

	/**
	 * A trunk with files is built.
	 */
	public function test_builds_trunk_with_files(): void {
		$this->assertTrue( $this->rebuild( true, array( 'trunk' ) ) );
	}
}
