<?php
/**
 * Tests for the Trac sync job when Trac can't be reached.
 *
 * `Trac::ticket_query()` returns false once its retries are used up (for
 * example on a run of HTTP 504s). The sync used to cast that to an array,
 * giving `[ false ]`, so the release-to-live pass asked Trac for ticket
 * `False`. It had also already advanced its sync cursor, so ticket changes
 * made since the previous run were never imported.
 *
 * @package theme-directory
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Theme_Directory\Jobs\Trac_Sync;

/**
 * Tests that a failed Trac query is skipped and retried on the next run.
 *
 * @group trac-sync
 */
class Trac_Sync_Query_Failure_Test extends TestCase {

	/**
	 * The option holding the time of the last successful sync.
	 */
	const CURSOR_OPTION = 'wporg-themes-last-trac-sync';

	/**
	 * A Trac ticket number that no other fixture uses.
	 */
	const TICKET_ID = 987654;

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var array
	 */
	protected $post_ids = array();

	/**
	 * The sync cursor before the test, restored on teardown.
	 *
	 * @var mixed
	 */
	protected $saved_cursor;

	/**
	 * Saves the sync cursor, which the tests overwrite.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->saved_cursor = get_option( self::CURSOR_OPTION );
	}

	/**
	 * Restores the sync cursor and removes the posts created during the test.
	 */
	protected function tearDown(): void {
		if ( false === $this->saved_cursor ) {
			delete_option( self::CURSOR_OPTION );
		} else {
			update_option( self::CURSOR_OPTION, $this->saved_cursor );
		}

		/*
		 * The plugin prevents repopackages from being deleted; detach that
		 * specific guard while cleaning up the fixture posts.
		 */
		remove_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		add_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );

		$this->post_ids = array();

		parent::tearDown();
	}

	/**
	 * Builds a stand-in for `Trac` that records calls instead of making them.
	 *
	 * @param callable $respond Given the parsed `ticket.query` arguments, returns
	 *                          the ticket ids, or false for a failed query.
	 * @return object
	 */
	protected function create_trac_double( callable $respond ) {
		// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- The double mirrors Trac's signatures.
		return new class( $respond ) {

			/**
			 * Returns the response to each `ticket.query`.
			 *
			 * @var callable
			 */
			public $respond;

			/**
			 * The parsed arguments of every `ticket_query()` call.
			 *
			 * @var array
			 */
			public $queries = array();

			/**
			 * The `$id` argument of every `ticket_get()` call.
			 *
			 * @var array
			 */
			public $requested_ids = array();

			/**
			 * The `$id` argument of every `ticket_update()` call.
			 *
			 * @var array
			 */
			public $updated_ids = array();

			/**
			 * Constructor.
			 *
			 * @param callable $respond Returns the response to each `ticket.query`.
			 */
			public function __construct( callable $respond ) {
				$this->respond = $respond;
			}

			/**
			 * Records the query and returns the canned response.
			 *
			 * @param string $search Trac search query.
			 * @return bool|array
			 */
			public function ticket_query( $search ) {
				/*
				 * Split the way Trac does, on `&` and `=` with no URL decoding,
				 * so the `+` in a changetime's timezone offset survives.
				 */
				$args = array();
				foreach ( explode( '&', $search ) as $pair ) {
					list( $key, $value ) = array_pad( explode( '=', $pair, 2 ), 2, '' );
					$args[ $key ]        = $value;
				}
				$this->queries[] = $args;

				return ( $this->respond )( $args );
			}

			/**
			 * Records the ticket id it was called with.
			 *
			 * @param int $id Trac ticket id.
			 * @return bool
			 */
			public function ticket_get( $id ) {
				$this->requested_ids[] = $id;

				return false;
			}

			/**
			 * Records the ticket id it was called with.
			 *
			 * @param int    $id      Ticket number.
			 * @param string $comment Comment.
			 * @param array  $attr    Ticket attributes.
			 * @param bool   $notify  Whether to notify.
			 * @return bool
			 */
			public function ticket_update( $id, $comment, $attr = array(), $notify = false ) {
				$this->updated_ids[] = $id;

				return true;
			}
		};
		// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter
	}

	/**
	 * Whether a query is the status sync's query for approved tickets, rather
	 * than release_to_live()'s query, which also asks for `approved`.
	 *
	 * @param array $args Parsed `ticket.query` arguments.
	 * @return bool
	 */
	protected function is_approved_sync_query( array $args ): bool {
		return 'approved' === ( $args['status'] ?? '' ) && isset( $args['changetime'] );
	}

	/**
	 * Creates a theme whose version 1.0 has an open review ticket.
	 *
	 * @return int The repopackage post ID.
	 */
	protected function create_theme_in_review(): int {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'repopackage',
				'post_status' => 'draft',
				'post_title'  => 'Test Theme',
				'post_name'   => 'test-theme-' . uniqid(),
				'post_author' => 1,
			)
		);

		$this->post_ids[] = $post_id;

		// The meta the upload records for a new ticket.
		add_post_meta( $post_id, '_ticket_id', array( '1.0' => self::TICKET_ID ) );
		add_post_meta( $post_id, '_trac_ticket_10', self::TICKET_ID );
		add_post_meta( $post_id, '_status', array( '1.0' => 'new' ) );

		return $post_id;
	}

	/**
	 * When Trac can't be reached, nothing should be asked of it for the
	 * tickets the failed query would have returned.
	 */
	public function test_unreachable_trac_does_not_request_ticket_false() {
		$trac = $this->create_trac_double( '__return_false' );

		Trac_Sync::sync( $trac );

		$this->assertNotEmpty( $trac->queries );
		$this->assertSame( array(), $trac->requested_ids );
		$this->assertSame( array(), $trac->updated_ids );
	}

	/**
	 * A failed status query should leave the sync cursor where it was, so the
	 * next run queries the same window and imports what the failed run missed.
	 */
	public function test_failed_status_query_is_retried_on_next_run() {
		$last_sync = time() - HOUR_IN_SECONDS;
		update_option( self::CURSOR_OPTION, $last_sync );

		$post_id = $this->create_theme_in_review();

		// The ticket was approved on Trac, but Trac times out on that query.
		$trac = $this->create_trac_double(
			function ( $args ) {
				return $this->is_approved_sync_query( $args ) ? false : array();
			}
		);

		Trac_Sync::sync( $trac );

		$this->assertSame( array( '1.0' => 'new' ), get_post_meta( $post_id, '_status', true ) );
		$this->assertSame( $last_sync, (int) get_option( self::CURSOR_OPTION ) );

		// Trac answers on the next run.
		$trac = $this->create_trac_double(
			function ( $args ) {
				return $this->is_approved_sync_query( $args ) ? array( self::TICKET_ID ) : array();
			}
		);

		$before = time();
		Trac_Sync::sync( $trac );

		$approved_queries = array_values(
			array_filter(
				$trac->queries,
				function ( $args ) {
					return $this->is_approved_sync_query( $args );
				}
			)
		);

		$this->assertSame( gmdate( 'c', $last_sync - MINUTE_IN_SECONDS ), $approved_queries[0]['changetime'] );
		$this->assertSame( '0', $approved_queries[0]['max'], 'The whole window should be returned, not just its first page.' );
		$this->assertSame( array( '1.0' => 'approved' ), get_post_meta( $post_id, '_status', true ) );
		$this->assertGreaterThanOrEqual( $before, (int) get_option( self::CURSOR_OPTION ) );
	}

	/**
	 * A run where every query succeeds should move the sync cursor forward.
	 */
	public function test_successful_sync_advances_cursor() {
		update_option( self::CURSOR_OPTION, time() - HOUR_IN_SECONDS );

		$trac = $this->create_trac_double(
			function () {
				return array();
			}
		);

		$before = time();
		Trac_Sync::sync( $trac );

		$this->assertGreaterThanOrEqual( $before, (int) get_option( self::CURSOR_OPTION ) );
	}
}
