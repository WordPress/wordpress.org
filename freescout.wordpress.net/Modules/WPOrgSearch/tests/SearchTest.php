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
 * Covers the default date and the search’s pages.
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
	 * Registers the module.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSearchServiceProvider::class );

		$this->admin   = $this->create_user();
		$this->mailbox = $this->create_mailbox();
	}

	/**
	 * Covers a search without other filters only going back a year.
	 *
	 * @return void
	 */
	public function test_search_without_filters_covers_the_past_year(): void {
		$expected = now()->modify( Search::DEFAULT_AFTER )->format( 'Y-m-d' );

		$this->assertSame( array( 'after' => $expected ), $this->filters( array(), array( 'q' => 'obenland' ) ) );
		$this->assertTrue( app( Search::class )->was_defaulted() );

		// Filters the agent switched on but left empty don’t count.
		$this->assertSame( $expected, $this->filters( array( 'following' => '' ), array( 'q' => 'obenland' ) )['after'] );

		// AJAX pages send the query along with the filters.
		$this->assertSame( $expected, $this->filters( array(), array( 'filter' => array( 'q' => 'obenland' ) ) )['after'] );

		$this->assertStringContainsString( 'need an After date', $this->filters_note() );
	}

	/**
	 * Covers a cleared date coming back, since searching everything is what times out.
	 *
	 * @return void
	 */
	public function test_cleared_date_is_set_again(): void {
		$expected = now()->modify( Search::DEFAULT_AFTER )->format( 'Y-m-d' );

		$this->assertSame( array( 'after' => $expected ), $this->filters( array( 'after' => '' ), array( 'q' => 'obenland' ) ) );
		$this->assertTrue( app( Search::class )->was_defaulted() );
	}

	/**
	 * Covers a date the agent picked, other filters, and browsing without a query staying as they are.
	 *
	 * @return void
	 */
	public function test_chosen_date_or_other_filters_are_kept(): void {
		$this->assertSame( array( 'after' => '2020-01-01' ), $this->filters( array( 'after' => '2020-01-01' ), array( 'q' => 'obenland' ) ) );
		$this->assertSame( array( 'status' => array( '1' ) ), $this->filters( array( 'status' => array( '1' ) ), array( 'q' => 'obenland' ) ) );
		$cleared = array(
			'mailbox' => '17',
			'after'   => '',
		);
		$this->assertSame( array( 'mailbox' => '17' ), $this->filters( $cleared, array( 'q' => 'obenland' ) ) );
		$this->assertSame( array(), $this->filters( array(), array( 'q' => '' ) ) );
		$this->assertFalse( app( Search::class )->was_defaulted() );
		$this->assertSame( '', $this->filters_note() );
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
	 * Covers a search that runs past the time limit coming back empty, with a note, instead of timing out the page.
	 *
	 * @return void
	 */
	public function test_search_that_takes_too_long_is_stopped(): void {
		$this->conversation( 'Plugin review', 'obenland', '2026-10-01 10:00:00' );
		$this->app->instance( Search::class, new Search( 0.1 ) );
		$limit = \DB::selectOne( 'SELECT @@max_statement_time AS `limit`' )->limit;

		$slow = static function ( $query ) {
			return $query->whereRaw( 'SLEEP(1) = 0' );
		};
		\Eventy::addFilter( 'search.conversations.apply_filters', $slow, 20, 1 );
		$results = $this->search( 'obenland' );
		\Eventy::removeFilter( 'search.conversations.apply_filters', $slow );

		$this->assertSame( 0, $results->total() );
		$this->assertTrue( app( Search::class )->timed_out() );
		$this->assertStringContainsString( 'took too long', $this->filters_note() );
		$this->assertSame( $limit, \DB::selectOne( 'SELECT @@max_statement_time AS `limit`' )->limit );
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
