<?php
/**
 * Tests for conversation search.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSearch
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSearch\Tests;

use App\Conversation;
use App\Customer;
use App\Mailbox;
use App\Thread;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Modules\WPOrgSearch\Providers\WPOrgSearchServiceProvider;
use Modules\WPOrgSearch\Services\Search;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the default date, the search with and without the full-text index, and its pages.
 */
final class SearchTest extends TestCase {

	/**
	 * Mailbox searched.
	 *
	 * @var Mailbox
	 */
	private $mailbox;

	/**
	 * Agent searching.
	 *
	 * @var User
	 */
	private $admin;

	/**
	 * Registers the module, as if the database had no full-text index.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->add_index();
		$this->app->register( WPOrgSearchServiceProvider::class );
		$this->use_index( false );

		$this->admin   = $this->create_user();
		$this->mailbox = $this->create_mailbox();
	}

	/**
	 * Covers a search without filters only going back a year.
	 *
	 * @return void
	 */
	public function test_search_without_filters_covers_the_past_year(): void {
		$expected = now()->modify( Search::DEFAULT_AFTER )->format( 'Y-m-d' );

		$this->assertSame( array( 'after' => $expected ), $this->filters( array(), array( 'q' => 'obenland' ) ) );
		$this->assertTrue( app( Search::class )->was_defaulted() );

		// Filters the agent switched on but left empty don't count.
		$this->assertSame( $expected, $this->filters( array( 'following' => '' ), array( 'q' => 'obenland' ) )['after'] );

		// AJAX pages send the query along with the filters.
		$this->assertSame( $expected, $this->filters( array(), array( 'filter' => array( 'q' => 'obenland' ) ) )['after'] );

		$this->assertStringContainsString( 'clear the After date', $this->filters_note() );
	}

	/**
	 * Covers filters, a cleared date, and browsing without a query keeping the whole history.
	 *
	 * @return void
	 */
	public function test_filters_or_a_cleared_date_search_everything(): void {
		$this->assertSame( array( 'status' => array( '1' ) ), $this->filters( array( 'status' => array( '1' ) ), array( 'q' => 'obenland' ) ) );
		$this->assertSame( array( 'after' => '' ), $this->filters( array( 'after' => '' ), array( 'q' => 'obenland' ) ) );
		$this->assertSame( array(), $this->filters( array(), array( 'q' => '' ) ) );
		$this->assertFalse( app( Search::class )->was_defaulted() );
		$this->assertSame( '', $this->filters_note() );
	}

	/**
	 * Covers the index making the default date unnecessary, unless no word can be looked up in it.
	 *
	 * @return void
	 */
	public function test_index_searches_everything(): void {
		$this->use_index( true );

		$this->assertSame( array(), $this->filters( array(), array( 'q' => 'obenland' ) ) );
		$this->assertArrayHasKey( 'after', $this->filters( array(), array( 'q' => 'wp' ) ) );
	}

	/**
	 * Covers what the full-text query requires.
	 *
	 * @return void
	 */
	public function test_boolean_query(): void {
		$search = app( Search::class );

		$this->assertSame( '+obenland*', $search->boolean_query( 'Obenland' ) );
		$this->assertSame( '+jane* +example* +org*', $search->boolean_query( 'jane@example.org' ) );
		$this->assertSame( '+"how to install" +plugin*', $search->boolean_query( 'plugin "How to install" wp' ) );
		$this->assertSame( '+plugin* +theme*', $search->boolean_query( '+plugin* -(theme) "a b"' ) );
		$this->assertSame( '', $search->boolean_query( 'wp' ) );
	}

	/**
	 * Covers finding messages, newest reply first, with the number of results.
	 *
	 * @return void
	 */
	public function test_search_finds_messages(): void {
		$older = $this->conversation( 'Plugin review', 'Thanks, Konstantin Obenland', '2026-09-01 10:00:00' );
		$newer = $this->conversation( 'Theme review', 'cc obenland@example.org', '2026-10-01 10:00:00' );
		$this->conversation( 'Unrelated', 'Nothing to see', '2026-10-02 10:00:00' );

		$results = $this->search( 'obenland' );

		$this->assertSame( 2, $results->total() );
		$this->assertSame( array( $newer->id, $older->id ), $results->pluck( 'id' )->all() );
		$this->assertArrayNotHasKey( 'wporgsearch_total', $results->first()->getAttributes() );
	}

	/**
	 * Covers mailboxes the agent can't see staying out of the results.
	 *
	 * @return void
	 */
	public function test_search_respects_mailbox_access(): void {
		$visible = $this->conversation( 'Plugin review', 'obenland', '2026-10-01 10:00:00' );
		$this->conversation( 'Plugin review', 'obenland', '2026-10-01 10:00:00', $this->create_mailbox( 'Security' ) );

		$agent = $this->create_user( User::ROLE_USER );
		$agent->mailboxes()->attach( $this->mailbox->id );

		$this->assertSame( array( $visible->id ), $this->search( 'obenland', array(), $agent )->pluck( 'id' )->all() );
	}

	/**
	 * Covers later pages carrying the total of every page.
	 *
	 * @return void
	 */
	public function test_pages_carry_the_total(): void {
		for ( $i = 0; $i <= Conversation::DEFAULT_LIST_SIZE; $i++ ) {
			$this->conversation( 'Plugin review', 'obenland', '2026-10-01 10:00:00' );
		}

		Paginator::currentPageResolver(
			static function (): int {
				return 2;
			}
		);
		$results = $this->search( 'obenland' );

		$this->assertSame( Conversation::DEFAULT_LIST_SIZE + 1, $results->total() );
		$this->assertCount( 1, $results->items() );
	}

	/**
	 * Covers the search with the index: subjects, senders, and numbers match, and threads aren't joined.
	 *
	 * The index only holds committed rows, and every test runs in a transaction, so messages can't match here; the
	 * full-text query still has to run.
	 *
	 * @return void
	 */
	public function test_search_with_index(): void {
		$this->use_index( true );

		$subject  = $this->conversation( 'Obenland plugin review', 'Hello', '2026-09-01 10:00:00' );
		$numbered = $this->conversation( 'Theme review', 'Hello', '2026-09-02 10:00:00' );
		$this->conversation( 'Unrelated', 'Hello', '2026-09-03 10:00:00' );

		$sender             = $this->create_sender( 'konstantin@example.org' );
		$sender->first_name = 'Konstantin';
		$sender->last_name  = 'Obenland';
		$sender->save();
		$named = $this->conversation( 'Question', 'Hello', '2026-09-04 10:00:00', null, $sender );

		\DB::enableQueryLog();
		$results = $this->search( 'obenland' );
		$queries = implode( "\n", array_column( \DB::getQueryLog(), 'query' ) );

		$this->assertSame( array( $named->id, $subject->id ), $results->pluck( 'id' )->all() );
		$this->assertSame( 2, $results->total() );
		$this->assertStringContainsString( 'MATCH(', $queries );
		$this->assertStringNotContainsString( 'join `threads`', $queries );

		$this->assertSame( array( $named->id ), $this->search( 'Konstantin Obenland' )->pluck( 'id' )->all() );
		$this->assertSame( array( $numbered->id ), $this->search( (string) $numbered->number )->pluck( 'id' )->all() );
	}

	/**
	 * Covers filters that need threads keeping them joined, with the index.
	 *
	 * @return void
	 */
	public function test_search_with_index_keeps_filters(): void {
		$this->use_index( true );

		$match = $this->conversation( 'Obenland plugin review', 'Hello there', '2026-09-01 10:00:00' );
		$this->conversation( 'Obenland theme review', 'Goodbye', '2026-09-02 10:00:00' );

		$this->assertSame( array( $match->id ), $this->search( 'obenland', array( 'body' => 'hello' ) )->pluck( 'id' )->all() );
	}

	/**
	 * Covers core's search running when there's no agent to search as.
	 *
	 * @return void
	 */
	public function test_search_without_agent_is_left_to_core(): void {
		$this->assertSame( '', \Eventy::filter( 'search.conversations.perform', '', 'obenland', array(), null ) );
	}

	/**
	 * Says whether the database has the full-text index, as if it had been looked up.
	 *
	 * @param bool $index Whether it has.
	 * @return void
	 */
	private function use_index( bool $index ): void {
		\Cache::put(
			'wporgsearch_fulltext',
			array(
				'index'          => $index,
				'min_token_size' => 3,
			),
			60
		);
	}

	/**
	 * Adds the full-text index to the test database, unless it's there.
	 *
	 * Through its own connection, since adding it commits, which would end the test's transaction. Before the
	 * transaction reads anything: its snapshot would predate the index, and MariaDB refuses to search it then.
	 *
	 * @return void
	 */
	private function add_index(): void {
		config( array( 'database.connections.wporgsearch_ddl' => config( 'database.connections.' . config( 'database.default' ) ) ) );
		$connection = \DB::connection( 'wporgsearch_ddl' );

		$table  = $connection->getTablePrefix() . 'threads';
		$exists = $connection->selectOne(
			'SELECT COUNT(*) AS `count` FROM `information_schema`.`STATISTICS` WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `INDEX_NAME` = ?',
			array( $table, Search::INDEX )
		);

		if ( ! $exists->count ) {
			$connection->statement( 'ALTER TABLE `' . $table . '` ADD FULLTEXT `' . Search::INDEX . '` (`body`, `from`, `to`, `cc`, `bcc`)' );
		}

		\DB::disconnect( 'wporgsearch_ddl' );
	}

	/**
	 * Runs the search filters hook, as core does for a request.
	 *
	 * @param array $filters Filters from the request.
	 * @param array $input   Request input.
	 * @return array
	 */
	private function filters( array $filters, array $input ): array {
		return \Eventy::filter( 'search.filters', $filters, Conversation::SEARCH_MODE_CONV, Request::create( '/search', 'GET', $input ) );
	}

	/**
	 * What the module adds below the search filters.
	 *
	 * @return string
	 */
	private function filters_note(): string {
		ob_start();
		\Eventy::action( 'search.display_filters', array(), array(), Conversation::SEARCH_MODE_CONV );

		return (string) ob_get_clean();
	}

	/**
	 * Searches conversations, as core does through the module.
	 *
	 * @param string    $q       Search query.
	 * @param array     $filters Search filters.
	 * @param User|null $user    Agent searching; the administrator by default.
	 * @return LengthAwarePaginator
	 */
	private function search( string $q, array $filters = array(), ?User $user = null ): LengthAwarePaginator {
		$results = \Eventy::filter( 'search.conversations.perform', '', $q, $filters, $user ? $user : $this->admin );
		$this->assertInstanceOf( LengthAwarePaginator::class, $results );

		return $results;
	}

	/**
	 * Creates a conversation with one message from its sender.
	 *
	 * @param string        $subject    Subject.
	 * @param string        $body       Message.
	 * @param string        $replied_at Time of the last reply.
	 * @param Mailbox|null  $mailbox    Mailbox; the one searched by default.
	 * @param Customer|null $sender     Sender; a new one by default.
	 * @return Conversation
	 */
	private function conversation( string $subject, string $body, string $replied_at, ?Mailbox $mailbox = null, ?Customer $sender = null ): Conversation {
		$conversation = $this->create_conversation( $mailbox ? $mailbox : $this->mailbox, $sender ? $sender : $this->create_sender() );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, $body, null, $replied_at );

		// After the message, which updates them.
		$conversation->subject       = $subject;
		$conversation->last_reply_at = $replied_at;
		$conversation->save();

		return $conversation;
	}
}
