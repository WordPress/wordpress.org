<?php
/**
 * Shared fixture for tests about what a Gandalf scan verdict does to a release.
 *
 * Adds an in-flight scan of NEW_VERSION to the release fixture, a builder for the callback
 * payload Gandalf sends.
 *
 * Contains no tests of its own, so it's excluded from the suite in phpunit.xml and loaded on
 * demand by the test bootstrap's autoloader when a subclass extends it.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;

/**
 * @group jobs
 */
abstract class Gandalf_Scan_Test_Case extends Release_Block_Test_Case {

	/**
	 * The scan the callbacks under test report on.
	 */
	const SCAN_ID = '9a7b6c5d-0000-4000-8000-0123456789ab';

	/**
	 * The release fixture, plus a scan of NEW_VERSION awaiting its verdict.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->set_pending_scan( static::NEW_VERSION );
	}

	/**
	 * Record an in-flight scan so handle_callback() recognizes the callback.
	 *
	 * @param string $version The version being scanned.
	 */
	protected function set_pending_scan( $version ) {
		update_post_meta(
			$this->plugin->ID,
			Plugin_Scan_Gandalf::PENDING_META_KEY,
			array(
				static::SCAN_ID => array(
					'version'      => $version,
					'release_ref'  => $version,
					'requested_at' => time(),
				),
			)
		);
	}

	/**
	 * The pending scans still awaiting a callback.
	 *
	 * @return array
	 */
	protected function get_pending_scans() {
		$pending = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true );

		return $pending ? $pending : array();
	}

	/**
	 * A well-formed completed-scan payload, as Gandalf would send it. The scanned version has
	 * no findings; a verdict field like risk_score is added per-test via $overrides.
	 *
	 * @param array $overrides Values to override on the default payload.
	 * @return array
	 */
	protected function callback_data( $overrides = array() ) {
		return array_merge(
			array(
				'scan_id'         => static::SCAN_ID,
				'status'          => 'completed',
				'version'         => static::NEW_VERSION,
				'release_ref'     => static::NEW_VERSION,
				'findings_count'  => 0,
				'severity_counts' => array(),
				'verdict_hash'    => 'abc123',
				'report_url'      => 'https://gandalf.wordpress.org/report/abc123',
			),
			$overrides
		);
	}
}
