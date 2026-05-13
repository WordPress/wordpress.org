<?php
/**
 * Tests for the Gandalf scan integration.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\API\Routes\Gandalf_Scan;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Updates;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Updates_Gandalf;

/**
 * @group gandalf
 */
class Gandalf_Scan_Test extends TestCase {

	/**
	 * Captured Gandalf HTTP requests.
	 *
	 * @var array
	 */
	private $http_requests = array();

	/**
	 * Plugin slugs used by the test.
	 *
	 * @var string[]
	 */
	private $plugin_slugs = array();

	/**
	 * Plugin post IDs used by the test.
	 *
	 * @var int[]
	 */
	private $plugin_ids = array();

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

		add_filter( 'pre_http_request', array( $this, 'pre_http_request' ), 10, 3 );
		add_filter( 'rest_url', array( $this, 'filter_rest_url' ), 10, 4 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'pre_http_request' ), 10 );
		remove_filter( 'rest_url', array( $this, 'filter_rest_url' ), 10 );

		foreach ( $this->plugin_slugs as $slug ) {
			$this->clear_scan_cron( $slug );
			wp_cache_delete( $slug, 'plugin-slugs' );
		}

		foreach ( $this->plugin_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		update_option( 'home', $this->home_url );
		update_option( 'siteurl', $this->site_url );

		parent::tearDown();
	}

	public function test_import_queues_existing_scan_plugin_job_with_gandalf_context() {
		$plugin = $this->make_plugin( 'gandalf-queue-test' );

		Plugin_Updates::wporg_plugins_imported(
			$plugin,
			'1.2.3',
			'1.2.2',
			array( '1.2.3', 'trunk' ),
			12345,
			array( 'stable_tag_invalid' => true )
		);

		$events = $this->scheduled_scan_events( $plugin->post_name );

		$this->assertCount( 1, $events );
		$this->assertSame( $plugin->post_name, $events[0]['args'][0] );
		$this->assertSame( array( '1.2.3', 'trunk' ), $events[0]['args'][1] );

		$context = $events[0]['args'][2];
		$this->assertSame( '1.2.3', $context['stable_tag'] );
		$this->assertSame( '1.2.2', $context['old_stable_tag'] );
		$this->assertSame( array( '1.2.3', 'trunk' ), $context['changed_svn_tags'] );
		$this->assertSame( 12345, $context['svn_revision'] );
		$this->assertSame( array( 'stable_tag_invalid' => true ), $context['warnings'] );
		$this->assertArrayNotHasKey( 'version', $context );
		$this->assertArrayNotHasKey( 'last_version', $context );
	}

	public function test_import_does_not_queue_for_old_tag_only_changes() {
		$plugin = $this->make_plugin( 'gandalf-old-tag-test' );

		Plugin_Updates::wporg_plugins_imported(
			$plugin,
			'1.2.3',
			'1.2.3',
			array( '1.0.0' ),
			12345
		);

		$this->assertSame( array(), $this->scheduled_scan_events( $plugin->post_name ) );
	}

	public function test_dispatches_tag_release_payload() {
		$plugin = $this->make_plugin(
			'gandalf-tag-test',
			array(
				'version'      => '1.2.3',
				'last_version' => '1.2.2',
			)
		);

		$result = Plugin_Updates_Gandalf::dispatch_from_import_context(
			$plugin,
			array(
				'stable_tag'       => '1.2.3',
				'old_stable_tag'   => '1.2.2',
				'changed_svn_tags' => array( '1.2.3' ),
				'svn_revision'     => 12345,
				'warnings'         => array(),
			)
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
			array(
				'version'      => '1.2.3',
				'last_version' => '1.2.2',
			)
		);

		$result = Plugin_Updates_Gandalf::dispatch_from_import_context(
			$plugin,
			array(
				'stable_tag'       => 'trunk',
				'old_stable_tag'   => 'trunk',
				'changed_svn_tags' => array( 'trunk' ),
				'svn_revision'     => 12345,
				'warnings'         => array(),
			)
		);

		$this->assertTrue( $result );

		$payload = $this->last_gandalf_payload();
		$this->assertSame( 'trunk', $payload['release_ref'] );
		$this->assertSame( 'https://downloads.wordpress.org/plugin/gandalf-trunk-test.zip', $payload['current_zip_url'] );
		$this->assertNull( $payload['previous_version'] );
		$this->assertNull( $payload['previous_release_ref'] );
		$this->assertNull( $payload['previous_zip_url'] );
	}

	public function test_does_not_dispatch_tag_stable_release_for_trunk_only_commit() {
		$plugin = $this->make_plugin(
			'gandalf-trunk-only-test',
			array(
				'version' => '1.2.3',
			)
		);

		$result = Plugin_Updates_Gandalf::dispatch_from_import_context(
			$plugin,
			array(
				'stable_tag'       => '1.2.3',
				'old_stable_tag'   => '1.2.3',
				'changed_svn_tags' => array( 'trunk' ),
				'svn_revision'     => 12345,
				'warnings'         => array(),
			)
		);

		$this->assertFalse( $result );
		$this->assertSame( array(), $this->http_requests );
	}

	public function test_completed_callback_clears_pending_scan_and_records_notification_hash() {
		$plugin  = $this->make_plugin( 'gandalf-callback-test' );
		$scan_id = '33333333-3333-4333-8333-333333333333';

		update_post_meta(
			$plugin->ID,
			Plugin_Updates_Gandalf::PENDING_META_KEY,
			array(
				$scan_id => array(
					'version'      => '1.2.3',
					'release_ref'  => '1.2.3',
					'requested_at' => time(),
				),
			)
		);

		$result = Plugin_Updates_Gandalf::handle_callback(
			$plugin,
			array(
				'status'          => 'completed',
				'scan_id'         => $scan_id,
				'subject_type'    => 'plugin',
				'slug'            => $plugin->post_name,
				'version'         => '1.2.3',
				'release_ref'     => '1.2.3',
				'completed_at'    => time(),
				'verdict_hash'    => 'verdict-hash',
				'findings_count'  => 1,
				'severity_counts' => array( 'high' => 1 ),
				'scanner_version' => '0.1.0',
				'report_url'      => 'https://gandalf.wordpress.org/admin/runs/' . $scan_id,
			)
		);

		$this->assertTrue( $result );
		$this->assertSame( array(), get_post_meta( $plugin->ID, Plugin_Updates_Gandalf::PENDING_META_KEY, true ) );

		$notified = get_post_meta( $plugin->ID, Plugin_Updates_Gandalf::NOTIFIED_META_KEY, true );
		$this->assertIsArray( $notified );
		$this->assertArrayHasKey( 'verdict-hash', $notified );
	}

	public function test_callback_validation_rejects_invalid_status_fixture() {
		$plugin = $this->make_plugin( 'hello-dolly' );
		$data   = json_decode( file_get_contents( __DIR__ . '/fixtures/gandalf-contract/callback-failed-bad-status.invalid.json' ), true );

		$reflection = new ReflectionClass( Gandalf_Scan::class );
		$route      = $reflection->newInstanceWithoutConstructor();
		$method     = $reflection->getMethod( 'validate_callback_payload' );
		$method->setAccessible( true );

		$result = $method->invoke( $route, $plugin, $data );

		$this->assertTrue( is_wp_error( $result ) );
		$this->assertSame( 'invalid_gandalf_scan_callback', $result->get_error_code() );
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
		if ( Plugin_Updates_Gandalf::ENDPOINT !== $url ) {
			return $preempt;
		}

		$this->http_requests[] = array(
			'url'  => $url,
			'args' => $parsed_args,
		);

		$payload = json_decode( $parsed_args['body'], true );

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'scan_id'     => $payload['scan_id'],
					'accepted_at' => time(),
				)
			),
			'response' => array(
				'code'    => 202,
				'message' => 'Accepted',
			),
			'cookies'  => array(),
			'filename' => null,
		);
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
	private function make_plugin( $slug, $meta = array() ) {
		$now     = current_time( 'mysql' );
		$now_gmt = current_time( 'mysql', true );

		$post_id = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_status'       => 'publish',
				'post_name'         => $slug,
				'post_title'        => ucwords( str_replace( '-', ' ', $slug ) ),
				'post_date'         => $now,
				'post_date_gmt'     => $now_gmt,
				'post_modified'     => $now,
				'post_modified_gmt' => $now_gmt,
			)
		);

		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );

		$meta = array_merge(
			array(
				'version'    => '1.0.0',
				'stable_tag' => 'trunk',
			),
			$meta
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		wp_cache_delete( $slug, 'plugin-slugs' );
		$this->plugin_slugs[] = $slug;
		$this->plugin_ids[]   = $post_id;

		return get_post( $post_id );
	}

	/**
	 * Fetch scheduled scan_plugin events for a plugin.
	 *
	 * @param string $slug Plugin slug.
	 * @return array Scheduled events.
	 */
	private function scheduled_scan_events( $slug ) {
		$events = array();
		$hook   = "scan_plugin:{$slug}";
		$crons  = _get_cron_array();

		if ( ! is_array( $crons ) ) {
			return $events;
		}

		foreach ( $crons as $timestamp => $hooks ) {
			if ( empty( $hooks[ $hook ] ) ) {
				continue;
			}

			foreach ( $hooks[ $hook ] as $event ) {
				$events[] = array(
					'timestamp' => $timestamp,
					'args'      => $event['args'],
				);
			}
		}

		return $events;
	}

	/**
	 * Clear scheduled scan_plugin events for a plugin.
	 *
	 * @param string $slug Plugin slug.
	 */
	private function clear_scan_cron( $slug ) {
		foreach ( $this->scheduled_scan_events( $slug ) as $event ) {
			wp_unschedule_event( $event['timestamp'], "scan_plugin:{$slug}", $event['args'] );
		}
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
