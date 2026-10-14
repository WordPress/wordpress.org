<?php
/**
 * Tests for the worklists' Open buttons.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests;

use App\Conversation;
use App\Mailbox;
use App\Thread;
use App\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\WPOrgPluginReview\Providers\WPOrgPluginReviewServiceProvider;
use Modules\WPOrgPluginReview\Services\OpenOrder;
use Modules\WPOrgPluginReview\Services\Worklist;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers which conversations the buttons open and in what order, how many, and the profile field that sets it.
 */
final class OpenOrderTest extends TestCase {

	/**
	 * The plugins team's mailbox.
	 *
	 * @var Mailbox
	 */
	private $mailbox;

	/**
	 * The reviewer whose conversations are opened.
	 *
	 * @var User
	 */
	private $reviewer;

	/**
	 * Registers the module.
	 *
	 * Times are from now: FreeScout's Carbon can't stop the clock. The time a test takes only makes conversations wait
	 * a little longer, and none is that close to a wait.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgPluginReviewServiceProvider::class );
		$this->app['router']->getRoutes()->refreshNameLookups();

		$this->mailbox  = $this->create_mailbox( 'Plugins', 'plugins@wordpress.test' );
		$this->reviewer = $this->create_user();
	}

	/**
	 * Clears the modules' cache, which outlives the test's database.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		\App\Module::clearModulesCache();

		parent::tearDown();
	}

	/**
	 * Waits grow with the team's replies, as WAIT_DAYS has them, and with tags.
	 *
	 * @return void
	 */
	public function test_wait_days(): void {
		$expected = array(
			0  => 0,
			1  => 1,
			2  => 1,
			3  => 2,
			4  => 2,
			5  => 4,
			6  => 4,
			7  => 5,
			8  => 5,
			9  => 6,
			10 => 6,
			11 => 8,
			40 => 8,
		);
		foreach ( $expected as $replies => $days ) {
			$this->assertSame( $days, OpenOrder::wait_days( $replies, array() ), $replies . ' replies' );
		}

		$this->assertSame( 5, OpenOrder::wait_days( 1, array( 'Sockpuppeting', 'guidelines' ) ) );
		$this->assertSame( 4, OpenOrder::wait_days( 0, array( 'sockpuppet' ) ) );
	}

	/**
	 * Oldest first opens the active conversations that waited at least 12 hours, the longest waiting first, up to the
	 * limit.
	 *
	 * @return void
	 */
	public function test_oldest_first(): void {
		$day      = $this->conversation( 1, 24 );
		$fresh    = $this->conversation( 1, 11 );
		$half_day = $this->conversation( 1, 12 );
		$week     = $this->conversation( 9, 168 );
		$pending  = $this->conversation( 1, 300, Conversation::STATUS_PENDING );

		$all = array( $day, $fresh, $half_day, $week, $pending );

		$this->assertSame( $this->ids( $week, $day, $half_day ), OpenOrder::oldest_first( $all, 25 ) );
		$this->assertSame( $this->ids( $week, $day ), OpenOrder::oldest_first( $all, 2 ) );
		$this->assertNotContains( (int) $fresh->id, OpenOrder::oldest_first( $all, 25 ) );
	}

	/**
	 * By progress, each conversation waits as long as the team's replies make it, and those that waited longest beyond
	 * that go first; the first email counts when the team wrote it, and notes don't.
	 *
	 * @return void
	 */
	public function test_progress_based(): void {
		$recipe     = $this->conversation( 7, 216 );
		$badges     = $this->conversation( 5, 148 );
		$table      = $this->conversation( 2, 74 );
		$membership = $this->conversation( 11, 240 );
		$colors     = $this->conversation( 6, 72 );
		$gallery    = $this->conversation( 1, 38 );
		$contact    = $this->conversation( 1, 20 );
		$started    = $this->conversation( 0, 13, Conversation::STATUS_ACTIVE, true );
		$noted      = $this->conversation( 2, 30, Conversation::STATUS_ACTIVE, false, 9 );

		$all = array( $recipe, $badges, $table, $membership, $colors, $gallery, $contact, $started, $noted );

		// 96, 52, 50, 48, 14, 13, and 6 hours beyond their waits; the colors wait 4 days yet, and the contact form one.
		$this->assertSame( $this->ids( $recipe, $badges, $table, $membership, $gallery, $started, $noted ), OpenOrder::progress_based( $all, 25 ) );
		$this->assertSame( $this->ids( $recipe, $badges ), OpenOrder::progress_based( $all, 2 ) );
	}

	/**
	 * The team's first email counts as a reply when the team wrote it.
	 *
	 * @return void
	 */
	public function test_progress_based_counts_the_teams_first_email(): void {
		$started  = $this->conversation( 3, 60 );
		$answered = $this->conversation( 2, 59, Conversation::STATUS_ACTIVE, true );

		// Three replies wait 2 days, 12 hours beyond; two wait a day, 35 hours beyond.
		$this->assertSame( $this->ids( $answered, $started ), OpenOrder::progress_based( array( $started, $answered ), 25 ) );
	}

	/**
	 * Tags like sockpuppeting add to the wait, with the Tags module.
	 *
	 * @return void
	 */
	public function test_progress_based_waits_longer_for_tags(): void {
		$sockpuppets = $this->conversation( 1, 145 );
		$other       = $this->conversation( 1, 100 );

		$tag_id = (int) \DB::table( 'tags' )->insertGetId( array( 'name' => 'sockpuppeting' ) );
		\DB::table( 'conversation_tag' )->insert(
			array(
				'conversation_id' => $sockpuppets->id,
				'tag_id'          => $tag_id,
			)
		);

		$this->assertSame( $this->ids( $sockpuppets, $other ), OpenOrder::progress_based( array( $sockpuppets, $other ), 25 ) );

		\App\Module::clearModulesCache();
		\App\Module::setActive( 'tags', true );
		\App\Module::clearModulesCache();

		// 145 - 120 hours is 25 beyond, and 100 - 24 is 76.
		$this->assertSame( $this->ids( $other, $sockpuppets ), OpenOrder::progress_based( array( $sockpuppets, $other ), 25 ) );
	}

	/**
	 * How many open at a time is 25, unless the agent chose otherwise, from 1 to 100.
	 *
	 * @return void
	 */
	public function test_limit(): void {
		$this->assertSame( OpenOrder::DEFAULT_LIMIT, OpenOrder::limit( $this->reviewer ) );

		OpenOrder::set_limit( $this->reviewer, '40' );
		$this->assertSame( 40, OpenOrder::limit( $this->reviewer ) );

		OpenOrder::set_limit( $this->reviewer, '500' );
		$this->assertSame( OpenOrder::MAX_LIMIT, OpenOrder::limit( $this->reviewer ) );

		OpenOrder::set_limit( $this->reviewer, '0' );
		$this->assertSame( 1, OpenOrder::limit( $this->reviewer ) );

		OpenOrder::set_limit( $this->reviewer, '' );
		$this->assertSame( OpenOrder::DEFAULT_LIMIT, OpenOrder::limit( $this->reviewer ) );
		$this->assertFalse( \Option::get( 'wporgpluginreview.open_limit.' . $this->reviewer->id, false, true, false ) );
	}

	/**
	 * Reviewers' profiles have the field, which saves with the profile.
	 *
	 * @return void
	 */
	public function test_profile_field(): void {
		OpenOrder::set_limit( $this->reviewer, 30 );

		ob_start();
		\Eventy::action( 'user.edit.before_photo', $this->reviewer );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="wporgpluginreview_open_limit" value="30"', $html );

		$request = Request::create( '/', 'POST', array( 'wporgpluginreview_open_limit' => '12' ) );
		$this->assertSame( $this->reviewer, \Eventy::filter( 'user.save_profile', $this->reviewer, $request ) );
		$this->assertSame( 12, OpenOrder::limit( $this->reviewer ) );
	}

	/**
	 * The participating section has the buttons, with the conversations each opens, in order, and how many.
	 *
	 * @return void
	 */
	public function test_page_has_the_buttons(): void {
		$day  = $this->conversation( 1, 24 );
		$week = $this->conversation( 9, 168 );
		OpenOrder::set_limit( $this->reviewer, 10 );

		$html = $this->actingAs( $this->reviewer )->get( Worklist::reviews()->url( (int) $this->mailbox->id ) )->getContent();

		// The week-old one has to wait 6 days for its 9 replies, and the day-old one 1 for its one.
		$this->assertStringContainsString( 'data-conversations="' . $week->id . ',' . $day->id . '"', $html );
		$this->assertStringContainsString( 'Open Oldest First', $html );
		$this->assertStringContainsString( 'Open Progress Based', $html );
		$this->assertStringContainsString( 'Up to 10 at a time', $html );
	}

	/**
	 * Creates an active conversation the reviewer replied to, assigned to nobody, which without the Teams module is
	 * in their reviews: the team writes first, unless the sender did, and the sender answers every email.
	 *
	 * @param int  $replies      The team's replies.
	 * @param int  $hours        Hours since the sender's latest reply.
	 * @param int  $status       Status.
	 * @param bool $sender_first Whether the sender wrote first, so the team's first email is a reply to them.
	 * @param int  $notes        Notes the team added too, which don't count.
	 * @return Conversation
	 */
	private function conversation( int $replies, int $hours, int $status = Conversation::STATUS_ACTIVE, bool $sender_first = false, int $notes = 0 ): Conversation {
		$conversation = $this->create_conversation( $this->mailbox, $this->create_sender() );
		$at           = '2026-01-01 10:00:00';

		if ( $sender_first || ! $replies ) {
			$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, '<p>My plugin.</p>', null, $at );
		}
		for ( $n = 0; $n < $replies; $n++ ) {
			$this->create_thread( $conversation, Thread::TYPE_MESSAGE, '<p>Review.</p>', $this->reviewer, $at );
			$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, '<p>Updated.</p>', null, $at );
		}
		for ( $n = 0; $n < $notes; $n++ ) {
			$this->create_thread( $conversation, Thread::TYPE_NOTE, '<p>Note.</p>', $this->reviewer, $at );
		}

		$conversation->status        = $status;
		$conversation->last_reply_at = Carbon::now()->subHours( $hours );
		$conversation->save();

		return $conversation;
	}

	/**
	 * Conversations' IDs.
	 *
	 * @param Conversation ...$conversations Conversations.
	 * @return int[]
	 */
	private function ids( Conversation ...$conversations ): array {
		return array_map(
			static function ( Conversation $conversation ): int {
				return (int) $conversation->id;
			},
			$conversations
		);
	}
}
