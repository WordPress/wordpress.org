<?php
/**
 * Tests for the alternate conversation design.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use App\Thread;
use Illuminate\Http\Request;
use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use Modules\WPOrgSite\Services\AltConversation;
use Modules\WPOrgSite\Services\PinnedNote;
use Modules\WPOrgSite\Services\UserChoice;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the profile's choice and the body's class, which alt-conversation.js lays the conversation out by.
 */
final class AltConversationTest extends TestCase {

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSiteServiceProvider::class );
	}

	/**
	 * It's off until an agent turns it on in their profile, which shows it next to the pinned note.
	 *
	 * @return void
	 */
	public function test_profile_saves_choice(): void {
		$user = $this->create_user();

		$this->assertFalse( UserChoice::get( $user, AltConversation::OPTION ) );

		ob_start();
		\Eventy::action( 'user.edit.before_photo', $user );
		$profile = (string) ob_get_clean();

		$this->assertStringContainsString( 'id="' . AltConversation::FIELD . '"', $profile );
		$this->assertStringContainsString( 'id="' . PinnedNote::FIELD . '"', $profile );
		$this->assertStringContainsString( 'Alternate conversation design', $profile );

		\Eventy::filter( 'user.save_profile', $user, new Request( array( AltConversation::FIELD => '1' ) ) );
		$this->assertTrue( UserChoice::get( $user, AltConversation::OPTION ) );
		$this->assertFalse( UserChoice::get( $user, PinnedNote::OPTION ) );

		\Eventy::filter( 'user.save_profile', $user, new Request( array( AltConversation::FIELD => '0' ) ) );
		$this->assertFalse( UserChoice::get( $user, AltConversation::OPTION ) );
	}

	/**
	 * The body gets the class only for agents who chose the design.
	 *
	 * @return void
	 */
	public function test_body_class(): void {
		$user = $this->create_user();
		$this->actingAs( $user );

		$this->assertStringNotContainsString( AltConversation::BODY_CLASS, $this->body_class() );

		UserChoice::set( $user, AltConversation::OPTION, true );
		$this->assertStringContainsString( ' ' . AltConversation::BODY_CLASS, $this->body_class() );

		// Others' choice is theirs.
		$this->actingAs( $this->create_user() );
		$this->assertStringNotContainsString( AltConversation::BODY_CLASS, $this->body_class() );
	}

	/**
	 * In the design, threads go oldest first, so the pinned note's copy is at the bottom, and its label says so.
	 *
	 * @return void
	 */
	public function test_pinned_note_is_at_the_bottom(): void {
		$agent = $this->create_user();
		$this->actingAs( $agent );
		UserChoice::set( $agent, PinnedNote::OPTION, true );

		$conversation = $this->create_conversation( $this->create_mailbox(), $this->create_sender() );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Question', null, '2026-10-01 10:00:00' );
		$note = $this->create_thread( $conversation, Thread::TYPE_NOTE, 'Context', $agent, '2026-10-01 11:00:00' );
		$this->create_thread( $conversation, Thread::TYPE_CUSTOMER, 'Follow-up', null, '2026-10-02 10:00:00' );

		ob_start();
		\Eventy::action( 'conversation.before_threads', $conversation );
		\Eventy::action( 'thread.info.prepend', $note );
		$this->assertStringContainsString( 'Pinned to the top', (string) ob_get_clean() );

		UserChoice::set( $agent, AltConversation::OPTION, true );
		ob_start();
		\Eventy::action( 'thread.info.prepend', $note );
		$this->assertStringContainsString( 'Pinned to the bottom', (string) ob_get_clean() );
	}

	/**
	 * A deleted agent's choice is forgotten.
	 *
	 * @return void
	 */
	public function test_forgets_deleted_users(): void {
		$user = $this->create_user();
		UserChoice::set( $user, AltConversation::OPTION, true );

		\Eventy::action( 'user.deleted', $user );

		$this->assertFalse( UserChoice::get( $user, AltConversation::OPTION ) );
	}

	/**
	 * What the body's class gets.
	 *
	 * @return string Classes.
	 */
	private function body_class(): string {
		ob_start();
		\Eventy::action( 'body.class' );

		return (string) ob_get_clean();
	}
}
