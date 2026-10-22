<?php
/**
 * Tests for looking up a plugin's review conversations by the plugin IDs of their review emails.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests;

use App\Conversation;
use App\Mailbox;
use App\Thread;
use App\User;
use Illuminate\Http\Request;
use Modules\WPOrgPluginReview\Providers\WPOrgPluginReviewServiceProvider;
use Modules\WPOrgPluginReview\Services\PluginConversations;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the `reviewPluginId` parameter of the API's conversation list.
 *
 * The API & Webhooks module isn't in the test environment, so the filter runs on the query it would build.
 */
final class PluginConversationsTest extends TestCase {

	/**
	 * The plugins team's mailbox.
	 *
	 * @var Mailbox
	 */
	private $mailbox;

	/**
	 * Reviewer.
	 *
	 * @var User
	 */
	private $reviewer;

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgPluginReviewServiceProvider::class );
		$this->reviewer = $this->create_user();
		$this->mailbox  = $this->create_mailbox( 'Plugins', 'plugins@wordpress.test' );
	}

	/**
	 * Every conversation with a review email about the plugin is listed, open or closed, with the module's other filters.
	 *
	 * @return void
	 */
	public function test_lists_the_conversations_of_a_plugin(): void {
		$open   = $this->review( 42 )->conversation;
		$closed = $this->review( 42 )->conversation;
		$closed->update( array( 'status' => Conversation::STATUS_CLOSED ) );
		$this->review( 43 );
		$this->create_thread( $this->conversation(), Thread::TYPE_CUSTOMER, '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>', null, '2026-08-08 11:00:00' );

		$this->assertSame( array( $open->id, $closed->id ), $this->search( '42' ) );
		$this->assertSame( array( $open->id ), $this->search( '42', fn( $query ) => $query->where( 'status', Conversation::STATUS_ACTIVE ) ) );
		$this->assertSame( array(), $this->search( '41' ) );
		$this->assertCount( 4, $this->search( null ) );
	}

	/**
	 * A value that isn't a plugin ID is refused rather than ignored.
	 *
	 * @return void
	 */
	public function test_refuses_what_isnt_a_plugin_id(): void {
		foreach ( array( 'abc', '0' ) as $value ) {
			try {
				$this->search( $value );
				$this->fail( 'Not refused: ' . json_encode( $value ) );
			} catch ( HttpException $e ) {
				$this->assertSame( 400, $e->getStatusCode() );
			}
		}
	}

	/**
	 * A conversation in the plugins team's mailbox.
	 *
	 * @return Conversation
	 */
	private function conversation(): Conversation {
		return $this->create_conversation( $this->mailbox, $this->create_sender() );
	}

	/**
	 * A review email about a plugin, in a conversation of its own.
	 *
	 * @param int $plugin_id Plugin ID.
	 * @return Thread
	 */
	private function review( int $plugin_id ): Thread {
		return $this->create_thread( $this->conversation(), Thread::TYPE_MESSAGE, '<p>Review ID: R my-plugin/jane/8Aug26/T1 8Aug26/4.3 (P0TDX' . $plugin_id . 'HGN)</p>', $this->reviewer, '2026-08-08 10:00:00' );
	}

	/**
	 * Runs the API & Webhooks module's filter on the conversation list query.
	 *
	 * @param mixed         $plugin_id Value of the parameter; null leaves it out.
	 * @param callable|null $more      Adds the module's other parameters to the query.
	 * @return int[] IDs of the conversations listed.
	 */
	private function search( mixed $plugin_id, ?callable $more = null ): array {
		$query = $this->base_query();
		if ( $more ) {
			$more( $query );
		}
		$parameters = null === $plugin_id ? array() : array( PluginConversations::PARAMETER => $plugin_id );
		$query      = \Eventy::filter( 'api.conversations.query', $query, Request::create( '/api/conversations', 'GET', $parameters ) );

		return array_map( 'intval', $query->pluck( 'conversations.id' )->all() );
	}

	/**
	 * The query the module starts from.
	 *
	 * @return mixed
	 */
	private function base_query(): mixed {
		return Conversation::select( array( 'conversations.*' ) )->where( 'mailbox_id', $this->mailbox->id )->orderBy( 'conversations.id', 'asc' );
	}
}
