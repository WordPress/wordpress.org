<?php
/**
 * Tests for Jobs\Plugin_Scan_Gandalf.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;
use PHPUnit\Framework\TestCase;

/**
 * Tests Gandalf dispatch eligibility from importer context.
 *
 * @group gandalf
 */
class Plugin_Scan_Gandalf_Test extends TestCase {

	/**
	 * Captured Gandalf dispatch requests.
	 *
	 * @var array
	 */
	private $requests = array();

	/**
	 * Plugin posts created during a test.
	 *
	 * @var array
	 */
	private $plugin_ids = array();

	/**
	 * Define the Gandalf shared secret used by the integration guard.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! defined( 'WP_GANDALF_SCAN_SHARED_SECRET' ) ) {
			define( 'WP_GANDALF_SCAN_SHARED_SECRET', 'test-secret' );
		}
	}

	/**
	 * Capture Gandalf HTTP dispatches for each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->requests   = array();
		$this->plugin_ids = array();

		add_filter( 'pre_http_request', array( $this, 'capture_gandalf_request' ), 10, 3 );
	}

	/**
	 * Remove the HTTP capture filter.
	 */
	protected function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'capture_gandalf_request' ), 10 );

		foreach ( $this->plugin_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		parent::tearDown();
	}

	/**
	 * Capture the Gandalf HTTP request and return a matching accepted response.
	 *
	 * @param false|array|WP_Error $preempt     Whether to preempt the request.
	 * @param array                $parsed_args Request arguments.
	 * @param string               $url         Request URL.
	 * @return array
	 */
	public function capture_gandalf_request( $preempt, $parsed_args, $url ) {
		if ( Plugin_Scan_Gandalf::ENDPOINT !== $url ) {
			return $preempt;
		}

		$body             = json_decode( $parsed_args['body'], true );
		$this->requests[] = array(
			'url'  => $url,
			'args' => $parsed_args,
			'body' => $body,
		);

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'scan_id' => $body['scan_id'],
				)
			),
			'response' => array(
				'code'    => 202,
				'message' => 'Accepted',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * A missing-tag trunk fallback should not dispatch to Gandalf.
	 */
	public function test_missing_tag_trunk_fallback_does_not_dispatch() {
		$plugin = $this->create_plugin(
			'gandalf-missing-tag-fallback',
			array(
				'version' => '1.0.1',
			)
		);

		$result = Plugin_Scan_Gandalf::dispatch_from_import_context(
			$plugin,
			array(
				'stable_tag'       => 'trunk',
				'old_stable_tag'   => '1.0.0',
				'changed_svn_tags' => array( 'trunk' ),
				'warnings'         => array(
					'stable_tag_invalid_trunk_fallback' => '1.0.1',
				),
			)
		);

		$this->assertFalse( $result );
		$this->assertSame( array(), $this->requests );
		$pending = get_post_meta( $plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true );
		$this->assertSame( array(), $pending ? $pending : array() );
	}

	/**
	 * An intentional trunk release should still dispatch.
	 */
	public function test_intentional_trunk_release_dispatches() {
		$plugin = $this->create_plugin(
			'gandalf-intentional-trunk',
			array(
				'version' => '1.0.1',
			)
		);

		$result = Plugin_Scan_Gandalf::dispatch_from_import_context(
			$plugin,
			array(
				'stable_tag'       => 'trunk',
				'old_stable_tag'   => 'trunk',
				'changed_svn_tags' => array( 'trunk' ),
				'warnings'         => array(),
			)
		);

		$this->assertTrue( $result );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'trunk', $this->requests[0]['body']['release_ref'] );
		$this->assertSame( 'https://downloads.wordpress.org/plugin/gandalf-intentional-trunk.zip', $this->requests[0]['body']['current_zip_url'] );
	}

	/**
	 * A normal tagged release should dispatch and include previous ZIP context.
	 */
	public function test_tagged_release_dispatches_with_empty_warnings() {
		$plugin = $this->create_plugin(
			'gandalf-tagged-release',
			array(
				'version'         => '1.0.1',
				'last_version'    => '1.0.0',
				'last_stable_tag' => '1.0.0',
			)
		);

		$result = Plugin_Scan_Gandalf::dispatch_from_import_context(
			$plugin,
			array(
				'stable_tag'       => '1.0.1',
				'old_stable_tag'   => '1.0.0',
				'changed_svn_tags' => array( '1.0.1' ),
				'warnings'         => array(),
			)
		);

		$this->assertTrue( $result );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( '1.0.1', $this->requests[0]['body']['release_ref'] );
		$this->assertSame( 'https://downloads.wordpress.org/plugin/gandalf-tagged-release.1.0.1.zip', $this->requests[0]['body']['current_zip_url'] );
		$this->assertSame( '1.0.0', $this->requests[0]['body']['previous_release_ref'] );
		$this->assertSame( 'https://downloads.wordpress.org/plugin/gandalf-tagged-release.1.0.0.zip', $this->requests[0]['body']['previous_zip_url'] );
	}

	/**
	 * The fallback warning alone should not skip a non-trunk release ref.
	 */
	public function test_fallback_warning_does_not_skip_non_trunk_release_ref() {
		$plugin = $this->create_plugin(
			'gandalf-tagged-warning',
			array(
				'version' => '1.0.1',
			)
		);

		$result = Plugin_Scan_Gandalf::dispatch_from_import_context(
			$plugin,
			array(
				'stable_tag'       => '1.0.1',
				'old_stable_tag'   => '1.0.0',
				'changed_svn_tags' => array( '1.0.1' ),
				'warnings'         => array(
					'stable_tag_invalid_trunk_fallback' => '1.0.1',
				),
			)
		);

		$this->assertTrue( $result );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( '1.0.1', $this->requests[0]['body']['release_ref'] );
	}

	/**
	 * Development-only trunk changes should not rescan an unchanged tagged stable release.
	 */
	public function test_trunk_only_change_with_existing_stable_tag_does_not_dispatch() {
		$plugin = $this->create_plugin(
			'gandalf-dev-trunk',
			array(
				'version' => '1.0.1',
			)
		);

		$result = Plugin_Scan_Gandalf::dispatch_from_import_context(
			$plugin,
			array(
				'stable_tag'       => '1.0.1',
				'old_stable_tag'   => '1.0.1',
				'changed_svn_tags' => array( 'trunk' ),
				'warnings'         => array(),
			)
		);

		$this->assertFalse( $result );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Direct calls with incomplete importer context should not dispatch.
	 */
	public function test_malformed_import_context_does_not_dispatch() {
		$plugin = $this->create_plugin(
			'gandalf-malformed-context',
			array(
				'version' => '1.0.1',
			)
		);

		$result = Plugin_Scan_Gandalf::dispatch_from_import_context(
			$plugin,
			array(
				'stable_tag'       => 'trunk',
				'old_stable_tag'   => 'trunk',
				'changed_svn_tags' => array( 'trunk' ),
			)
		);

		$this->assertFalse( $result );
		$this->assertSame( array(), $this->requests );
	}

	/**
	 * Create a minimal plugin post with the post meta Gandalf reads.
	 *
	 * @param string $slug Plugin slug.
	 * @param array  $meta Plugin post meta.
	 * @return WP_Post
	 */
	private function create_plugin( $slug, $meta = array() ) {
		$post_id = wp_insert_post(
			array(
				'post_name'   => $slug,
				'post_title'  => $slug,
				'post_type'   => 'plugin',
				'post_status' => 'publish',
			)
		);

		$this->plugin_ids[] = $post_id;

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return get_post( $post_id );
	}
}
