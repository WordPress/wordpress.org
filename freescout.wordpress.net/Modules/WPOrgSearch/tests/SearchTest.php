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
	 * Covers which searches get the default After date, and the note saying so.
	 *
	 * @dataProvider data_default_date
	 *
	 * @param array $filters   Filters from the request.
	 * @param array $input     Request input.
	 * @param array $expected  Filters the search runs with, before the default date.
	 * @param bool  $defaulted Whether the default date is added.
	 * @return void
	 */
	public function test_default_date( array $filters, array $input, array $expected, bool $defaulted ): void {
		if ( $defaulted ) {
			$expected['after'] = now()->modify( Search::DEFAULT_AFTER )->format( 'Y-m-d' );
		}

		$this->assertSame( $expected, $this->filters( $filters, $input ) );
		$this->assertSame( $defaulted, str_contains( $this->filters_note(), 'started in the past year' ) );
	}

	/**
	 * Requests and the filters their search runs with.
	 *
	 * @return array[]
	 */
	public function data_default_date(): array {
		return array(
			'query only'                 => array( array(), array( 'q' => 'obenland' ), array(), true ),
			'removed or cleared date'    => array(
				array(
					'following' => '',
					'after'     => '',
				),
				array( 'q' => 'obenland' ),
				array( 'following' => '' ),
				true,
			),
			'AJAX page'                  => array( array(), array( 'filter' => array( 'q' => 'obenland' ) ), array(), true ),
			'date the agent picked'      => array( array( 'after' => '2020-01-01' ), array( 'q' => 'obenland' ), array( 'after' => '2020-01-01' ), false ),
			'another filter'             => array( array( 'status' => array( '1' ) ), array( 'q' => 'obenland' ), array( 'status' => array( '1' ) ), false ),
			'browsing filters, no query' => array( array(), array( 'q' => '' ), array(), false ),
		);
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
	 * Covers conversations the agent can't see staying out of the results: other mailboxes, and for agents who may
	 * only see their own, conversations assigned to someone else.
	 *
	 * @return void
	 */
	public function test_search_respects_access(): void {
		$visible = $this->conversation( 'Plugin review', 'obenland', '2026-10-01 10:00:00' );
		$this->conversation( 'Plugin review', 'obenland', '2026-10-01 10:00:00', $this->create_mailbox( 'Security' ) );

		$agent = $this->create_user( User::ROLE_USER );
		$agent->mailboxes()->attach( $this->mailbox->id );

		$this->assertSame( array( $visible->id ), $this->search( 'obenland', array(), $agent )->pluck( 'id' )->all() );

		$visible->user_id = $agent->id;
		$visible->save();
		$this->conversation( 'Theme review', 'obenland', '2026-10-02 10:00:00' );
		$agent->permissions = array( User::PERM_ONLY_ASSIGNED_TICKETS => true );
		$agent->save();

		$this->assertSame( array( $visible->id ), $this->search( 'obenland', array(), $agent->fresh() )->pluck( 'id' )->all() );
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

		$slow = static function ( $query ) {
			return $query->whereRaw( 'SLEEP(1) = 0' );
		};
		\Eventy::addFilter( 'search.conversations.apply_filters', $slow, 20, 1 );
		$results = $this->search( 'obenland' );
		\Eventy::removeFilter( 'search.conversations.apply_filters', $slow );

		$this->assertSame( 0, $results->total() );
		$this->assertTrue( app( Search::class )->timed_out() );
		$this->assertStringContainsString( 'took too long', $this->filters_note() );
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
