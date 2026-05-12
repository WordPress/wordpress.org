<?php
/**
 * Tests for Plugin_Import::merge_plugin_data().
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
	private function merge( array $existing, array $new ): array {
		$method = new ReflectionMethod( Plugin_Import::class, 'merge_plugin_data' );
		$method->setAccessible( true );

		return $method->invoke( null, $existing, $new );
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
}
