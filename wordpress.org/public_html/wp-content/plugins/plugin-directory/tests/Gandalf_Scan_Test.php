<?php
/**
 * Functional tests for the plugins/v1/plugin/<slug>/gandalf-scan endpoint.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;

/**
 * Functional tests for the plugins/v1/plugin/<slug>/gandalf-scan endpoint.
 *
 * @group rest-api
 */
class Gandalf_Scan_Test extends Plugin_Directory_Endpoint_TestCase {

	/**
	 * ID of the plugin fixture.
	 *
	 * @var int
	 */
	protected static $plugin_id;

	/**
	 * Creates the plugin fixture and the shared secret.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! defined( 'WP_GANDALF_SCAN_SHARED_SECRET' ) ) {
			define( 'WP_GANDALF_SCAN_SHARED_SECRET', 'gandalf-test-secret' );
		}

		self::$plugin_id = self::create_plugin( 'gandalf-test-plugin', 'Gandalf Test Plugin' );
	}

	/**
	 * Deletes the plugin fixture.
	 */
	public static function tearDownAfterClass(): void {
		wp_delete_post( self::$plugin_id, true );

		parent::tearDownAfterClass();
	}

	/**
	 * Scan callbacks require the shared secret.
	 */
	public function test_requires_authentication() {
		$body = array(
			'scan_id'     => 'scan-123',
			'version'     => '1.0',
			'release_ref' => 'tags/1.0',
			'status'      => 'completed',
		);

		$request = $this->callback_request( $body, false );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'not_authorized', $response->get_data()['code'] );
	}

	/**
	 * Malformed callbacks reach the handler and are recorded on the plugin
	 * instead of being rejected at the REST layer.
	 */
	public function test_records_malformed_callbacks() {
		$request = $this->callback_request( array( 'scan_id' => 'scan-123' ) );

		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'unknown_gandalf_scan', $response->get_data()['code'] );
		$this->assertNotEmpty( get_post_meta( self::$plugin_id, Plugin_Scan_Gandalf::LAST_ERROR_META_KEY, true ) );
	}

	/**
	 * Callbacks padding inapplicable fields with nulls pass the schema.
	 */
	public function test_accepts_null_padded_callbacks() {
		$request = $this->callback_request( array(
			'scan_id'         => 'scan-that-was-never-dispatched',
			'version'         => '1.0',
			'release_ref'     => 'tags/1.0',
			'status'          => 'completed',
			'findings_count'  => null,
			'severity_counts' => null,
			'verdict_hash'    => null,
			'report_url'      => null,
			'error'           => null,
		) );

		$response = self::server()->dispatch( $request );

		// Rejected by the handler as an unknown scan, not by the schema.
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'unknown_gandalf_scan', $response->get_data()['code'] );
	}

	/**
	 * Callbacks for unknown scans are rejected and recorded on the plugin.
	 */
	public function test_rejects_unknown_scan() {
		$request = $this->callback_request(
			array(
				'scan_id'     => 'scan-that-was-never-dispatched',
				'version'     => '1.0',
				'release_ref' => 'tags/1.0',
				'status'      => 'completed',
			)
		);

		$response = self::server()->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'unknown_gandalf_scan', $response->get_data()['code'] );
		$this->assertNotEmpty( get_post_meta( self::$plugin_id, Plugin_Scan_Gandalf::LAST_ERROR_META_KEY, true ) );
	}

	/**
	 * Builds a scan callback request with a JSON body.
	 *
	 * @param array $body       The callback body.
	 * @param bool  $authorized Optional. Whether to include the shared secret. Default true.
	 * @return WP_REST_Request The request.
	 */
	protected function callback_request( $body, $authorized = true ) {
		$request = new WP_REST_Request( 'POST', '/plugins/v1/plugin/gandalf-test-plugin/gandalf-scan' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );

		if ( $authorized ) {
			$request->set_header( 'Authorization', 'Bearer ' . WP_GANDALF_SCAN_SHARED_SECRET );
		}

		return $request;
	}
}
