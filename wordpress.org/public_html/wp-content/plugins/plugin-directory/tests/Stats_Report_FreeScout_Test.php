<?php
/**
 * Tests the stats report's email queue stats, from FreeScout.
 *
 * @package plugin-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Admin\Tools\Stats_Report;

/**
 * Tests for `Stats_Report::get_stats()` once the plugins team is on FreeScout.
 *
 * @group plugin-directory
 */
#[Group( 'plugin-directory' )]
class Stats_Report_FreeScout_Test extends TestCase {

	/**
	 * A made-up ID of a conversation in WordPress.org's copy.
	 *
	 * @var int
	 */
	const CONVERSATION_ID = 4242;

	/**
	 * Filters added during a test, each a hook and its callback, removed again on teardown.
	 *
	 * @var array[]
	 */
	protected array $filters = array();

	/**
	 * Requests sent to FreeScout's API.
	 *
	 * @var string[]
	 */
	protected array $requests = array();

	/**
	 * IDs of posts created during a test.
	 *
	 * @var int[]
	 */
	protected array $posts = array();

	/**
	 * Configures FreeScout, and answers its Reports API in place of the API & Webhooks and Reports modules.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->filter( 'wporg_plugins_freescout_mailbox_id', static fn(): int => 3 );
		$this->filter( 'wporg_plugins_freescout_api_key', static fn(): string => 'test-key' );
		$this->filter(
			'pre_http_request',
			function ( mixed $preempt, array $args, string $url ): mixed {
				if ( ! str_starts_with( $url, 'https://freescout.wordpress.net/api/' ) ) {
					return $preempt;
				}
				$this->requests[] = $url;

				$reports = array(
					'conversations' => array(
						'total'     => array( 'value' => 128 ),
						'new'       => array( 'value' => 96 ),
						'messages'  => array( 'value' => 210 ),
						'customers' => array( 'value' => 84 ),
						'conv_day'  => array( 'value' => 4 ),
						'busy_day'  => array( 'value' => 'Monday' ),
					),
					'productivity'  => array(
						'replies'          => array( 'value' => 57 ),
						'closed'           => array( 'value' => 40 ),
						'customers_helped' => array( 'value' => 33 ),
					),
				);
				$report  = (string) preg_replace( '#^.*/api/reports/([a-z]+).*$#', '$1', $url );

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode(
						array(
							'report'  => $report,
							'metrics' => $reports[ $report ] ?? array(),
						)
					),
				);
			}
		);
	}

	/**
	 * Removes what the test added.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		global $wpdb;

		foreach ( $this->filters as list( $hook, $callback ) ) {
			remove_filter( $hook, $callback );
		}
		foreach ( $this->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$wpdb->delete( "{$wpdb->base_prefix}helpscout", array( 'id' => self::CONVERSATION_ID ) );
		$wpdb->delete( "{$wpdb->base_prefix}helpscout_meta", array( 'helpscout_id' => self::CONVERSATION_ID ) );

		parent::tearDown();
	}

	/**
	 * The queue stats are the Plugins mailbox's, for the report's dates, from FreeScout's conversations and productivity
	 * reports.
	 *
	 * @return void
	 */
	public function test_queue_stats_come_from_freescouts_reports(): void {
		$stats = Stats_Report::instance()->get_stats(
			array(
				'date'     => '2026-10-13',
				'num_days' => 7,
			)
		);

		$this->assertSame(
			array(
				'email_queue_total_conversations'   => 128,
				'email_queue_new_conversations'     => 96,
				'email_queue_customers'             => 84,
				'email_queue_conversations_per_day' => 4,
				'email_queue_busiest_day'           => 'Monday',
				'email_queue_messages_received'     => 210,
				'email_queue_replies_sent'          => 57,
			),
			array_intersect_key( $stats, array_flip( preg_grep( '/^email_queue_/', array_keys( $stats ) ) ) )
		);

		$query = 'filters%5Bfrom%5D=2026-10-06&filters%5Bto%5D=2026-10-13&filters%5Bmailbox%5D=3';
		$this->assertSame(
			array(
				'https://freescout.wordpress.net/api/reports/conversations?' . $query,
				'https://freescout.wordpress.net/api/reports/productivity?' . $query,
			),
			$this->requests
		);
	}

	/**
	 * Pending plugins are broken down by who the review waits on, from WordPress.org's copy, without Help Scout.
	 *
	 * @return void
	 */
	public function test_breaks_down_pending_plugins_without_helpscout(): void {
		global $wpdb;

		$before = Stats_Report::instance()->get_stats()['in_queue_pending_why'] ?? null;
		$this->assertIsArray( $before );

		$this->posts[] = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_name'         => 'stats-report-fixture',
				'post_title'        => 'Stats Report Fixture',
				'post_status'       => 'pending',
				// Plugin_Directory::filter_wp_insert_post_data() reads it, and fails the insert without it.
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			)
		);
		$wpdb->insert(
			"{$wpdb->base_prefix}helpscout",
			array(
				'id'      => self::CONVERSATION_ID,
				'status'  => 'closed',
				'subject' => '[WordPress Plugin Directory] Review in Progress: Stats Report Fixture',
			)
		);
		$wpdb->insert(
			"{$wpdb->base_prefix}helpscout_meta",
			array(
				'helpscout_id' => self::CONVERSATION_ID,
				'meta_key'     => 'plugins', // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
				'meta_value'   => 'stats-report-fixture', // phpcs:ignore WordPress.DB.SlowDBQuery -- Test fixture.
			)
		);

		$after = Stats_Report::instance()->get_stats()['in_queue_pending_why'];

		$this->assertSame( $before['author'] + 1, $after['author'] );
	}

	/**
	 * Adds a filter for the test.
	 *
	 * @param string  $hook     Hook.
	 * @param Closure $callback Callback.
	 * @return void
	 */
	protected function filter( string $hook, Closure $callback ): void {
		$this->filters[] = array( $hook, $callback );
		add_filter( $hook, $callback, 10, 3 );
	}
}
