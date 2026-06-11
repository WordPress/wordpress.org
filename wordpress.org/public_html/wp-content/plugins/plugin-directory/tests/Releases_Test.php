<?php
/**
 * Tests for release CPT storage compatibility.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Releases;

/**
 * Release CPT storage tests.
 *
 * @group releases
 */
class Releases_Test extends TestCase {

	/**
	 * Plugin posts created by a test.
	 *
	 * @var WP_Post[]
	 */
	private $plugins = array();

	/**
	 * Clean up posts created by tests.
	 */
	protected function tearDown(): void {
		foreach ( $this->plugins as $plugin ) {
			$release_posts = get_posts(
				array(
					'post_type'      => Releases::POST_TYPE,
					'post_parent'    => $plugin->ID,
					'post_status'    => 'any',
					'posts_per_page' => -1,
				)
			);

			foreach ( $release_posts as $release_post ) {
				wp_delete_post( $release_post->ID, true );
			}

			wp_delete_post( $plugin->ID, true );
		}

		$this->plugins = array();
		parent::tearDown();
	}

	/**
	 * Create a plugin post for release tests.
	 *
	 * @param string $slug Plugin slug.
	 * @return WP_Post
	 */
	private function create_plugin( $slug = 'release-cpt-test' ) {
		$now     = current_time( 'mysql' );
		$post_id = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_name'         => $slug,
				'post_title'        => 'Release CPT Test',
				'post_status'       => 'publish',
				'post_date'         => $now,
				'post_date_gmt'     => $now,
				'post_modified'     => $now,
				'post_modified_gmt' => $now,
			)
		);

		update_post_meta( $post_id, 'releases', array() );

		$plugin          = get_post( $post_id );
		$this->plugins[] = $plugin;

		return $plugin;
	}

	/**
	 * Get release CPT posts for a plugin.
	 *
	 * @param WP_Post $plugin Plugin post.
	 * @return WP_Post[]
	 */
	private function get_release_posts( $plugin ) {
		return get_posts(
			array(
				'post_type'      => Releases::POST_TYPE,
				'post_parent'    => $plugin->ID,
				'post_status'    => 'any',
				'posts_per_page' => -1,
			)
		);
	}

	/**
	 * The legacy add_release() API writes a release CPT.
	 */
	public function test_add_release_writes_cpt_and_preserves_legacy_shape() {
		$plugin = $this->create_plugin();

		$result = Plugin_Directory::add_release(
			$plugin,
			array(
				'date'                     => 1700000000,
				'tag'                      => '1.0.0',
				'version'                  => '1.0.0',
				'committer'                => array( 'alice' ),
				'revision'                 => array( 123 ),
				'confirmations_required'   => 1,
				'confirmed'                => false,
				'zips_built'               => false,
				'zips_built_from_revision' => 0,
				'release_delay'            => HOUR_IN_SECONDS,
			)
		);

		$this->assertTrue( $result );

		$release_posts = $this->get_release_posts( $plugin );
		$this->assertCount( 1, $release_posts );
		$this->assertSame( 'plugin_release', $release_posts[0]->post_type );
		$this->assertSame( '1.0.0', get_post_meta( $release_posts[0]->ID, 'tag', true ) );

		$release = Plugin_Directory::get_release( $plugin, '1.0.0' );
		$this->assertSame( '1.0.0', $release['tag'] );
		$this->assertSame( '1.0.0', $release['version'] );
		$this->assertSame( array( 'alice' ), $release['committer'] );
		$this->assertSame( array( 123 ), $release['revision'] );
		$this->assertSame( HOUR_IN_SECONDS, $release['release_delay'] );
		$this->assertFalse( $release['confirmed'] );
	}

	/**
	 * Legacy release metadata is not backfilled on read; the migration handles it.
	 */
	public function test_legacy_releases_meta_is_not_backfilled_on_read() {
		$plugin = $this->create_plugin( 'legacy-release-cpt-test' );
		$legacy = array(
			array(
				'date'                   => 1700000000,
				'tag'                    => '1.0.0',
				'version'                => '1.0.0',
				'committer'              => array( 'alice' ),
				'revision'               => array( 100 ),
				'zips_built'             => true,
				'confirmations_required' => 0,
				'release_delay'          => 0,
			),
		);
		update_post_meta( $plugin->ID, 'releases', $legacy );

		// Reads no longer trigger an automatic backfill.
		$this->assertSame( array(), Plugin_Directory::get_releases( $plugin ) );
		$this->assertCount( 0, $this->get_release_posts( $plugin ) );
	}

	/**
	 * Writes backfill legacy release metadata to release CPTs first.
	 */
	public function test_writes_backfill_legacy_releases_meta_first() {
		$plugin = $this->create_plugin( 'write-backfill-release-cpt-test' );
		$legacy = array(
			array(
				'date'                   => 1700000000,
				'tag'                    => '1.0.0',
				'version'                => '1.0.0',
				'committer'              => array( 'alice' ),
				'revision'               => array( 100 ),
				'zips_built'             => true,
				'confirmations_required' => 0,
				'release_delay'          => 0,
			),
			array(
				'date'                   => 1710000000,
				'tag'                    => '1.1.0',
				'version'                => '1.1.0',
				'committer'              => array( 'bob' ),
				'revision'               => array( 200 ),
				'zips_built'             => false,
				'confirmed'              => false,
				'confirmations_required' => 1,
				'release_delay'          => 0,
			),
		);
		update_post_meta( $plugin->ID, 'releases', $legacy );

		// Removing the unconfirmed legacy release backfills all releases to CPTs first.
		$this->assertTrue( Plugin_Directory::remove_release( $plugin, '1.1.0' ) );

		$releases = Plugin_Directory::get_releases( $plugin );
		$this->assertCount( 1, $releases );
		$this->assertSame( '1.0.0', $releases[0]['tag'] );
	}

	/**
	 * The migration backfills legacy release metadata to release CPTs.
	 */
	public function test_migration_backfills_legacy_releases_meta_to_cpts() {
		$plugin = $this->create_plugin( 'migrate-release-cpt-test' );
		$legacy = array(
			array(
				'date'                   => 1700000000,
				'tag'                    => '1.0.0',
				'version'                => '1.0.0',
				'committer'              => array( 'alice' ),
				'revision'               => array( 100 ),
				'zips_built'             => true,
				'confirmations_required' => 0,
				'release_delay'          => 0,
			),
			array(
				'date'                   => 1710000000,
				'tag'                    => '1.1.0',
				'version'                => '1.1.0',
				'committer'              => array( 'bob' ),
				'revision'               => array( 200 ),
				'zips_built'             => false,
				'confirmations_required' => 0,
				'release_delay'          => 2 * HOUR_IN_SECONDS,
			),
		);
		update_post_meta( $plugin->ID, 'releases', $legacy );

		Releases::for_plugin( $plugin )->maybe_backfill();

		$releases = Plugin_Directory::get_releases( $plugin );

		$this->assertCount( 2, $releases );
		$this->assertSame( '1.1.0', $releases[0]['tag'] );
		$this->assertTrue( $releases[0]['zips_built'], 'Legacy no-confirmation releases should still report built ZIPs.' );
		$this->assertSame( 2 * HOUR_IN_SECONDS, $releases[0]['release_delay'] );
		$this->assertCount( 2, $this->get_release_posts( $plugin ) );

		// Re-running the migration is idempotent and does not duplicate CPTs.
		Releases::for_plugin( $plugin )->maybe_backfill();
		$this->assertCount( 2, $this->get_release_posts( $plugin ), 'Backfill should not duplicate release CPTs.' );
	}

	/**
	 * Existing release tags are updated instead of duplicated.
	 */
	public function test_add_release_updates_existing_tag_and_merges_array_fields() {
		$plugin = $this->create_plugin( 'merge-release-cpt-test' );

		Plugin_Directory::add_release(
			$plugin,
			array(
				'tag'       => '1.0.0',
				'version'   => '1.0.0',
				'committer' => array( 'alice' ),
				'revision'  => array( 100 ),
			)
		);

		Plugin_Directory::add_release(
			$plugin,
			array(
				'tag'       => '1.0.0',
				'committer' => array( 'bob' ),
				'revision'  => array( 101 ),
				'confirmed' => true,
			)
		);

		$release = Plugin_Directory::get_release( $plugin, '1.0.0' );
		$this->assertSame( array( 'alice', 'bob' ), $release['committer'] );
		$this->assertSame( array( 100, 101 ), $release['revision'] );
		$this->assertTrue( $release['confirmed'] );
		$this->assertCount( 1, $this->get_release_posts( $plugin ) );
	}

	/**
	 * Only unconfirmed releases can be removed.
	 */
	public function test_remove_release_only_deletes_unconfirmed_releases() {
		$plugin = $this->create_plugin( 'remove-release-cpt-test' );

		Plugin_Directory::add_release(
			$plugin,
			array(
				'tag'                    => '1.0.0',
				'version'                => '1.0.0',
				'confirmed'              => false,
				'confirmations_required' => 1,
			)
		);
		Plugin_Directory::add_release(
			$plugin,
			array(
				'tag'       => '2.0.0',
				'version'   => '2.0.0',
				'confirmed' => true,
			)
		);

		$this->assertTrue( Plugin_Directory::remove_release( $plugin, '1.0.0' ) );
		$this->assertFalse( Plugin_Directory::get_release( $plugin, '1.0.0' ) );

		$this->assertFalse( Plugin_Directory::remove_release( $plugin, '2.0.0' ) );
		$this->assertIsArray( Plugin_Directory::get_release( $plugin, '2.0.0' ) );
	}

	/**
	 * The legacy trunk@version lookup fallback is preserved.
	 */
	public function test_get_release_keeps_trunk_version_fallback() {
		$plugin = $this->create_plugin( 'trunk-release-cpt-test' );

		Plugin_Directory::add_release(
			$plugin,
			array(
				'tag'     => 'trunk@1.2.3',
				'version' => '1.2.3',
			)
		);

		$release = Plugin_Directory::get_release( $plugin, '1.2.3' );

		$this->assertSame( 'trunk@1.2.3', $release['tag'] );
		$this->assertSame( '1.2.3', $release['version'] );
	}
}
