<?php
/**
 * Unit tests for the read methods added to Trac_Notifications_DB.
 *
 * Uses an in-memory Fake_DB so tests run without MySQL or SQLite.
 *
 * @package WordPressdotorg\Trac
 */

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Trac_Notifications_DB behaviour tests for the new ticket-read methods.
 */
#[Group( 'trac-notifications' )]
class Trac_Notifications_DB_Tests extends TestCase {

	/**
	 * Scriptable DB driver injected into Trac_Notifications_DB.
	 *
	 * @var Fake_DB
	 */
	protected $fake;

	/**
	 * System under test.
	 *
	 * @var Trac_Notifications_DB
	 */
	protected $dao;

	/**
	 * Build a fresh fake and DAO per test.
	 */
	public function setUp(): void {
		$this->fake = new Fake_DB();
		$this->dao  = new Trac_Notifications_DB( $this->fake );
	}

	/**
	 * Comments are returned in chronological order with the expected SQL shape.
	 */
	public function test_comments_returns_rows_in_chronological_order() {
		$this->fake->get_results_returns[] = array(
			array(
				'id'     => '1',
				'time'   => '100',
				'author' => 'alice',
				'body'   => 'first',
			),
			array(
				'id'     => '2',
				'time'   => '200',
				'author' => 'bob',
				'body'   => 'second',
			),
		);

		$result = $this->dao->get_trac_ticket_comments( 42, 10 );

		$this->assertCount( 2, $result );
		$this->assertSame( 'alice', $result[0]['author'] );
		$this->assertSame( 'second', $result[1]['body'] );
		$this->assertStringContainsString( 'ORDER BY time ASC', $this->fake->get_results_calls[0] );
		$this->assertStringContainsString( 'LIMIT 10', $this->fake->get_results_calls[0] );
		$this->assertStringContainsString( "field = 'comment'", $this->fake->get_results_calls[0] );
		$this->assertStringContainsString( "newvalue <> ''", $this->fake->get_results_calls[0] );
	}

	/**
	 * No rows yields an empty array, not null.
	 */
	public function test_comments_empty_result_returns_empty_array() {
		$this->fake->get_results_returns[] = array();
		$this->assertSame( array(), $this->dao->get_trac_ticket_comments( 42 ) );
	}

	/**
	 * A zero limit omits the LIMIT clause (return all rows).
	 */
	public function test_comments_unlimited_when_limit_zero() {
		$this->fake->get_results_returns[] = array();
		$this->dao->get_trac_ticket_comments( 42, 0 );
		$this->assertStringNotContainsString( 'LIMIT', $this->fake->get_results_calls[0] );
	}

	/**
	 * Offset is passed through to the SQL.
	 */
	public function test_comments_honours_offset() {
		$this->fake->get_results_returns[] = array();
		$this->dao->get_trac_ticket_comments( 42, 5, 10 );
		$this->assertStringContainsString( 'LIMIT 5 OFFSET 10', $this->fake->get_results_calls[0] );
	}

	/**
	 * Non-integer ticket ids are coerced to int before being prepared.
	 */
	public function test_comments_passes_cast_ticket_id_to_prepare() {
		$this->fake->get_results_returns[] = array();
		$this->dao->get_trac_ticket_comments( '42abc', 5 );
		$this->assertSame( 42, $this->fake->prepare_calls[0]['args'][0] );
	}

	/**
	 * Comment count is coerced to an int.
	 */
	public function test_comment_count_returns_int() {
		$this->fake->get_var_returns[] = '7';
		$this->assertSame( 7, $this->dao->get_trac_ticket_comment_count( 42 ) );
	}

	/**
	 * Null from the DB collapses to zero (not null/false).
	 */
	public function test_comment_count_zero_when_null() {
		$this->fake->get_var_returns[] = null;
		$this->assertSame( 0, $this->dao->get_trac_ticket_comment_count( 42 ) );
	}

	/**
	 * Changelog returns every non-comment, non-cc field transition.
	 */
	public function test_changelog_returns_non_comment_changes() {
		$this->fake->get_results_returns[] = array(
			array(
				'time'     => '100',
				'author'   => 'alice',
				'field'    => 'status',
				'oldvalue' => 'new',
				'newvalue' => 'assigned',
			),
			array(
				'time'     => '200',
				'author'   => 'bob',
				'field'    => 'keywords',
				'oldvalue' => '',
				'newvalue' => 'has-patch',
			),
		);

		$result = $this->dao->get_trac_ticket_changelog( 42 );

		$this->assertCount( 2, $result );
		$this->assertSame( 'status', $result[0]['field'] );
		$this->assertStringContainsString( "field <> 'comment'", $this->fake->get_results_calls[0] );
		$this->assertStringContainsString( "field <> 'cc'", $this->fake->get_results_calls[0] );
	}

	/**
	 * Changelog with no rows yields an empty array.
	 */
	public function test_changelog_empty() {
		$this->fake->get_results_returns[] = array();
		$this->assertSame( array(), $this->dao->get_trac_ticket_changelog( 42 ) );
	}

	/**
	 * Attachments query reads the attachment table filtered by type=ticket.
	 */
	public function test_attachments_returns_rows() {
		$this->fake->get_results_returns[] = array(
			array(
				'filename'    => 'patch.diff',
				'size'        => '1024',
				'time'        => '100',
				'description' => '',
				'author'      => 'alice',
			),
		);

		$result = $this->dao->get_trac_ticket_attachments( 42 );

		$this->assertCount( 1, $result );
		$this->assertSame( 'patch.diff', $result[0]['filename'] );
		$this->assertStringContainsString( "type = 'ticket'", $this->fake->get_results_calls[0] );
	}

	/**
	 * Custom fields are returned as a name => value map.
	 */
	public function test_custom_fields_returns_name_value_map() {
		$this->fake->get_results_returns[] = array(
			array(
				'name'  => 'focuses',
				'value' => 'rest-api, performance',
			),
			array(
				'name'  => 'keywords',
				'value' => 'has-patch',
			),
		);

		$result = $this->dao->get_trac_ticket_custom_fields( 42 );

		$this->assertSame(
			array(
				'focuses'  => 'rest-api, performance',
				'keywords' => 'has-patch',
			),
			$result
		);
	}

	/**
	 * No custom fields yields an empty map (not null/false).
	 */
	public function test_custom_fields_empty() {
		$this->fake->get_results_returns[] = array();
		$this->assertSame( array(), $this->dao->get_trac_ticket_custom_fields( 42 ) );
	}

	/**
	 * Missing ticket short-circuits to null with no follow-up queries.
	 */
	public function test_full_returns_null_for_missing_ticket() {
		$this->fake->get_row_returns[] = null;

		$this->assertNull( $this->dao->get_trac_ticket_full( 999 ) );
		$this->assertCount( 1, $this->fake->get_row_calls, 'No follow-up queries when ticket is missing' );
	}

	/**
	 * Composite payload includes ticket fields, custom fields, participants, comments + count, changelog, attachments.
	 */
	public function test_full_assembles_complete_payload() {
		$this->fake->get_row_returns[]     = array(
			'id'      => '42',
			'summary' => 'Test ticket',
			'status'  => 'new',
		);
		$this->fake->get_results_returns[] = array( // custom_fields.
			array(
				'name'  => 'focuses',
				'value' => 'rest-api',
			),
		);
		$this->fake->get_col_returns[]     = array( 'alice', 'bob' ); // participants.
		$this->fake->get_var_returns[]     = '1'; // comment count.
		$this->fake->get_results_returns[] = array( // comments.
			array(
				'id'     => '1',
				'time'   => '100',
				'author' => 'alice',
				'body'   => 'a comment',
			),
		);
		$this->fake->get_results_returns[] = array(); // changelog.
		$this->fake->get_results_returns[] = array(); // attachments.

		$result = $this->dao->get_trac_ticket_full( 42 );

		$this->assertSame( '42', $result['id'] );
		$this->assertSame( 'Test ticket', $result['summary'] );
		$this->assertSame( array( 'focuses' => 'rest-api' ), $result['custom_fields'] );
		$this->assertSame( array( 'alice', 'bob' ), $result['participants'] );
		$this->assertCount( 1, $result['comments'] );
		$this->assertSame( 1, $result['comments_total'] );
		$this->assertSame( array(), $result['changelog'] );
		$this->assertSame( array(), $result['attachments'] );
	}

	/**
	 * Passing comments=false drops the comments and comments_total keys.
	 */
	public function test_full_can_skip_comments() {
		$this->fake->get_row_returns[]     = array( 'id' => '42' );
		$this->fake->get_results_returns[] = array(); // custom_fields.
		$this->fake->get_col_returns[]     = array(); // participants.
		$this->fake->get_results_returns[] = array(); // changelog.
		$this->fake->get_results_returns[] = array(); // attachments.

		$result = $this->dao->get_trac_ticket_full( 42, array( 'comments' => false ) );

		$this->assertArrayNotHasKey( 'comments', $result );
		$this->assertArrayNotHasKey( 'comments_total', $result );
		$this->assertArrayHasKey( 'changelog', $result );
	}

	/**
	 * Passing changelog=false and attachments=false drops their respective keys.
	 */
	public function test_full_can_skip_changelog_and_attachments() {
		$this->fake->get_row_returns[]     = array( 'id' => '42' );
		$this->fake->get_results_returns[] = array(); // custom_fields.
		$this->fake->get_col_returns[]     = array(); // participants.
		$this->fake->get_var_returns[]     = '0'; // comment count.
		$this->fake->get_results_returns[] = array(); // comments.

		$result = $this->dao->get_trac_ticket_full(
			42,
			array(
				'changelog'   => false,
				'attachments' => false,
			)
		);

		$this->assertArrayHasKey( 'comments', $result );
		$this->assertArrayNotHasKey( 'changelog', $result );
		$this->assertArrayNotHasKey( 'attachments', $result );
	}

	/**
	 * The composite computes offset = total - limit so the trailing window of comments
	 * is returned in ASC display order.
	 */
	public function test_full_returns_most_recent_n_comments_in_chronological_order() {
		$this->fake->get_row_returns[]     = array( 'id' => '42' );
		$this->fake->get_results_returns[] = array(); // custom_fields.
		$this->fake->get_col_returns[]     = array(); // participants.
		$this->fake->get_var_returns[]     = '100'; // total comments.
		$this->fake->get_results_returns[] = array(); // comments.
		$this->fake->get_results_returns[] = array(); // changelog.
		$this->fake->get_results_returns[] = array(); // attachments.

		$this->dao->get_trac_ticket_full( 42, array( 'comments' => 5 ) );

		$comments_query = null;
		foreach ( $this->fake->get_results_calls as $query ) {
			if ( str_contains( $query, "field = 'comment'" ) ) {
				$comments_query = $query;
				break;
			}
		}
		$this->assertNotNull( $comments_query );
		$this->assertStringContainsString( 'LIMIT 5 OFFSET 95', $comments_query );
	}

	/**
	 * Passing comments=0 (unlimited) uses offset 0, so no LIMIT clause is appended.
	 */
	public function test_full_unlimited_comments_uses_zero_offset() {
		$this->fake->get_row_returns[]     = array( 'id' => '42' );
		$this->fake->get_results_returns[] = array(); // custom_fields.
		$this->fake->get_col_returns[]     = array(); // participants.
		$this->fake->get_var_returns[]     = '100'; // total comments.
		$this->fake->get_results_returns[] = array(); // comments.
		$this->fake->get_results_returns[] = array(); // changelog.
		$this->fake->get_results_returns[] = array(); // attachments.

		$this->dao->get_trac_ticket_full( 42, array( 'comments' => 0 ) );

		$comments_query = null;
		foreach ( $this->fake->get_results_calls as $query ) {
			if ( str_contains( $query, "field = 'comment'" ) ) {
				$comments_query = $query;
				break;
			}
		}
		$this->assertNotNull( $comments_query );
		$this->assertStringNotContainsString( 'LIMIT', $comments_query );
	}

	/**
	 * Focuses accessor returns the focuses value when present in the custom fields.
	 */
	public function test_focuses_returns_value_via_custom_fields() {
		$this->fake->get_results_returns[] = array(
			array(
				'name'  => 'focuses',
				'value' => 'rest-api, performance',
			),
		);
		$this->assertSame( 'rest-api, performance', $this->dao->get_trac_ticket_focuses( 42 ) );
	}

	/**
	 * Focuses accessor returns null when the ticket has no focuses custom field.
	 */
	public function test_focuses_returns_null_when_absent() {
		$this->fake->get_results_returns[] = array();
		$this->assertNull( $this->dao->get_trac_ticket_focuses( 42 ) );
	}

	/**
	 * Search returns the canonical column set ordered by changetime DESC.
	 */
	public function test_search_returns_rows_with_default_order() {
		$this->fake->get_results_returns[] = array(
			array(
				'id'         => '42',
				'summary'    => 'Recent',
				'status'     => 'new',
				'type'       => 'defect',
				'component'  => 'REST API',
				'priority'   => 'normal',
				'changetime' => '200',
			),
		);

		$result = $this->dao->search_trac_tickets();

		$this->assertCount( 1, $result );
		$this->assertSame( '42', $result[0]['id'] );
		$this->assertStringContainsString( 'ORDER BY t.changetime DESC', $this->fake->get_results_calls[0] );
	}

	/**
	 * Search excludes closed tickets unless asked otherwise.
	 */
	public function test_search_excludes_closed_by_default() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets();
		$this->assertStringContainsString( "t.status <> 'closed'", $this->fake->get_results_calls[0] );
	}

	/**
	 * Supplying an explicit status disables the default closed-exclusion clause.
	 */
	public function test_search_includes_closed_when_status_supplied() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets( array( 'status' => 'closed' ) );
		$this->assertStringNotContainsString( "t.status <> 'closed'", $this->fake->get_results_calls[0] );
		$this->assertStringContainsString( 't.status = %s', $this->fake->get_results_calls[0] );
	}

	/**
	 * The include_closed flag also disables the default closed-exclusion clause.
	 */
	public function test_search_includes_closed_with_flag() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets( array( 'include_closed' => true ) );
		$this->assertStringNotContainsString( "t.status <> 'closed'", $this->fake->get_results_calls[0] );
	}

	/**
	 * Unknown filter keys are silently dropped (allowlist enforcement).
	 */
	public function test_search_unknown_filter_keys_ignored() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets(
			array(
				'type'             => 'defect',
				'malicious_column' => 'value',
				'DROP TABLE x;'    => '1',
			)
		);

		$prepared_args = $this->fake->prepare_calls[0]['args'][0];
		$this->assertSame( array( 'defect' ), $prepared_args, 'only the allowlisted "type" value is bound' );
		$this->assertStringNotContainsString( 'malicious_column', $this->fake->get_results_calls[0] );
	}

	/**
	 * Type + component filter combines into the WHERE clause and binds both values.
	 */
	public function test_search_combines_filters() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets(
			array(
				'type'      => 'defect',
				'component' => 'REST API',
			)
		);

		$this->assertStringContainsString( 't.type = %s', $this->fake->get_results_calls[0] );
		$this->assertStringContainsString( 't.component = %s', $this->fake->get_results_calls[0] );
		$this->assertSame(
			array( 'defect', 'REST API' ),
			$this->fake->prepare_calls[0]['args'][0]
		);
	}

	/**
	 * The focuses filter adds a ticket_custom join and a LIKE comparison.
	 */
	public function test_search_focuses_filter_adds_join() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets(
			array(
				'component' => 'Media',
				'focuses'   => 'rest-api',
			)
		);

		$query = $this->fake->get_results_calls[0];
		$this->assertStringContainsString( "LEFT JOIN ticket_custom cf ON cf.ticket = t.id AND cf.name = 'focuses'", $query );
		$this->assertStringContainsString( 'cf.value LIKE %s', $query );
		$this->assertSame(
			array( 'Media', '%rest-api%' ),
			$this->fake->prepare_calls[0]['args'][0]
		);
	}

	/**
	 * The keywords filter adds a ticket_custom join with its own alias.
	 */
	public function test_search_keywords_filter_adds_join() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets(
			array(
				'component' => 'Media',
				'keywords'  => 'has-patch',
			)
		);

		$query = $this->fake->get_results_calls[0];
		$this->assertStringContainsString( "LEFT JOIN ticket_custom ck ON ck.ticket = t.id AND ck.name = 'keywords'", $query );
		$this->assertStringContainsString( 'ck.value LIKE %s', $query );
	}

	/**
	 * changed_since accepts a strtotime string and converts to Trac's microsecond unit.
	 */
	public function test_search_changed_since_converts_to_microseconds() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets( array( 'changed_since' => '2022-01-01' ) );

		$bound = $this->fake->prepare_calls[0]['args'][0][0];
		$this->assertSame( strtotime( '2022-01-01' ) * 1000000, $bound );
	}

	/**
	 * Limit is clamped to [1, 50].
	 */
	public function test_search_limit_capped_at_50() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets( array(), 999 );
		$this->assertStringContainsString( 'LIMIT 50', $this->fake->get_results_calls[0] );

		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets( array(), 0 );
		$this->assertStringContainsString( 'LIMIT 1', $this->fake->get_results_calls[1] );
	}

	/**
	 * Offset is passed through and clamped at zero.
	 */
	public function test_search_offset_passed_through() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets( array(), 25, 75 );
		$this->assertStringContainsString( 'OFFSET 75', $this->fake->get_results_calls[0] );
	}

	/**
	 * With no filters, prepare() is not called because the SQL has no placeholders to bind.
	 */
	public function test_search_no_filters_skips_prepare() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets();
		$this->assertCount( 0, $this->fake->prepare_calls );
	}

	/**
	 * Unscoped focuses LIKE is refused (returns empty) without touching the DB.
	 */
	public function test_search_refuses_unscoped_focuses() {
		$result = $this->dao->search_trac_tickets( array( 'focuses' => 'performance' ) );

		$this->assertSame( array(), $result );
		$this->assertCount( 0, $this->fake->get_results_calls, 'no SQL executed' );
		$this->assertCount( 0, $this->fake->prepare_calls );
	}

	/**
	 * Unscoped keywords LIKE is also refused.
	 */
	public function test_search_refuses_unscoped_keywords() {
		$result = $this->dao->search_trac_tickets( array( 'keywords' => 'has-patch' ) );

		$this->assertSame( array(), $result );
		$this->assertCount( 0, $this->fake->get_results_calls );
	}

	/**
	 * A LIKE filter scoped by an equality filter is allowed through.
	 */
	public function test_search_allows_focuses_when_scoped_by_equality_filter() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets(
			array(
				'component' => 'Media',
				'focuses'   => 'performance',
			)
		);

		$this->assertCount( 1, $this->fake->get_results_calls );
		$this->assertStringContainsString( 'cf.value LIKE %s', $this->fake->get_results_calls[0] );
	}

	/**
	 * A LIKE filter scoped by changed_since is allowed through.
	 */
	public function test_search_allows_focuses_when_scoped_by_changed_since() {
		$this->fake->get_results_returns[] = array();
		$this->dao->search_trac_tickets(
			array(
				'changed_since' => '2024-01-01',
				'focuses'       => 'performance',
			)
		);

		$this->assertCount( 1, $this->fake->get_results_calls );
		$this->assertStringContainsString( 'cf.value LIKE %s', $this->fake->get_results_calls[0] );
	}
}
