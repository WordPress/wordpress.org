<?php
/**
 * Tests for the Gandalf scan callback REST endpoint.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests the full REST chain for security scan callbacks — authentication,
 * routing, the contract schema, and the policy outcome — by dispatching a
 * production-shaped callback body through the REST server.
 *
 * @group api
 */
#[Group( 'api' )]
class Gandalf_Scan_Endpoint_Test extends TestCase {

	/** The scan ID used for the pending scan fixture. */
	private const SCAN_ID = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';

	/** The version and release ref used for the pending scan fixture. */
	private const VERSION = '2.7.0';

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
	 * Create a published plugin with a pending scan and a fresh REST server.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		// Tools::audit_log() reads it unguarded.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		if ( ! defined( 'WP_GANDALF_SCAN_SHARED_SECRET' ) ) {
			define( 'WP_GANDALF_SCAN_SHARED_SECRET', 'test-shared-secret' );
		}

		// A fresh server per test; creating it fires rest_api_init, which registers the route.
		global $wp_rest_server;
		$wp_rest_server = null;
		rest_get_server();

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'endpoint-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Scan Endpoint Test Plugin',
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

		update_post_meta( $this->plugin->ID, 'version', self::VERSION );
		update_post_meta( $this->plugin->ID, 'stable_tag', self::VERSION );
		update_post_meta(
			$this->plugin->ID,
			Plugin_Scan_Gandalf::PENDING_META_KEY,
			array(
				self::SCAN_ID => array(
					'version'      => self::VERSION,
					'release_ref'  => self::VERSION,
					'requested_at' => time(),
				),
			)
		);
	}

	/**
	 * Build a callback body mirroring the shape of a production scanner delivery.
	 *
	 * @param array $overrides Fields to override.
	 * @return array The callback data.
	 */
	private function payload( array $overrides = array() ): array {
		$defaults = array(
			'status'          => 'completed',
			'scan_id'         => self::SCAN_ID,
			'subject_type'    => 'plugin',
			'slug'            => $this->plugin->post_name,
			'version'         => self::VERSION,
			'release_ref'     => self::VERSION,
			'completed_at'    => time(),
			'verdict_hash'    => '18c5a24a9bfd7b3245ed2c23e49a0c88',
			'findings_count'  => 3,
			'findings'        => array(
				array(
					'id'            => 'f1f1f1f1-1111-5111-a111-111111111111',
					'ref'           => 'prompt-security.sensitive_data.secret_persistence',
					'title'         => 'Payment secrets are persistently stored in user metadata and debug order logs.',
					'severity'      => 'error',
					'file_path'     => 'includes/ExampleGatewayDebit.php',
					'line'          => 1723,
					'code_snippet'  => "                        'secretCode' => \$cardSecret,\n                    );\n",
					'explanation'   => "An authenticated customer submitting a saved-card checkout controls `example_secret` at line 1233. When card saving is selected, that secret is added as `secretCode` to `\$cardsArray` at line 1723 and the complete array is persisted with `update_user_meta()` at line 1727. The checkout nonce protects submission integrity but does not make post-authorization secret retention safe.\n\nAdditionally, when debug logging is enabled, the request body—including `SecretCode` or a saved-card token—is copied into order logs at lines 1882-1896 without removing the secret. The first concrete effect is durable storage of payment authentication data in WordPress user/order metadata, extending exposure to database backups and any principal or integration able to read that metadata.\n\nExploitation requires subsequent access to the database, backups, or an applicable metadata-reading path; this chunk does not establish an unauthenticated read endpoint. The medium 5.5 risk reflects highly sensitive data retention but a separate access requirement and the saved-card/debug feature conditions.",
					'risk_score'    => 5.5,
					'investigation' => array(
						'status'  => 'skipped',
						'result'  => 'unknown',
						'summary' => 'Investigation was not attempted because the finding is below the configured risk threshold.',
					),
				),
				array(
					'id'            => 'f2f2f2f2-2222-5222-a222-222222222222',
					'ref'           => 'prompt-security.sensitive_data.secret_in_url',
					'title'         => 'Full card numbers are sent in REST GET query strings during brand detection.',
					'severity'      => 'warning',
					'file_path'     => 'resources/js/example/example-brand-detector.js',
					'line'          => 52,
					'code_snippet'  => "        return fetch(restUrl + 'exampleGateway/getCardBrand?number=' + encodeURIComponent(cleanNumber) + '&gateway=credit', {\n            method: 'GET',\n            headers: {",
					'explanation'   => "A checkout customer controls the card-number input. After six digits, `fetchCardBrand()` passes the entire whitespace-stripped value as the `number` query parameter of a GET request; the debit detector duplicates this behavior at `resources/js/example/example-debit-detector.js:52-58`. The REST nonce authenticates the request context but does not prevent the URL from being retained.\n\nOnly a six-digit prefix is needed: the server-side lookup truncates the value to six characters at `includes/ExampleGatewayEndpoint.php:392`. Sending the full number in a URL unnecessarily exposes it to web-server, reverse-proxy, CDN, security-monitoring, or APM access logs that record query strings, rather than limiting it to the payment submission body.\n\nThe proven effect is transmission of the number in a log-prone URL, not public disclosure. Recovery requires access to infrastructure or application logs, whose configuration and readers are not shown. This separate access requirement keeps the estimated risk at medium 4.5 despite routine checkout triggering and potentially high confidentiality impact.",
					'risk_score'    => 4.5,
					'investigation' => array(
						'status'  => 'skipped',
						'result'  => 'unknown',
						'summary' => 'Investigation was not attempted because the finding is below the configured risk threshold.',
					),
				),
				array(
					'id'            => 'f3f3f3f3-3333-5333-a333-333333333333',
					'ref'           => 'prompt-security.payment.transaction_binding',
					'title'         => 'Partial-capture handlers do not bind the client-supplied transaction ID to the selected order.',
					'severity'      => 'warning',
					'file_path'     => 'includes/ExampleGatewayCredit.php',
					'line'          => 1658,
					'code_snippet'  => "        \$order_id = isset(\$_POST['order_id']) ? intval(\$_POST['order_id']) : 0;\n        \$capture_amount = isset(\$_POST['capture_amount']) ? floatval(\$_POST['capture_amount']) : 0;\n        \$transaction_id = isset(\$_POST['transaction_id']) ? sanitize_text_field(wp_unslash(\$_POST['transaction_id'])) : '';",
					'explanation'   => "An authenticated principal holding `edit_shop_orders` and a valid partial-capture nonce can supply `order_id`, `capture_amount`, and `transaction_id` at lines 1658-1660. The handler verifies the selected order's gateway, capture mode, and prior-capture metadata, but never compares the submitted transaction ID with `\$order->get_transaction_id()`.\n\nThe unbound value is passed to `processPartialCapture()` at line 1702 and incorporated into the authenticated capture URL at line 1800. If the caller knows another transaction identifier accepted under the merchant credentials, they can apply the eligibility checks of one order while attempting a capture against a different provider transaction. The debit handler repeats the same flow at `includes/ExampleGatewayDebit.php:2394-2438`.\n\nExploitation also requires the order-edit capability, a nonce, a valid unrelated transaction ID, and provider acceptance. Those requirements bound the estimated risk to medium 5.0 and warrant verification rather than an asserted exploit.",
					'risk_score'    => 5,
					'investigation' => array(
						'status'  => 'skipped',
						'result'  => 'unknown',
						'summary' => 'Investigation was not attempted because the finding is below the configured risk threshold.',
					),
				),
			),
			'max_risk_score'  => 5.5,
			'severity_counts' => array(
				'error'   => 1,
				'warning' => 2,
			),
			'scanner_version' => '0.3.0',
			'report_url'      => 'https://gandalf.wordpress.org/admin/runs/' . self::SCAN_ID,
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * Dispatch a callback through the REST server, as the scanner would.
	 *
	 * @param array       $payload The callback body.
	 * @param string|null $bearer  The bearer token; null for the shared secret, '' to omit the header.
	 * @param string|null $slug    The routed plugin slug; null for the fixture plugin.
	 * @return \WP_REST_Response The response.
	 */
	private function dispatch( array $payload, ?string $bearer = null, ?string $slug = null ): \WP_REST_Response {
		$slug   = $slug ?? $this->plugin->post_name;
		$bearer = $bearer ?? WP_GANDALF_SCAN_SHARED_SECRET;

		$request = new \WP_REST_Request( 'POST', "/plugins/v1/plugin/{$slug}/gandalf-scan" );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( $payload ) );

		if ( '' !== $bearer ) {
			$request->set_header( 'Authorization', 'Bearer ' . $bearer );
		}

		return rest_do_request( $request );
	}

	/**
	 * A production-shaped callback is accepted end to end.
	 */
	public function test_callback_is_accepted(): void {
		$response = $this->dispatch( $this->payload() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'success' => true ), $response->get_data() );

		$this->assertEmpty( get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true ) );
	}

	/**
	 * A high-risk callback blocks the release end to end.
	 */
	public function test_high_risk_callback_blocks_release(): void {
		update_post_meta(
			$this->plugin->ID,
			'releases',
			array(
				array(
					'date'                     => time(),
					'tag'                      => self::VERSION,
					'version'                  => self::VERSION,
					'zips_built'               => true,
					'zips_built_from_revision' => 0,
					'confirmations'            => array(),
					'confirmed'                => true,
					'confirmations_required'   => 0,
					'committer'                => array(),
					'revision'                 => array(),
					'release_delay'            => DAY_IN_SECONDS,
				),
			)
		);

		$response = $this->dispatch( $this->payload( array( 'max_risk_score' => 9.8 ) ) );

		$this->assertSame( 200, $response->get_status() );

		$release = Plugin_Directory::get_release( get_post( $this->plugin->ID ), self::VERSION );
		$this->assertTrue( API_Update_Updater::is_release_blocked( $release ) );
	}

	/**
	 * A production-shaped failure report is accepted and recorded.
	 */
	public function test_failed_callback_is_accepted(): void {
		$response = $this->dispatch(
			array(
				'status'       => 'failed',
				'scan_id'      => self::SCAN_ID,
				'subject_type' => 'plugin',
				'slug'         => $this->plugin->post_name,
				'version'      => self::VERSION,
				'release_ref'  => self::VERSION,
				'completed_at' => time(),
				'report_url'   => 'https://gandalf.wordpress.org/admin/runs/' . self::SCAN_ID,
				'error'        => array(
					'kind'    => 'timeout',
					'message' => 'gandalf scan exceeded worker deadline',
				),
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'success' => true ), $response->get_data() );

		$last_error = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::LAST_ERROR_META_KEY, true );
		$this->assertSame( 'timeout', $last_error['kind'] );
	}

	/**
	 * Contract additions the directory does not know yet — new fields at any
	 * level, a new severity, a recalibrated score — do not void a delivery.
	 */
	public function test_unknown_contract_additions_are_accepted(): void {
		$payload = $this->payload(
			array(
				'scan_duration'  => 314,
				'max_risk_score' => 10.5,
			)
		);

		$payload['findings'][0]['severity']                = 'catastrophic';
		$payload['findings'][0]['exploit_maturity']        = 'proof-of-concept';
		$payload['findings'][0]['investigation']['effort'] = 'medium';

		$response = $this->dispatch( $payload );

		$this->assertSame( 200, $response->get_status() );
		$this->assertEmpty( get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true ) );
	}

	/**
	 * A payload asserting a different plugin than the routed one is rejected
	 * and recorded.
	 */
	public function test_slug_mismatch_is_rejected(): void {
		$response = $this->dispatch( $this->payload( array( 'slug' => 'some-other-plugin' ) ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'invalid_gandalf_scan', $response->get_data()['code'] );

		$last_error = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::LAST_ERROR_META_KEY, true );
		$this->assertSame( 'invalid_gandalf_scan', $last_error['kind'] );

		$pending = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true );
		$this->assertArrayHasKey( self::SCAN_ID, $pending );
	}

	/**
	 * A callback without the bearer header is rejected before any processing.
	 */
	public function test_missing_bearer_is_rejected(): void {
		$response = $this->dispatch( $this->payload(), '' );

		$this->assertSame( 401, $response->get_status() );
		$this->assertPendingScanUntouched();
	}

	/**
	 * A callback with the wrong bearer token is rejected before any processing.
	 */
	public function test_wrong_bearer_is_rejected(): void {
		$response = $this->dispatch( $this->payload(), 'not-the-shared-secret' );

		$this->assertSame( 401, $response->get_status() );
		$this->assertPendingScanUntouched();
	}

	/**
	 * A mistyped contract field is rejected by the route schema.
	 */
	public function test_invalid_field_type_is_rejected(): void {
		$response = $this->dispatch( $this->payload( array( 'max_risk_score' => 'critical' ) ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rest_invalid_param', $response->get_data()['code'] );
		$this->assertPendingScanUntouched();
	}

	/**
	 * A missing required contract field is rejected by the route schema.
	 */
	public function test_missing_required_field_is_rejected(): void {
		$payload = $this->payload();
		unset( $payload['scan_id'] );

		$response = $this->dispatch( $payload );

		$this->assertSame( 400, $response->get_status() );
		$this->assertPendingScanUntouched();
	}

	/**
	 * A completed callback that reports no verdict is rejected by the route schema.
	 *
	 * The verdict fields cannot be marked required — a failed report omits them
	 * legitimately — so the status validator enforces that half of the contract.
	 */
	public function test_completed_callback_without_verdict_is_rejected(): void {
		foreach ( array( 'verdict_hash', 'findings_count', 'severity_counts', 'max_risk_score', 'findings', 'report_url' ) as $field ) {
			$payload = $this->payload();
			unset( $payload[ $field ] );

			$response = $this->dispatch( $payload );

			$this->assertSame( 400, $response->get_status(), "Missing {$field} was accepted." );
			$this->assertStringContainsString( $field, $response->get_data()['data']['params']['status'] );
			$this->assertPendingScanUntouched();
		}
	}

	/**
	 * A failed callback that reports no error is rejected by the route schema.
	 */
	public function test_failed_callback_without_error_is_rejected(): void {
		$response = $this->dispatch(
			array(
				'status'       => 'failed',
				'scan_id'      => self::SCAN_ID,
				'subject_type' => 'plugin',
				'slug'         => $this->plugin->post_name,
				'version'      => self::VERSION,
				'release_ref'  => self::VERSION,
				'completed_at' => time(),
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertStringContainsString( 'error', $response->get_data()['data']['params']['status'] );
		$this->assertPendingScanUntouched();
	}

	/**
	 * A completed callback reporting a verdict with no findings is accepted.
	 */
	public function test_completed_callback_without_findings_is_accepted(): void {
		$response = $this->dispatch(
			$this->payload(
				array(
					'findings_count'  => 0,
					'findings'        => array(),
					'severity_counts' => array(),
					'max_risk_score'  => 0,
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertEmpty( get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true ) );
	}

	/**
	 * A callback routed to an unknown plugin is rejected.
	 */
	public function test_unknown_plugin_is_rejected(): void {
		$response = $this->dispatch( $this->payload(), null, 'no-such-plugin' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertPendingScanUntouched();
	}

	/**
	 * Assert the pending scan was not consumed and no error was recorded.
	 */
	private function assertPendingScanUntouched(): void {
		$pending = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true );
		$this->assertArrayHasKey( self::SCAN_ID, $pending );
		$this->assertEmpty( get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::LAST_ERROR_META_KEY, true ) );
	}
}
