<?php
/**
 * Tests for a conversation's reviews and the index of review emails.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests;

use App\Conversation;
use App\Thread;
use App\User;
use Modules\WPOrgPluginReview\Jobs\IndexReviews;
use Modules\WPOrgPluginReview\Providers\WPOrgPluginReviewServiceProvider;
use Modules\WPOrgPluginReview\Services\FlagReplies;
use Modules\WPOrgPluginReview\Services\Review;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers working out the latest review, whether the author owes an update, and keeping the index as threads change.
 */
final class ReviewTest extends TestCase {

	/**
	 * Conversation under test.
	 *
	 * @var Conversation
	 */
	private $conversation;

	/**
	 * Reviewer.
	 *
	 * @var User
	 */
	private $reviewer;

	/**
	 * The agent WordPress.org posts upload confirmations as.
	 *
	 * @var User
	 */
	private $wporg;

	/**
	 * Registers the module and creates a review conversation.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgPluginReviewServiceProvider::class );

		$this->reviewer     = $this->create_user();
		$this->wporg        = $this->create_user();
		$this->conversation = $this->create_conversation( $this->create_mailbox( 'Plugins', 'plugins@wordpress.test' ), $this->create_sender() );
		$this->conversation->update( array( 'subject' => '[WordPress Plugin Directory] Review in Progress: My Plugin' ) );
	}

	/**
	 * Without a review email, there's no review, even if the sender quotes one.
	 *
	 * @return void
	 */
	public function test_conversation_without_a_review_email_has_none(): void {
		$this->reply( '<p>Hi, I fixed it.</p>' );
		$this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, '<p>Review ID: R my-plugin/jane 1Aug26/4.2</p>', null, '2026-08-02 10:00:00' );
		$this->create_thread( $this->conversation, Thread::TYPE_NOTE, '<p>Review ID: R my-plugin/jane 1Aug26/4.2</p>', $this->reviewer, '2026-08-02 11:00:00' );

		$this->assertNull( Review::latest( $this->conversation ) );
		$this->assertSame( 0, \DB::table( Review::TABLE )->count() );
	}

	/**
	 * The latest review email is the one shown, and the author owes an update until they upload one.
	 *
	 * @return void
	 */
	public function test_latest_review_waits_for_an_update_until_one_is_uploaded(): void {
		$this->reply( '<p>Review ID: F1 my-plugin/jane 1Aug26/4.2 (P0TDX42HGN)</p>', '2026-08-01 10:00:00' );
		$latest = $this->reply( '<h3>🔴 Use wp_enqueue commands</h3><p>Review ID: R ❗OWN my-plugin/jane/1Aug26/T2 8Aug26/4.3 (P0TDX42HGN)</p>', '2026-08-08 10:00:00' );
		$this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, '<p>Working on it.</p>', null, '2026-08-09 10:00:00' );

		$review = Review::latest( $this->conversation );

		$this->assertSame( (int) $latest->id, $review['thread_id'] );
		$this->assertSame( 'R', $review['review_id']['type'] );
		$this->assertSame( array( Review::FLAG_UPDATE, 'OWN' ), $review['flags'] );
		$this->assertSame( array( 'Enqueue' ), array_column( $review['issues'], 'name' ) );

		$this->confirm( '2026-08-10 10:00:00' );

		$this->assertSame( array( 'OWN' ), Review::latest( $this->conversation )['flags'] );
	}

	/**
	 * An upload confirmed since the latest review ends the wait, until the team writes to the author again; WordPress.org's
	 * own automated replies don't count as the team's, and a confirmation from before the latest review doesn't count.
	 *
	 * @return void
	 */
	public function test_an_upload_ends_the_wait_until_the_team_asks_again(): void {
		$this->reply( '<p>Review ID: R my-plugin/jane 1Aug26/4.2 (P0TDX42HGN)</p>', '2026-08-01 10:00:00' );
		$this->confirm( '2026-08-02 10:00:00' );
		$this->assertSame( array(), Review::latest( $this->conversation )['flags'] );

		$this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, '<p>Thanks, your email is in the queue.</p>', $this->wporg, '2026-08-03 10:00:00' );
		$this->assertSame( array(), Review::latest( $this->conversation )['flags'] );

		$this->reply( FlagReplies::REPLIES['TRM'], '2026-08-04 10:00:00' );
		$this->assertSame( array( Review::FLAG_UPDATE ), Review::latest( $this->conversation )['flags'] );

		$this->confirm( '2026-08-05 10:00:00' );
		$this->assertSame( array(), Review::latest( $this->conversation )['flags'] );

		$this->reply( '<p>Review ID: R my-plugin/jane/1Aug26/T2 6Aug26/4.3 (P0TDX42HGN)</p>', '2026-08-06 10:00:00' );
		$this->assertSame( array( Review::FLAG_UPDATE ), Review::latest( $this->conversation )['flags'] );
	}

	/**
	 * The emails following up on a review count for the wait, but the review they follow up on is the one shown; a
	 * sentence starting with "Review:" is neither.
	 *
	 * @return void
	 */
	public function test_follow_ups_dont_replace_the_review(): void {
		$review = $this->reply(
			'<h3>🔴 Use wp_enqueue commands</h3><ul><li>Our suggested alternative name: <code>Example Kit</code></li></ul>' .
			'<p>Review ID: R ❗TRM my-plugin/jane/1Aug26/T2 2Aug26/4.3 (P0TDX42HGN)</p>',
			'2026-08-02 10:00:00'
		);
		$this->confirm( '2026-08-03 10:00:00' );
		$this->reply( '<p>The changes weren’t made:</p><ul><li><strong>Use wp_enqueue commands</strong></li></ul><p>Review: CHANGESNOTMADE ❗TRM my-plugin/jane/1Aug26/T3 4Aug26/4.3 (P0TDX42HGN)</p>', '2026-08-04 10:00:00' );
		$this->reply( '<p>Review: I fixed the issues you mentioned.</p>', '2026-08-05 10:00:00' );

		$latest = Review::latest( $this->conversation );

		$this->assertSame( (int) $review->id, $latest['thread_id'] );
		$this->assertSame( 'R', $latest['review_id']['type'] );
		$this->assertSame( array( Review::FLAG_UPDATE, 'TRM' ), $latest['flags'] );
		$this->assertSame( array( 'Enqueue' ), array_column( $latest['issues'], 'name' ) );
		$this->assertSame( 'Example Kit', $latest['names']['suggested'] );
		$this->assertSame( array( 'CHANGESNOTMADE', 'R' ), \DB::table( Review::TABLE )->orderBy( 'type' )->pluck( 'type' )->all() );
	}

	/**
	 * Only WordPress.org confirms an upload: an author's email with its text doesn't.
	 *
	 * @return void
	 */
	public function test_an_authors_email_does_not_confirm_an_upload(): void {
		$this->reply( '<p>Review ID: R my-plugin/jane 1Aug26/4.2 (P0TDX42HGN)</p>', '2026-08-01 10:00:00' );
		$this->create_thread( $this->conversation, Thread::TYPE_CUSTOMER, '<p>' . Review::UPLOAD_CONFIRMATION . '</p>', null, '2026-08-02 10:00:00' );

		$this->assertSame( array( Review::FLAG_UPDATE ), Review::latest( $this->conversation )['flags'] );
	}

	/**
	 * Merging moves the other conversation's review emails into this one's index, as core saves the threads it moves.
	 *
	 * @return void
	 */
	public function test_merging_moves_the_review_emails(): void {
		$other  = $this->create_conversation( $this->conversation->mailbox, $this->create_sender() );
		$thread = $this->create_thread( $other, Thread::TYPE_MESSAGE, '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>', $this->reviewer, '2026-08-08 10:00:00' );
		$this->assertSame( (int) $other->id, (int) \DB::table( Review::TABLE )->where( 'thread_id', $thread->id )->value( 'conversation_id' ) );

		$this->conversation->mergeConversations( $other, $this->reviewer );

		$this->assertSame( array( (int) $this->conversation->id ), \DB::table( Review::TABLE )->pluck( 'conversation_id' )->map( 'intval' )->all() );
		$this->assertSame( (int) $thread->id, Review::latest( $this->conversation->fresh() )['thread_id'] );
	}

	/**
	 * Conversations deleted for good leave the index, however core deletes them.
	 *
	 * @return void
	 */
	public function test_deleting_conversations_for_good_forgets_their_reviews(): void {
		$other = $this->create_conversation( $this->conversation->mailbox, $this->create_sender() );
		$this->create_thread( $other, Thread::TYPE_MESSAGE, '<p>Review ID: R other-plugin/john 8Aug26/1.0</p>', $this->reviewer, '2026-08-08 10:00:00' );
		$this->reply( '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>' );

		Conversation::deleteConversationsForever( array( (int) $this->conversation->id ) );
		$this->assertSame( array( (int) $other->id ), \DB::table( Review::TABLE )->pluck( 'conversation_id' )->map( 'intval' )->all() );

		$other->delete();
		$this->assertSame( 0, \DB::table( Review::TABLE )->count() );
	}

	/**
	 * No update is waited for once the plugin is approved, or when no review asked for one.
	 *
	 * @return void
	 */
	public function test_no_update_is_waited_for_after_approval_or_without_a_request(): void {
		$this->reply( '<p>Review ID: AUTO my-plugin/jane 1Aug26/4.2</p>', '2026-08-01 10:00:00' );
		$this->assertSame( array(), Review::latest( $this->conversation )['flags'] );

		$this->reply( '<p>Review ID: R my-plugin/jane 2Aug26/4.2</p>', '2026-08-02 10:00:00' );
		$this->assertSame( array( Review::FLAG_UPDATE ), Review::latest( $this->conversation )['flags'] );

		$this->reply( '<p>Review ID: APPROVED my-plugin/jane 3Aug26/4.2</p>', '2026-08-03 10:00:00' );
		$this->assertSame( array(), Review::latest( $this->conversation )['flags'] );
	}

	/**
	 * A naming review shows the submitted name and the suggestions; an ownership review the declared domains.
	 *
	 * @return void
	 */
	public function test_reads_names_and_owner_for_their_flags(): void {
		$this->reply(
			'<ul><li>Author URI: https://www.example.co.uk/me</li><li>Plugin URI: <a href="https://sub.example.org/p">p</a></li>' .
			'<li>Suggested alternative name: <code>Example Kit</code></li><li>Suggested alternative slug: <code>example-kit</code></li></ul>' .
			'<p>Review ID: TRM ❗TRM-OWN my-plugin/jane 1Aug26/4.2</p>'
		);

		$review = Review::latest( $this->conversation );

		$this->assertSame(
			array(
				'original'       => 'My Plugin',
				'suggested'      => 'Example Kit',
				'suggested_slug' => 'example-kit',
			),
			$review['names']
		);
		$this->assertSame(
			array(
				'author_host' => 'example.co.uk',
				'plugin_host' => 'example.org',
				'username'    => 'jane',
			),
			$review['owner']
		);

		// Once the review is no longer in progress, the slug isn't offered.
		$this->conversation->update( array( 'subject' => '[WordPress Plugin Directory] Closure Notice - Trademarks: My Plugin' ) );
		$this->assertSame( '', Review::latest( $this->conversation )['names']['suggested_slug'] );
		$this->assertSame( 'My Plugin', Review::latest( $this->conversation )['names']['original'] );
	}

	/**
	 * Review emails are indexed as they're written, changed, and deleted.
	 *
	 * @return void
	 */
	public function test_indexes_review_emails_as_threads_change(): void {
		$thread = $this->reply( '<p>Review ID: R ❗TRM my-plugin/jane/T2 8Aug26/4.3 (P0TDX42HGN)</p>' );

		$row = \DB::table( Review::TABLE )->where( 'thread_id', $thread->id )->first();
		$this->assertSame( array( (int) $this->conversation->id, 'R' ), array( (int) $row->conversation_id, $row->type ) );

		$thread->body = '<p>No review here after all.</p>';
		$thread->save();
		$this->assertSame( 0, \DB::table( Review::TABLE )->count() );

		$thread->body = '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>';
		$thread->save();
		$this->assertSame( 1, \DB::table( Review::TABLE )->count() );

		$thread->delete();
		$this->assertSame( 0, \DB::table( Review::TABLE )->count() );
	}

	/**
	 * Saving a thread only reads it again when what makes it a review email changed, not when it's opened.
	 *
	 * @return void
	 */
	public function test_reindexes_threads_only_when_their_review_could_change(): void {
		$thread = $this->reply( '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>' );
		\DB::table( Review::TABLE )->where( 'thread_id', $thread->id )->update( array( 'type' => 'STALE' ) );

		$thread->opened_at = now();
		$thread->save();
		$this->assertSame( 'STALE', \DB::table( Review::TABLE )->value( 'type' ) );

		$thread->body = '<p>Review ID: TRM my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>';
		$thread->save();
		$this->assertSame( 'TRM', \DB::table( Review::TABLE )->value( 'type' ) );
	}

	/**
	 * The HelpScout import's conversations are indexed as they're imported, as their threads are written without hooks.
	 *
	 * @return void
	 */
	public function test_indexes_imported_conversations(): void {
		$this->reply( '<p>Review ID: R my-plugin/jane 1Aug26/4.2 (P0TDX42HGN)</p>', '2026-08-01 10:00:00' );
		$imported = $this->insert_thread( '<p>Review ID: TRM my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>' );

		\Eventy::action( 'wporghelpscoutimport.conversation_imported', $this->conversation, 1000000028 );

		$this->assertSame( $imported, Review::latest( $this->conversation )['thread_id'] );
	}

	/**
	 * A draft isn't a review email until it's published.
	 *
	 * @return void
	 */
	public function test_indexes_drafts_once_published(): void {
		$thread        = $this->reply( '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>' );
		$thread->state = Thread::STATE_DRAFT;
		$thread->save();
		$this->assertSame( 0, \DB::table( Review::TABLE )->count() );

		$thread->state = Thread::STATE_PUBLISHED;
		$thread->save();
		$this->assertSame( 1, \DB::table( Review::TABLE )->count() );
	}

	/**
	 * Showing a conversation's review brings its index up to date with threads saved without events, like imported ones,
	 * and drops rows of threads moved away.
	 *
	 * @return void
	 */
	public function test_showing_the_review_brings_the_index_up_to_date(): void {
		$imported = $this->insert_thread( '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>' );
		\DB::table( Review::TABLE )->insert(
			array(
				'thread_id'       => $imported + 1000,
				'conversation_id' => $this->conversation->id,
				'type'            => 'R',
			)
		);

		Review::latest( $this->conversation );

		$this->assertSame( array( $imported ), \DB::table( Review::TABLE )->pluck( 'thread_id' )->map( 'intval' )->all() );
	}

	/**
	 * Review emails newer than the latest indexed one, written without their hooks, are indexed as the review is shown.
	 *
	 * @return void
	 */
	public function test_showing_the_review_indexes_newer_review_emails(): void {
		$this->reply( '<p>Review ID: F1 my-plugin/jane 1Aug26/4.2 (P0TDX42HGN)</p>', '2026-08-01 10:00:00' );
		$newer = $this->insert_thread( '<p>Review ID: R my-plugin/jane/1Aug26/T2 8Aug26/4.3 (P0TDX42HGN)</p>', '2026-08-08 10:00:00' );

		$this->assertSame( $newer, Review::latest( $this->conversation )['thread_id'] );
		$this->assertSame( 2, \DB::table( Review::TABLE )->count() );
	}

	/**
	 * The review emails FreeScout already has in the plugins team's mailbox are indexed on the queue, in batches, and by
	 * the module's command.
	 *
	 * @return void
	 */
	public function test_indexes_existing_review_emails(): void {
		$review = $this->insert_thread( '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>' );
		$this->insert_thread( '<p>Thanks for the review.</p>' );
		$themes = $this->create_conversation( $this->create_mailbox( 'Themes', 'themes@wordpress.test' ), $this->create_sender() );
		$this->create_thread( $themes, Thread::TYPE_MESSAGE, '<p>Review ID: R my-theme/jane 8Aug26/1.0</p>', $this->reviewer, '2026-08-08 10:00:00' );

		( new IndexReviews() )->handle();

		$this->assertSame( array( $review ), \DB::table( Review::TABLE )->pluck( 'thread_id' )->map( 'intval' )->all() );

		\DB::table( Review::TABLE )->delete();
		\Artisan::call( 'wporgpluginreview:index', array( '--now' => true ) );

		$this->assertSame( array( $review ), \DB::table( Review::TABLE )->pluck( 'thread_id' )->map( 'intval' )->all() );
	}

	/**
	 * Other mailboxes have no reviews: their replies aren't indexed, and their conversations show none.
	 *
	 * @return void
	 */
	public function test_only_the_plugins_mailbox_has_reviews(): void {
		$conversation = $this->create_conversation( $this->create_mailbox( 'Themes', 'themes@wordpress.test' ), $this->create_sender() );
		$this->create_thread( $conversation, Thread::TYPE_MESSAGE, '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>', $this->reviewer, '2026-08-08 10:00:00' );

		$this->assertSame( 0, \DB::table( Review::TABLE )->count() );
		$this->assertNull( Review::latest( $conversation ) );
	}

	/**
	 * Moving a conversation out of the plugins mailbox takes its reviews out of the index, and moving it back puts them in.
	 *
	 * @return void
	 */
	public function test_moving_a_conversation_reindexes_it(): void {
		$this->reply( '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>' );
		$plugins = $this->conversation->mailbox;
		$themes  = $this->create_mailbox( 'Themes', 'themes@wordpress.test' );

		$this->conversation->moveToMailbox( $themes, $this->reviewer );

		$this->assertSame( 0, \DB::table( Review::TABLE )->count() );
		$this->assertNull( Review::latest( $this->conversation ) );

		$this->conversation->moveToMailbox( $plugins, $this->reviewer );

		$this->assertSame( 1, \DB::table( Review::TABLE )->where( 'conversation_id', $this->conversation->id )->count() );
	}

	/**
	 * Domains are cut down to the one the site is registered under.
	 *
	 * @return void
	 */
	public function test_cuts_urls_down_to_their_domain(): void {
		$this->assertSame( 'example.com', Review::domain( 'https://www.example.com/x' ) );
		$this->assertSame( 'example.com', Review::domain( 'https://a.b.example.com' ) );
		$this->assertSame( 'example.co.uk', Review::domain( 'https://shop.example.co.uk' ) );
		$this->assertSame( '192.0.2.1', Review::domain( 'http://192.0.2.1/' ) );
		$this->assertSame( '', Review::domain( 'not a url' ) );
	}

	/**
	 * Adds a reply from the reviewer.
	 *
	 * @param string $body       Body.
	 * @param string $created_at Creation time.
	 * @return Thread
	 */
	private function reply( string $body, string $created_at = '2026-08-01 10:00:00' ): Thread {
		return $this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, $body, $this->reviewer, $created_at );
	}

	/**
	 * Adds WordPress.org's confirmation of an uploaded update.
	 *
	 * @param string $created_at Creation time.
	 * @return Thread
	 */
	private function confirm( string $created_at ): Thread {
		return $this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, '<p>' . Review::UPLOAD_CONFIRMATION . '</p><p>File updated by jane, version 4.4.</p>', $this->wporg, $created_at );
	}

	/**
	 * Adds a reply without the events core fires, like the HelpScout import does.
	 *
	 * @param string $body       Body.
	 * @param string $created_at Creation time.
	 * @return int Thread ID.
	 */
	private function insert_thread( string $body, string $created_at = '2026-08-01 10:00:00' ): int {
		$thread = $this->reply( $body, $created_at );
		\DB::table( Review::TABLE )->where( 'thread_id', $thread->id )->delete();

		return (int) $thread->id;
	}
}
