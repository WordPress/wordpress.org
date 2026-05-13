<?php
/**
 * Tests for Jobs\Plugin_Scan_Gandalf.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;

/**
 * Tests Gandalf scan eligibility from importer context.
 *
 * @group gandalf
 */
class Plugin_Scan_Gandalf_Test extends TestCase {

	/**
	 * Invoke Plugin_Scan_Gandalf::should_scan_import_context().
	 *
	 * @param string $stable_tag       The new stable tag.
	 * @param string $old_stable_tag   The previous stable tag.
	 * @param array  $changed_svn_tags The SVN tags that changed.
	 * @param array  $warnings         The import warnings.
	 * @return bool
	 */
	private function should_scan_import_context( $stable_tag, $old_stable_tag, $changed_svn_tags, $warnings ) {
		$reflection = new ReflectionClass( Plugin_Scan_Gandalf::class );
		$method     = $reflection->getMethod( 'should_scan_import_context' );
		$method->setAccessible( true );

		return $method->invoke( null, $stable_tag, $old_stable_tag, $changed_svn_tags, $warnings );
	}

	/**
	 * A missing-tag trunk fallback should not dispatch to Gandalf.
	 */
	public function test_missing_tag_trunk_fallback_does_not_dispatch() {
		$this->assertFalse(
			$this->should_scan_import_context(
				'trunk',
				'1.0.0',
				array( 'trunk' ),
				array(
					'stable_tag_invalid_trunk_fallback' => '1.0.1',
				)
			)
		);
	}

	/**
	 * An intentional trunk release should still dispatch.
	 */
	public function test_intentional_trunk_release_dispatches() {
		$this->assertTrue(
			$this->should_scan_import_context(
				'trunk',
				'trunk',
				array( 'trunk' ),
				array()
			)
		);
	}

	/**
	 * A normal tagged release should still dispatch.
	 */
	public function test_tagged_release_dispatches_with_empty_warnings() {
		$this->assertTrue(
			$this->should_scan_import_context(
				'1.0.1',
				'1.0.0',
				array( '1.0.1' ),
				array()
			)
		);
	}

	/**
	 * The fallback warning alone should not skip a non-trunk release ref.
	 */
	public function test_fallback_warning_does_not_skip_non_trunk_release_ref() {
		$this->assertTrue(
			$this->should_scan_import_context(
				'1.0.1',
				'1.0.0',
				array( '1.0.1' ),
				array(
					'stable_tag_invalid_trunk_fallback' => '1.0.1',
				)
			)
		);
	}

	/**
	 * Development-only trunk changes should not rescan an unchanged tagged stable release.
	 */
	public function test_trunk_only_change_with_existing_stable_tag_does_not_dispatch() {
		$this->assertFalse(
			$this->should_scan_import_context(
				'1.0.1',
				'1.0.1',
				array( 'trunk' ),
				array()
			)
		);
	}
}
