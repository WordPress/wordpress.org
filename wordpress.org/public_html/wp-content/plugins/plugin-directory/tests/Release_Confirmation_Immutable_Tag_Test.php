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
	 * A tag last changed at (or before) the release's recorded source revision is unmodified —
	 * re-triggered imports of the same commit must stay a no-op.
	 */
	public function test_tag_at_source_revision_is_not_modified(): void {
		$release = array( 'source_revision' => 100 );

		$this->assertFalse( Import::tag_modified_after_release( $release, 100 ) );
		$this->assertFalse( Import::tag_modified_after_release( $release, 90 ) );
	}

	/**
	 * A tag last changed after the recorded source revision was modified behind the release's
	 * back and must trigger a reset — the check ignores confirmed/zips-built state, so the
	 * confirm-to-build window and partially-confirmed releases are covered too.
	 */
	public function test_tag_changed_after_source_revision_is_modified(): void {
		$release = array( 'source_revision' => 100 );

		$this->assertTrue( Import::tag_modified_after_release( $release, 101 ) );
	}

	/**
	 * A discarded release is not exempt: its code can still be re-committed, so a change past
	 * the source revision is detected. The reset (and discard itself) clears its confirmations.
	 */
	public function test_discarded_release_is_not_exempt(): void {
		$release = array(
			'source_revision' => 100,
			'discarded'       => array( 'user' => 'someone' ),
		);

		$this->assertTrue( Import::tag_modified_after_release( $release, 200 ) );
	}

	/**
	 * A legacy record predating source_revision falls back to the served ZIP's export revision: a
	 * tag change after it is unshipped and re-opens the release, while a change already in the built
	 * code does not.
	 */
	public function test_legacy_release_falls_back_to_zip_build_revision(): void {
		$release = array( 'zips_built_from_revision' => 150 );

		$this->assertFalse( Import::tag_modified_after_release( $release, 150 ) );
		$this->assertTrue( Import::tag_modified_after_release( $release, 151 ) );
	}

	/**
	 * A legacy record with no revision information at all (e.g. a prefilled grandfathered tag) fails
	 * safe: any real revision counts as modified rather than trusting the grandfathered approval.
	 */
	public function test_legacy_release_without_any_revision_fails_safe(): void {
		$this->assertTrue( Import::tag_modified_after_release( array( 'tag' => '1.0' ), 100 ) );
		$this->assertFalse( Import::tag_modified_after_release( array( 'tag' => '1.0' ), 0 ) );
	}

	/**
	 * A missing release carries no approval to protect and never counts as modified.
	 */
	public function test_missing_release_is_not_modified(): void {
		$this->assertFalse( Import::tag_modified_after_release( false, 100 ) );
	}

	/**
	 * Recording a discard clears the release's confirmations outright, so undo_discard_release()
	 * can't restore approvals collected against code that has since changed.
	 */
	public function test_discard_clears_confirmations(): void {
		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'                    => '1.4.0',
				'version'                => '1.4.0',
				'confirmations_required' => 2,
				'confirmations'          => array(
					'alice' => 111,
					'bob'   => 222,
				),
			)
		);

		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'       => '1.4.0',
				'confirmed' => false,
				'discarded' => array(
					'user' => 'reviewer',
					'time' => 333,
				),
			)
		);

		$this->assertSame( array(), $this->release( '1.4.0' )['confirmations'] );
	}

	/**
	 * Re-opening a release re-arms the current cooldown (dropping a force-release bypass) and
	 * refreshes its date so it resurfaces in the confirmation queue instead of staying buried.
	 */
	public function test_reset_rearms_cooldown_and_refreshes_date(): void {
		$before = time();

		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'           => '1.4.0',
				'version'       => '1.4.0',
				'confirmed'     => true,
				'date'          => $before - WEEK_IN_SECONDS,
				'release_delay' => 0,
			)
		);

		Plugin_Directory::add_release(
			$this->plugin,
			array(
				'tag'                => '1.4.0',
				'reset_confirmation' => true,
			)
		);

		$release = $this->release( '1.4.0' );

		$this->assertSame(
			\WordPressdotorg\Plugin_Directory\get_release_cooldown_delay( $this->plugin->post_name ),
			$release['release_delay']
		);
		$this->assertGreaterThanOrEqual( $before, $release['date'] );
	}
}
