<?php
/**
 * Tests for pinning a conversation's latest note to its top.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use App\Conversation;
use App\Thread;
use App\User;
use Illuminate\Http\Request;
use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use Modules\WPOrgSite\Services\PinnedNote;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the profile's choice, which note is pinned, and what the conversation shows.
 */
final class PinnedNoteTest extends TestCase {

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSiteServiceProvider::class );
		PinnedNote::reset();
	}

	/**
	 * Forgets the notes pinned, which outlive the test's database.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		PinnedNote::reset();

		parent::tearDown();
	}

	/**
	 * It's off until an agent turns it on, and only for them.
	 *
	 * @return void
	 */
	public function test_off_by_default(): void {
		$user  = $this->create_user();
		$other = $this->create_user();

		$this->assertFalse( PinnedNote::is_enabled( $user ) );

		PinnedNote::set_enabled( $user, true );
		$this->assertTrue( PinnedNote::is_enabled( $user ) );
		$this->assertFalse( PinnedNote::is_enabled( $other ) );

		PinnedNote::set_enabled( $user, false );
		$this->assertFalse( PinnedNote::is_enabled( $user ) );
		$this->assertNull( \Option::get( PinnedNote::OPTION . $user->id, null, true, false ) );
	}

	/**
	 * The latest note is pinned once an email came after it; older notes aren't.
	 *
	 * @return void
	 */
	public function test_finds_latest_note_pushed_down(): void {
		$agent        = $this->create_user();
		$conversation = $this->conversation();

		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Question', null, '2026-10-01 10:00:00' );
		$this->create_thread( $conversation, Thread::TYPE_NOTE, 'Older note', $agent, '2026-10-01 11:00:00' );
		$this->create_thread( $conversation, Thread::TYPE_MESSAGE, 'Answer', $agent, '2026-10-01 12:00:00' );
		$latest = $this->create_thread( $conversation, Thread::TYPE_NOTE, 'Latest note', $agent, '2026-10-01 13:00:00' );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Follow-up', null, '2026-10-02 10:00:00' );

		$this->assertSame( $latest->id, PinnedNote::find( $conversation )->id );
	}

	/**
	 * A note that only status changes came after is near enough to the top; drafts of notes don't count.
	 *
	 * @return void
	 */
	public function test_skips_notes_on_top_and_drafts(): void {
		$agent        = $this->create_user();
		$conversation = $this->conversation();

		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Question', null, '2026-10-01 10:00:00' );
		$note = $this->create_thread( $conversation, Thread::TYPE_NOTE, 'Note', $agent, '2026-10-01 11:00:00' );
		$this->create_thread( $conversation, Thread::TYPE_LINEITEM, '', $agent, '2026-10-01 12:00:00' );

		$this->assertNull( PinnedNote::find( $conversation ) );

		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Follow-up', null, '2026-10-02 10:00:00' );
		$draft        = $this->create_thread( $conversation, Thread::TYPE_NOTE, 'Unsent note', $agent, '2026-10-02 11:00:00' );
		$draft->state = Thread::STATE_DRAFT;
		$draft->save();

		$this->assertSame( $note->id, PinnedNote::find( $conversation )->id );
	}

	/**
	 * Without notes, nothing is pinned.
	 *
	 * @return void
	 */
	public function test_nothing_without_notes(): void {
		$agent        = $this->create_user();
		$conversation = $this->conversation();

		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Question', null, '2026-10-01 10:00:00' );
		$this->create_thread( $conversation, Thread::TYPE_MESSAGE, 'Answer', $agent, '2026-10-01 12:00:00' );

		$this->assertNull( PinnedNote::find( $conversation ) );
	}

	/**
	 * Agents who pin it get the copy above the threads, linking to the note, which is labeled; others get neither.
	 *
	 * @return void
	 */
	public function test_shows_copy_and_label(): void {
		$agent = $this->create_user();
		$this->actingAs( $agent );

		$conversation = $this->conversation();
		$question     = $this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Question', null, '2026-10-01 10:00:00' );
		$note         = $this->create_thread( $conversation, Thread::TYPE_NOTE, '<p>Closed for a <strong>security issue</strong>.</p>', $agent, '2026-10-01 11:00:00' );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Follow-up', null, '2026-10-02 10:00:00' );

		$this->assertSame( '', $this->before_threads( $conversation ) );
		$this->assertSame( '', $this->info( $note ) );

		PinnedNote::set_enabled( $agent, true );
		$html = $this->before_threads( $conversation );

		$this->assertStringContainsString( 'id="wporgsite-pinned-note"', $html );
		$this->assertStringContainsString( 'Closed for a <strong>security issue</strong>.', $html );
		$this->assertStringContainsString( 'href="#thread-' . $note->id . '"', $html );
		$this->assertStringContainsString( e( $agent->getFullName() ), $html );

		$this->assertStringContainsString( 'href="#wporgsite-pinned-note"', $this->info( $note ) );
		$this->assertSame( '', $this->info( $question ) );
	}

	/**
	 * The profile shows the choice, and saves it when the form has it.
	 *
	 * @return void
	 */
	public function test_profile_saves_choice(): void {
		$user = $this->create_user();

		ob_start();
		\Eventy::action( 'user.edit.before_photo', $user );
		$field = (string) ob_get_clean();

		$this->assertStringContainsString( 'name="' . PinnedNote::FIELD . '" value="0"', $field );
		$this->assertStringContainsString( 'id="' . PinnedNote::FIELD . '"', $field );
		$this->assertStringNotContainsString( 'checked', $field );

		\Eventy::filter( 'user.save_profile', $user, new Request( array( PinnedNote::FIELD => '1' ) ) );
		$this->assertTrue( PinnedNote::is_enabled( $user ) );

		// Saved elsewhere, without the field, it stays as it was.
		\Eventy::filter( 'user.save_profile', $user, new Request() );
		$this->assertTrue( PinnedNote::is_enabled( $user ) );

		\Eventy::filter( 'user.save_profile', $user, new Request( array( PinnedNote::FIELD => '0' ) ) );
		$this->assertFalse( PinnedNote::is_enabled( $user ) );
	}

	/**
	 * A deleted agent's choice is forgotten.
	 *
	 * @return void
	 */
	public function test_forgets_deleted_users(): void {
		$user = $this->create_user();
		PinnedNote::set_enabled( $user, true );

		\Eventy::action( 'user.deleted', $user );

		$this->assertFalse( PinnedNote::is_enabled( $user ) );
	}

	/**
	 * A conversation in a new mailbox.
	 *
	 * @return Conversation
	 */
	private function conversation(): Conversation {
		return $this->create_conversation( $this->create_mailbox(), $this->create_sender() );
	}

	/**
	 * What shows above a conversation's threads.
	 *
	 * @param Conversation $conversation Conversation.
	 * @return string HTML.
	 */
	private function before_threads( Conversation $conversation ): string {
		ob_start();
		\Eventy::action( 'conversation.before_threads', $conversation );

		return (string) ob_get_clean();
	}

	/**
	 * What shows at the start of a thread's info, by its date.
	 *
	 * @param Thread $thread Thread.
	 * @return string HTML.
	 */
	private function info( Thread $thread ): string {
		ob_start();
		\Eventy::action( 'thread.info.prepend', $thread );

		return (string) ob_get_clean();
	}
}
