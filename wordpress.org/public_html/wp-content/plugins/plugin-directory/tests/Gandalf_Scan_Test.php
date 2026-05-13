<?php
/**
 * Tests for the Gandalf scan integration.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;

/**
 * @group gandalf
 */
class Gandalf_Scan_Test extends TestCase {

	/**
	 * Captured Gandalf HTTP requests.
	 *
	 * @var array
	 */
	private $http_requests = [];

	/**
	 * Plugin post IDs used by the test.
	 *
	 * @var int[]
	 */
	private $plugin_ids = [];

	/**
	 * Original home URL.
	 *
	 * @var string
	 */
	private $home_url;

	/**
	 * Original site URL.
	 *
	 * @var string
	 */
	private $site_url;

	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'WP_GANDALF_SCAN_SHARED_SECRET' ) ) {
			define( 'WP_GANDALF_SCAN_SHARED_SECRET', 'test-secret' );
		}

		$this->home_url = get_option( 'home' );
		$this->site_url = get_option( 'siteurl' );
		update_option( 'home', 'https://wordpress.org/plugins' );
		update_option( 'siteurl', 'https://wordpress.org/plugins' );

		add_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 10, 3 );
		add_filter( 'rest_url', [ $this, 'filter_rest_url' ], 10, 4 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', [ $this, 'pre_http_request' ], 10 );
		remove_filter( 'rest_url', [ $this, 'filter_rest_url' ], 10 );

		foreach ( $this->plugin_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		update_option( 'home', $this->home_url );
		update_option( 'siteurl', $this->site_url );

		parent::tearDown();
	}

	public function test_dispatches_tag_release_payload() {
		$plugin = $this->make_plugin(
			'gandalf-tag-test',
			[
				'version'         => '1.2.3',
				'last_version'    => '1.2.2',
				'last_stable_tag' => '1.2.2',
			]
		);

		$result = Plugin_Scan_Gandalf::dispatch_from_import_context(
			$plugin,
			[
				'stable_tag'       => '1.2.3',
				'old_stable_tag'   => '1.2.2',
				'changed_svn_tags' => [ '1.2.3' ],
				'svn_revision'     => 12345,
				'warnings'         => [],
			]
		);

		$this->assertTrue( $result );

		$payload = $this->last_gandalf_payload();
		$this->assertTrue( wp_is_uuid( $payload['scan_id'], 4 ) );
		$this->assertSame( 'plugin', $payload['subject_type'] );
		$this->assertSame( $plugin->post_name, $payload['slug'] );
		$this->assertSame( '1.2.3', $payload['version'] );
		$this->assertSame( '1.2.3', $payload['release_ref'] );
		$this->assertSame( 'https://downloads.wordpress.org/plugin/gandalf-tag-test.1.2.3.zip', $payload['current_zip_url'] );
		$this->assertSame( '1.2.2', $payload['previous_version'] );
		$this->assertSame( '1.2.2', $payload['previous_release_ref'] );
		$this->assertSame( 'https://downloads.wordpress.org/plugin/gandalf-tag-test.1.2.2.zip', $payload['previous_zip_url'] );
		$this->assertSame( 'https://wordpress.org/plugins/wp-json/plugins/v1/plugin/gandalf-tag-test/gandalf-scan', $payload['callback_url'] );
	}

	public function test_dispatches_trunk_release_payload() {
		$plugin = $this->make_plugin(
			'gandalf-trunk-test',
			[
				'version'      => '1.2.3',
				'last_version' => '1.2.2',
			]
		);

		$result = Plugin_Scan_Gandalf::dispatch_from_import_context(
			$plugin,
			[
				'stable_tag'       => 'trunk',
				'old_stable_tag'   => 'trunk',
				'changed_svn_tags' => [ 'trunk' ],
				'svn_revision'     => 12345,
				'warnings'         => [],
			]
		);

		$this->assertTrue( $result );

		$payload = $this->last_gandalf_payload();
		$this->assertSame( 'trunk', $payload['release_ref'] );
		$this->assertSame( 'https://downloads.wordpress.org/plugin/gandalf-trunk-test.zip', $payload['current_zip_url'] );
		$this->assertNull( $payload['previous_version'] );
		$this->assertNull( $payload['previous_release_ref'] );
		$this->assertNull( $payload['previous_zip_url'] );
	}

	public function test_completed_callback_clears_pending_scan_and_records_notification_hash() {
		$plugin  = $this->make_plugin( 'gandalf-callback-test' );
		$scan_id = '33333333-3333-4333-8333-333333333333';

		update_post_meta(
			$plugin->ID,
			Plugin_Scan_Gandalf::PENDING_META_KEY,
			[
				$scan_id => [
					'version'      => '1.2.3',
					'release_ref'  => '1.2.3',
					'requested_at' => time(),
				],
			]
		);

		$result = Plugin_Scan_Gandalf::handle_callback(
			$plugin,
			[
				'status'          => 'completed',
				'scan_id'         => $scan_id,
				'subject_type'    => 'plugin',
				'slug'            => $plugin->post_name,
				'version'         => '1.2.3',
				'release_ref'     => '1.2.3',
				'completed_at'    => time(),
				'verdict_hash'    => 'verdict-hash',
				'findings_count'  => 1,
				'severity_counts' => [ 'high' => 1 ],
				'scanner_version' => '0.1.0',
				'report_url'      => 'https://gandalf.wordpress.org/admin/runs/' . $scan_id,
			]
		);

		$this->assertTrue( $result );
		$this->assertSame( [], get_post_meta( $plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true ) );

		$notified = get_post_meta( $plugin->ID, Plugin_Scan_Gandalf::NOTIFIED_META_KEY, true );
		$this->assertIsArray( $notified );
		$this->assertArrayHasKey( 'verdict-hash', $notified );
	}

	/**
	 * Intercept Gandalf HTTP requests.
	 *
	 * @param false|array|\WP_Error $preempt     Preemptive response.
	 * @param array                 $parsed_args Request arguments.
	 * @param string                $url         Request URL.
	 * @return false|array|\WP_Error Preemptive response.
	 */
	public function pre_http_request( $preempt, $parsed_args, $url ) {
		if ( Plugin_Scan_Gandalf::ENDPOINT !== $url ) {
			return $preempt;
		}

		$this->http_requests[] = [
			'url'  => $url,
			'args' => $parsed_args,
		];

		$payload = json_decode( $parsed_args['body'], true );

		return [
			'headers'  => [],
			'body'     => wp_json_encode(
				[
					'scan_id'     => $payload['scan_id'],
					'accepted_at' => time(),
				]
			),
			'response' => [
				'code'    => 202,
				'message' => 'Accepted',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Force REST URLs to the public Plugin Directory shape.
	 *
	 * @param string $url     REST URL.
	 * @param string $path    REST path.
	 * @param int    $blog_id Blog ID.
	 * @param string $scheme  URL scheme.
	 * @return string REST URL.
	 */
	public function filter_rest_url( $url, $path, $blog_id, $scheme ) {
		return 'https://wordpress.org/plugins/wp-json/' . ltrim( $path, '/' );
	}

	/**
	 * Create a plugin post for tests.
	 *
	 * @param string $slug Plugin slug.
	 * @param array  $meta Plugin meta.
	 * @return \WP_Post Plugin post.
	 */
	private function make_plugin( $slug, $meta = [] ) {
		$now     = current_time( 'mysql' );
		$now_gmt = current_time( 'mysql', true );

		$post_id = wp_insert_post(
			[
				'post_type'         => 'plugin',
				'post_status'       => 'publish',
				'post_name'         => $slug,
				'post_title'        => ucwords( str_replace( '-', ' ', $slug ) ),
				'post_date'         => $now,
				'post_date_gmt'     => $now_gmt,
				'post_modified'     => $now,
				'post_modified_gmt' => $now_gmt,
			]
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$meta = array_merge(
			[
				'version'    => '1.0.0',
				'stable_tag' => 'trunk',
			],
			$meta
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		wp_cache_delete( $slug, 'plugin-slugs' );
		$this->plugin_ids[] = $post_id;

		return get_post( $post_id );
	}

	/**
	 * Return the last Gandalf request payload.
	 *
	 * @return array Request payload.
	 */
	private function last_gandalf_payload() {
		$this->assertNotEmpty( $this->http_requests );

		$request = end( $this->http_requests );
		$payload = json_decode( $request['args']['body'], true );

		$this->assertIsArray( $payload );

		return $payload;
	}
}
