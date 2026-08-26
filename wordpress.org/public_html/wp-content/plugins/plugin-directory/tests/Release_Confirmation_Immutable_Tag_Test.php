<?php
/**
 * Tests that a released tag cannot silently inherit its prior confirmation when
 * its code is modified after Release Confirmation is enabled.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Import;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * When Release Confirmation is enabled after a plugin already has tagged releases,
 * those tags are grandfathered as `confirmed = true`, `confirmations_required = 0`,
 * `zips_built = true`. If a committer then rewrites the code inside such a tag, the
 * altered revision must be re-approved from scratch rather than riding the old
 * zero-confirmation state.
 *
 * These tests cover the `reset_confirmation` seam Import::import_from_svn() uses to
 * re-open a modified released tag, the Import::tag_modified_after_release() check that
 * decides when to use it, and confirm a plain merge never resets on its own.
 *
 * Extends the plain PHPUnit TestCase for the same reasons as
 * Current_Release_Resolution_Test: WP_UnitTestCase is incompatible with the
 * PHPUnit 11 runner, and per-test isolation comes from a unique plugin post.
 *
 * @group jobs
 */
#[Group( 'jobs' )]
class Release_Confirmation_Immutable_Tag_Test extends TestCase {

	/**
	 * Counter to give every test plugin a unique slug.
	 *
	 * @var int
	 */
	private static int $plugin_count = 0;

	/**
	 * The plugin post under test.
	 *
	 * @var \WP_Post
	 */
	private \WP_Post $plugin;

	/**
	 * Create a published plugin with two-person Release Confirmation enabled.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'immutable-tag-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Immutable Tag Test Plugin',
				'post_status' => 'publish',
			)
		);

		$this->assertInstanceOf( \WP_Post::class, $plugin );
		$this->plugin = $plugin;

		update_post_meta( $this->plugin->ID, 'release_confirmation', 2 );
	}

	/**
	 * Store a grandfathered release: served, confirmed, zero confirmations required.
	 *
	 * Mirrors the state Plugin_Directory::prefill_releases_meta() records for a tag
	 * that existed before Release Confirmation was enabled.
	 *
	 * @param string $tag     The release tag.
	 * @param string $version The release version.
	 */
	private function add_grandfathered_release( string $tag, string $version ): void {
		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'                      => $tag,
				'version'                  => $version,
				'zips_built'               => true,
				'zips_built_from_revision' => 0,
				'confirmed'                => true,
				'confirmations_required'   => 0,
			)
		);
	}

	/**
	 * The current stored release for a tag.
	 *
	 * @param string $tag The release tag.
	 * @return array The release record.
	 */
	private function release( string $tag ): array {
		return Plugin_Directory::get_release( get_post( $this->plugin->ID ), $tag );
	}

	/**
	 * Re-opening a grandfathered release wipes its zero-confirmation approval and
	 * restores the plugin's current required confirmation count.
	 */
	public function test_reset_confirmation_clears_grandfathered_approval(): void {
		$this->add_grandfathered_release( '1.4.0', '1.4.0' );

		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'                => '1.4.0',
				'version'            => '99.0.0',
				'committer'          => array( 'attacker' ),
				'revision'           => array( 5000 ),
				'reset_confirmation' => true,
			)
		);

		$release = $this->release( '1.4.0' );

		$this->assertFalse( $release['confirmed'] );
		$this->assertSame( array(), $release['confirmations'] );
		$this->assertSame( 2, $release['confirmations_required'] );
		$this->assertFalse( $release['zips_built'] );
		$this->assertSame( 0, $release['zips_built_from_revision'] );
		$this->assertSame( '99.0.0', $release['version'] );
	}

	/**
	 * A reset drops any confirmations already recorded against the old revision, so
	 * a stale approval can't pre-satisfy the count for the modified code.
	 */
	public function test_reset_confirmation_discards_prior_confirmations(): void {
		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'                      => '1.4.0',
				'version'                  => '1.4.0',
				'zips_built'               => true,
				'zips_built_from_revision' => 4000,
				'confirmed'                => true,
				'confirmations_required'   => 2,
				'confirmations'            => array(
					'alice' => 111,
					'bob'   => 222,
				),
			)
		);

		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'                => '1.4.0',
				'version'            => '99.0.0',
				'reset_confirmation' => true,
			)
		);

		$release = $this->release( '1.4.0' );

		$this->assertFalse( $release['confirmed'] );
		$this->assertSame( array(), $release['confirmations'] );
	}

	/**
	 * Control: a normal merge (no reset flag) never disturbs the confirmation state.
	 * This is exactly why a modified grandfathered tag needs the explicit reset — the
	 * importer's ordinary version/revision update would otherwise keep it approved.
	 */
	public function test_plain_merge_preserves_confirmation(): void {
		$this->add_grandfathered_release( '1.4.0', '1.4.0' );

		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'       => '1.4.0',
				'version'   => '99.0.0',
				'committer' => array( 'attacker' ),
				'revision'  => array( 5000 ),
			)
		);

		$release = $this->release( '1.4.0' );

		$this->assertTrue( $release['confirmed'] );
		$this->assertSame( 0, $release['confirmations_required'] );
	}

	/**
	 * A tag last changed at (or before) a revision the release has already recorded is
	 * unmodified — re-triggered imports of the same commit must stay a no-op.
	 */
	public function test_tag_at_a_recorded_revision_is_not_modified(): void {
		$release = array(
			'revision'                 => array( 100 ),
			'zips_built_from_revision' => 0,
		);

		$this->assertFalse( Import::tag_modified_after_release( $release, 100 ) );
		$this->assertFalse( Import::tag_modified_after_release( $release, 90 ) );
	}

	/**
	 * A tag last changed after every revision the release has recorded was modified
	 * behind the release's back and must trigger a reset — regardless of whether the
	 * release is confirmed or its zips are built, so the confirm-to-build window and
	 * partially-confirmed releases are covered too.
	 */
	public function test_tag_changed_after_all_recorded_revisions_is_modified(): void {
		$release = array(
			'revision'                 => array( 100 ),
			'zips_built_from_revision' => 0,
		);

		$this->assertTrue( Import::tag_modified_after_release( $release, 101 ) );
	}

	/**
	 * The zip build's export revision extends the anchor: the build exported the repo at
	 * that revision, so any tag change at or before it was part of the built code.
	 */
	public function test_zip_build_revision_extends_the_anchor(): void {
		$release = array(
			'revision'                 => array( 100 ),
			'zips_built_from_revision' => 150,
		);

		$this->assertFalse( Import::tag_modified_after_release( $release, 150 ) );
		$this->assertTrue( Import::tag_modified_after_release( $release, 151 ) );
	}

	/**
	 * Legacy release records that predate the revision fields must not raise undefined
	 * array key warnings, and anchor at zero so any change re-opens them.
	 */
	public function test_legacy_release_without_revision_fields(): void {
		$this->assertTrue( Import::tag_modified_after_release( array( 'tag' => '1.0' ), 100 ) );
		$this->assertFalse( Import::tag_modified_after_release( array( 'tag' => '1.0' ), 0 ) );
	}

	/**
	 * Missing and discarded releases carry no approval to protect, so they never count
	 * as modified.
	 */
	public function test_missing_and_discarded_releases_are_ignored(): void {
		$this->assertFalse( Import::tag_modified_after_release( false, 100 ) );

		$discarded = array(
			'revision'                 => array( 100 ),
			'zips_built_from_revision' => 0,
			'discarded'                => true,
		);

		$this->assertFalse( Import::tag_modified_after_release( $discarded, 200 ) );
	}
}
