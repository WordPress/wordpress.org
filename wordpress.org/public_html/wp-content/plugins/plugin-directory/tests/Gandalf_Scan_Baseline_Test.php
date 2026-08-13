<?php
/**
 * Tests for the security scan diff baseline.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests that scan dispatches baseline against the served release, so a release
 * blocked by a scan can't become the diff baseline for its own re-release.
 *
 * Extends the plain PHPUnit TestCase: WP_UnitTestCase is not compatible with
 * the PHPUnit 11 runner used by this suite. Isolation comes from giving every
 * test its own plugin post instead of per-test transactions.
 *
 * The group is declared as an attribute as well as `@group`: PHPUnit 11 ignores
 * a class-level `@group` docblock, while older runners ignore the attribute.
 *
 * @group jobs
 */
#[Group( 'jobs' )]
class Gandalf_Scan_Baseline_Test extends TestCase {

	/** The version the update_source row serves — the last release users actually got. */
	private const SERVED_VERSION = '1.4.3';

	/** The version whose release was blocked by a scan — the previous import. */
	private const BLOCKED_VERSION = '1.4.4';

	/** The version being imported and scanned. */
	private const NEW_VERSION = '1.4.5';

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
	 * The scan request body captured from the dispatch, or null when none was sent.
	 *
	 * @var array|null
	 */
	private ?array $request_body = null;

	/**
	 * The pre_http_request callback capturing the dispatch.
	 *
	 * @var callable
	 */
	private $http_mock;

	/**
	 * Create a published plugin importing NEW_VERSION after a blocked BLOCKED_VERSION.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		if ( ! defined( 'WP_GANDALF_SCAN_SHARED_SECRET' ) ) {
			define( 'WP_GANDALF_SCAN_SHARED_SECRET', 'test-shared-secret' );
		}

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'baseline-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Scan Baseline Test Plugin',
				'post_status' => 'publish',
			)
		);

		$this->assertInstanceOf( \WP_Post::class, $plugin );
		$this->plugin = $plugin;

		/*
		 * The stub update_source table survives across runs — the WP test
		 * installer only drops core tables — so clear leftovers that would
		 * collide with this run's plugin ID or read as a served version.
		 */
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'update_source', array( 'plugin_id' => $this->plugin->ID ) );
		$wpdb->delete( $wpdb->prefix . 'update_source', array( 'plugin_slug' => $this->plugin->post_name ) );

		update_post_meta( $this->plugin->ID, 'version', self::NEW_VERSION );
		update_post_meta( $this->plugin->ID, 'stable_tag', self::NEW_VERSION );
		update_post_meta( $this->plugin->ID, 'last_version', self::BLOCKED_VERSION );
		update_post_meta( $this->plugin->ID, 'last_stable_tag', self::BLOCKED_VERSION );

		$this->request_body = null;
		$this->http_mock    = function ( $preempt, array $parsed_args, string $url ) {
			$this->assertSame( Plugin_Scan_Gandalf::ENDPOINT, $url );
			$this->request_body = json_decode( $parsed_args['body'], true );

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'scan_id' => $this->request_body['scan_id'] ) ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
			);
		};
		add_filter( 'pre_http_request', $this->http_mock, 10, 3 );
	}

	/**
	 * Unhook the HTTP mock.
	 */
	protected function tearDown(): void {
		remove_filter( 'pre_http_request', $this->http_mock, 10 );

		parent::tearDown();
	}

	/**
	 * Insert an update_source row serving a version.
	 *
	 * @param string $version    The served version.
	 * @param string $stable_tag The served stable tag.
	 */
	private function stage_served_release( string $version, string $stable_tag ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'update_source',
			array(
				'plugin_id'        => $this->plugin->ID,
				'plugin_slug'      => $this->plugin->post_name,
				'available'        => 1,
				'version'          => $version,
				'stable_tag'       => $stable_tag,
				'plugin_name'      => $this->plugin->post_title,
				'requires_plugins' => '',
				'last_updated'     => $this->plugin->post_modified,
			)
		);
	}

	/**
	 * Dispatch a scan for NEW_VERSION and return the captured request body.
	 *
	 * @return array The captured scan request body.
	 */
	private function dispatch_scan(): array {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- A successful dispatch logs an E_USER_NOTICE; keep it out of the test output.
		set_error_handler( static fn (): bool => true, E_USER_NOTICE );

		try {
			$dispatched = Plugin_Scan_Gandalf::dispatch_from_import_context(
				$this->plugin,
				array(
					'stable_tag'       => self::NEW_VERSION,
					'old_stable_tag'   => self::BLOCKED_VERSION,
					'changed_svn_tags' => array( self::NEW_VERSION ),
				)
			);
		} finally {
			restore_error_handler();
		}

		$this->assertTrue( $dispatched );
		$this->assertIsArray( $this->request_body );

		return $this->request_body;
	}

	/**
	 * With a version held out of update_source, the follow-up release is diffed
	 * against the served release, not the held one.
	 */
	public function test_served_release_is_baseline(): void {
		$this->stage_served_release( self::SERVED_VERSION, self::SERVED_VERSION );

		$request = $this->dispatch_scan();

		$this->assertSame( self::NEW_VERSION, $request['version'] );
		$this->assertSame( self::SERVED_VERSION, $request['previous_version'] );
		$this->assertSame( self::SERVED_VERSION, $request['previous_release_ref'] );
		$this->assertSame(
			'https://downloads.wordpress.org/plugin/' . $this->plugin->post_name . '.' . self::SERVED_VERSION . '.zip',
			$request['previous_zip_url']
		);
	}

	/**
	 * When the row already serves the scanned version — imports without a
	 * cooldown write it before the scan dispatches — the last imported release
	 * remains the baseline.
	 */
	public function test_falls_back_to_last_release_when_scanned_version_is_served(): void {
		$this->stage_served_release( self::NEW_VERSION, self::NEW_VERSION );

		$request = $this->dispatch_scan();

		$this->assertSame( self::BLOCKED_VERSION, $request['previous_version'] );
		$this->assertSame( self::BLOCKED_VERSION, $request['previous_release_ref'] );
	}

	/**
	 * A plugin with no update_source row falls back to the last imported release.
	 */
	public function test_falls_back_to_last_release_without_served_release(): void {
		$request = $this->dispatch_scan();

		$this->assertSame( self::BLOCKED_VERSION, $request['previous_version'] );
		$this->assertSame( self::BLOCKED_VERSION, $request['previous_release_ref'] );
	}

	/**
	 * A trunk-served release has no retrievable previous ZIP — its build was
	 * overwritten — so the scan carries no baseline.
	 */
	public function test_no_baseline_when_serving_from_trunk(): void {
		$this->stage_served_release( self::SERVED_VERSION, 'trunk' );

		$request = $this->dispatch_scan();

		$this->assertNull( $request['previous_version'] );
		$this->assertNull( $request['previous_release_ref'] );
		$this->assertNull( $request['previous_zip_url'] );
	}
}
