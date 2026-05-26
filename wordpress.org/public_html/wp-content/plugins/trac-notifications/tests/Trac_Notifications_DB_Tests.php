<?php
/**
 * Unit tests for the read methods added to Trac_Notifications_DB.
 *
 * Uses an in-memory Fake_DB so tests run without MySQL or SQLite.
 *
 * @package WordPressdotorg\Trac
 */

use PHPUnit\Framework\TestCase;

/**
 * @group trac-notifications
 */
class Trac_Notifications_DB_Tests extends TestCase {

	/**
	 * @var Fake_DB
	 */
	protected $fake;

	/**
	 * @var Trac_Notifications_DB
	 */
	protected $dao;

	/**
	 * @return void
	 */
	public function setUp(): void {
		$this->fake = new Fake_DB();
		$this->dao  = new Trac_Notifications_DB( $this->fake );
	}

	/**
	 * @return void
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
	 * @return void
	 */
	public function test_comments_empty_result_returns_empty_array() {
		$this->fake->get_results_returns[] = array();
		$this->assertSame( array(), $this->dao->get_trac_ticket_comments( 42 ) );
	}

	/**
	 * @return void
	 */
	public function test_comments_unlimited_when_limit_zero() {
		$this->fake->get_results_returns[] = array();
		$this->dao->get_trac_ticket_comments( 42, 0 );
		$this->assertStringNotContainsString( 'LIMIT', $this->fake->get_results_calls[0] );
	}

	/**
	 * @return void
	 */
	public function test_comments_honours_offset() {
		$this->fake->get_results_returns[] = array();
		$this->dao->get_trac_ticket_comments( 42, 5, 10 );
		$this->assertStringContainsString( 'LIMIT 5 OFFSET 10', $this->fake->get_results_calls[0] );
	}

	/**
	 * @return void
	 */
	public function test_comments_passes_cast_ticket_id_to_prepare() {
		$this->fake->get_results_returns[] = array();
		$this->dao->get_trac_ticket_comments( '42abc', 5 );
		$this->assertSame( 42, $this->fake->prepare_calls[0]['args'][0] );
	}

	/**
	 * @return void
	 */
	public function test_comment_count_returns_int() {
		$this->fake->get_var_returns[] = '7';
		$this->assertSame( 7, $this->dao->get_trac_ticket_comment_count( 42 ) );
	}

	/**
	 * @return void
	 */
	public function test_comment_count_zero_when_null() {
		$this->fake->get_var_returns[] = null;
		$this->assertSame( 0, $this->dao->get_trac_ticket_comment_count( 42 ) );
	}

	/**
	 * @return void
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
	 * @return void
	 */
	public function test_changelog_empty() {
		$this->fake->get_results_returns[] = array();
		$this->assertSame( array(), $this->dao->get_trac_ticket_changelog( 42 ) );
	}

	/**
	 * @return void
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
	 * @return void
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
	 * @return void
	 */
	public function test_custom_fields_empty() {
		$this->fake->get_results_returns[] = array();
		$this->assertSame( array(), $this->dao->get_trac_ticket_custom_fields( 42 ) );
	}

	/**
	 * @return void
	 */
	public function test_full_returns_null_for_missing_ticket() {
		$this->fake->get_row_returns[] = null;

		$this->assertNull( $this->dao->get_trac_ticket_full( 999 ) );
		$this->assertCount( 1, $this->fake->get_row_calls, 'No follow-up queries when ticket is missing' );
	}

	/**
	 * @return void
	 */
	public function test_full_assembles_complete_payload() {
		$this->fake->get_row_returns[]     = array(
			'id'      => '42',
			'summary' => 'Test ticket',
			'status'  => 'new',
		);
		$this->fake->get_results_returns[] = array( // custom_fields
			array(
				'name'  => 'focuses',
				'value' => 'rest-api',
			),
		);
		$this->fake->get_col_returns[]     = array( 'alice', 'bob' ); // participants
		$this->fake->get_var_returns[]     = '1'; // comment count
		$this->fake->get_results_returns[] = array( // comments
			array(
				'id'     => '1',
				'time'   => '100',
				'author' => 'alice',
				'body'   => 'a comment',
			),
		);
		$this->fake->get_results_returns[] = array(); // changelog
		$this->fake->get_results_returns[] = array(); // attachments

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
	 * @return void
	 */
	public function test_full_can_skip_comments() {
		$this->fake->get_row_returns[]     = array( 'id' => '42' );
		$this->fake->get_results_returns[] = array(); // custom_fields
		$this->fake->get_col_returns[]     = array(); // participants
		$this->fake->get_results_returns[] = array(); // changelog
		$this->fake->get_results_returns[] = array(); // attachments

		$result = $this->dao->get_trac_ticket_full( 42, array( 'comments' => false ) );

		$this->assertArrayNotHasKey( 'comments', $result );
		$this->assertArrayNotHasKey( 'comments_total', $result );
		$this->assertArrayHasKey( 'changelog', $result );
	}

	/**
	 * @return void
	 */
	public function test_full_can_skip_changelog_and_attachments() {
		$this->fake->get_row_returns[]     = array( 'id' => '42' );
		$this->fake->get_results_returns[] = array(); // custom_fields
		$this->fake->get_col_returns[]     = array(); // participants
		$this->fake->get_var_returns[]     = '0'; // comment count
		$this->fake->get_results_returns[] = array(); // comments

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
	 * @return void
	 */
	public function test_full_returns_most_recent_n_comments_in_chronological_order() {
		$this->fake->get_row_returns[]     = array( 'id' => '42' );
		$this->fake->get_results_returns[] = array(); // custom_fields
		$this->fake->get_col_returns[]     = array(); // participants
		$this->fake->get_var_returns[]     = '100'; // total comments
		$this->fake->get_results_returns[] = array(); // comments
		$this->fake->get_results_returns[] = array(); // changelog
		$this->fake->get_results_returns[] = array(); // attachments

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
	 * @return void
	 */
	public function test_full_unlimited_comments_uses_zero_offset() {
		$this->fake->get_row_returns[]     = array( 'id' => '42' );
		$this->fake->get_results_returns[] = array(); // custom_fields
		$this->fake->get_col_returns[]     = array(); // participants
		$this->fake->get_var_returns[]     = '100'; // total comments
		$this->fake->get_results_returns[] = array(); // comments
		$this->fake->get_results_returns[] = array(); // changelog
		$this->fake->get_results_returns[] = array(); // attachments

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
}
