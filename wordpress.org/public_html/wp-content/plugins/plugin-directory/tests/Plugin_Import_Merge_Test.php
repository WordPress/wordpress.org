<?php
/**
 * Tests for Plugin_Import::merge_plugin_data() and is_trunk_only_update() helpers.
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
	 * Invoke the protected Plugin_Import::is_trunk_only_update() helper.
	 */
	private function is_trunk_only( array $args ): bool {
		$method = new ReflectionMethod( Plugin_Import::class, 'is_trunk_only_update' );
		$method->setAccessible( true );

		return $method->invoke( null, $args );
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
	 * A commit that touches only /trunk is the candidate for the grace window.
	 */
	public function test_trunk_only_commit_is_classified_as_trunk_only() {
		$this->assertTrue( $this->is_trunk_only( [
			'tags_touched' => [ 'trunk' ],
			'tags_deleted' => [],
		] ) );
	}

	/**
	 * If a tag is touched the change should never be classified as trunk-only,
	 * even if /trunk is also in the same commit.
	 */
	public function test_change_touching_a_tag_is_not_trunk_only() {
		$this->assertFalse( $this->is_trunk_only( [
			'tags_touched' => [ 'trunk', '1.2.3' ],
			'tags_deleted' => [],
		] ) );

		$this->assertFalse( $this->is_trunk_only( [
			'tags_touched' => [ '1.2.3' ],
			'tags_deleted' => [],
		] ) );
	}

	/**
	 * A change that deletes a tag isn't a candidate for the grace window
	 * either: the deletion needs to propagate to the directory immediately.
	 */
	public function test_change_deleting_a_tag_is_not_trunk_only() {
		$this->assertFalse( $this->is_trunk_only( [
			'tags_touched' => [ 'trunk' ],
			'tags_deleted' => [ '1.1.0' ],
		] ) );
	}

	/**
	 * An empty set of touched tags (a defensive default) shouldn't be
	 * misclassified as a delayable trunk-only update.
	 */
	public function test_empty_tags_touched_is_not_trunk_only() {
		$this->assertFalse( $this->is_trunk_only( [
			'tags_touched' => [],
			'tags_deleted' => [],
		] ) );
	}
}
