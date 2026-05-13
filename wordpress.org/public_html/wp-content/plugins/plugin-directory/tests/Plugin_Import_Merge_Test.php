<?php
/**
 * Tests for Plugin_Import::merge_plugin_data() and queue_run_time() helpers.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Import;

/**
 * @group jobs
 */
class Plugin_Import_Merge_Test extends TestCase {

	/**
	 * Invoke the protected Plugin_Import::merge_plugin_data() helper.
	 */
	private function merge( array $existing, array $incoming ): array {
		$method = new ReflectionMethod( Plugin_Import::class, 'merge_plugin_data' );
		$method->setAccessible( true );

		return $method->invoke( null, $existing, $incoming );
	}

	/**
	 * Invoke the protected Plugin_Import::is_trunk_only_update_on_tagged_plugin() helper.
	 */
	private function is_trunk_only_on_tagged_plugin( string $plugin_slug, array $args ): bool {
		$method = new ReflectionMethod( Plugin_Import::class, 'is_trunk_only_update_on_tagged_plugin' );
		$method->setAccessible( true );

		return $method->invoke( null, $plugin_slug, $args );
	}

	public function test_merges_revisions_and_tags_without_duplicates() {
		$existing = [
			'plugin'         => 'hello',
			'tags_touched'   => [ 'trunk', '1.0' ],
			'tags_deleted'   => [],
			'revisions'      => [ 100, 101 ],
			'readme_touched' => true,
			'code_touched'   => false,
			'assets_touched' => false,
		];
		$new = [
			'plugin'         => 'hello',
			'tags_touched'   => [ 'trunk', '2.0' ],
			'tags_deleted'   => [ '0.9' ],
			'revisions'      => [ 101, 200 ],
			'readme_touched' => false,
			'code_touched'   => true,
			'assets_touched' => false,
		];

		$merged = $this->merge( $existing, $new );

		$this->assertEqualsCanonicalizing( [ 'trunk', '1.0', '2.0' ], $merged['tags_touched'] );
		$this->assertEqualsCanonicalizing( [ '0.9' ], $merged['tags_deleted'] );
		$this->assertEqualsCanonicalizing( [ 100, 101, 200 ], $merged['revisions'] );
	}

	public function test_boolean_flags_are_ored() {
		$existing = [
			'readme_touched' => true,
			'code_touched'   => false,
			'assets_touched' => false,
		];
		$new = [
			'readme_touched' => false,
			'code_touched'   => true,
			'assets_touched' => false,
		];

		$merged = $this->merge( $existing, $new );

		$this->assertTrue( $merged['readme_touched'] );
		$this->assertTrue( $merged['code_touched'] );
		$this->assertFalse( $merged['assets_touched'] );
	}

	public function test_missing_keys_in_existing_are_safe() {
		$merged = $this->merge(
			[ 'plugin' => 'hello' ],
			[
				'plugin'         => 'hello',
				'tags_touched'   => [ 'trunk' ],
				'revisions'      => [ 42 ],
				'readme_touched' => true,
			]
		);

		$this->assertSame( [ 'trunk' ], $merged['tags_touched'] );
		$this->assertSame( [ 42 ], $merged['revisions'] );
		$this->assertSame( [], $merged['tags_deleted'] );
		$this->assertTrue( $merged['readme_touched'] );
	}

	/**
	 * If a tag is touched the change should never be classified as trunk-only,
	 * even if /trunk is also in the same commit.
	 */
	public function test_change_touching_a_tag_is_not_trunk_only() {
		$this->assertFalse( $this->is_trunk_only_on_tagged_plugin( 'no-such-plugin', [
			'tags_touched' => [ 'trunk', '1.2.3' ],
			'tags_deleted' => [],
		] ) );

		$this->assertFalse( $this->is_trunk_only_on_tagged_plugin( 'no-such-plugin', [
			'tags_touched' => [ '1.2.3' ],
			'tags_deleted' => [],
		] ) );
	}

	/**
	 * A change that deletes a tag isn't a candidate for the 5-minute delay either:
	 * the deletion needs to propagate to the directory immediately.
	 */
	public function test_change_deleting_a_tag_is_not_trunk_only() {
		$this->assertFalse( $this->is_trunk_only_on_tagged_plugin( 'no-such-plugin', [
			'tags_touched' => [ 'trunk' ],
			'tags_deleted' => [ '1.1.0' ],
		] ) );
	}

	/**
	 * Without a backing plugin post the helper falls back to "run immediately",
	 * so the import isn't held up just because the directory hasn't seen the
	 * plugin yet.
	 */
	public function test_unknown_plugin_does_not_delay() {
		$this->assertFalse( $this->is_trunk_only_on_tagged_plugin( 'no-such-plugin', [
			'tags_touched' => [ 'trunk' ],
			'tags_deleted' => [],
		] ) );
	}
}
