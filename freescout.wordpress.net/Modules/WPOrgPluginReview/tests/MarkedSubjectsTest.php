<?php
/**
 * Tests for marking the plugin directory's subjects in conversations and lists.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests;

use App\Conversation;
use App\Customer;
use App\Mailbox;
use Modules\WPOrgPluginReview\Providers\WPOrgPluginReviewServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers what the conversation tells its script, and what lists of conversations show, in the plugins team's mailbox
 * and in others.
 */
final class MarkedSubjectsTest extends TestCase {

	/**
	 * The plugins team's mailbox.
	 *
	 * @var Mailbox
	 */
	private $mailbox;

	/**
	 * Sender.
	 *
	 * @var Customer
	 */
	private $sender;

	/**
	 * Registers the module, and acts as an agent.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgPluginReviewServiceProvider::class );

		$this->mailbox = $this->create_mailbox( 'Plugins', 'plugins@wordpress.test' );
		$this->sender  = $this->create_sender();
		$this->actingAs( $this->create_user() );
	}

	/**
	 * The conversation tells its script its subjects, for its title, and the marked subjects of the sender's others in
	 * the mailbox, for the sidebar, escaped.
	 *
	 * @return void
	 */
	public function test_conversation_has_its_subjects_and_its_previous_ones(): void {
		$conversation = $this->conversation( '[WordPress Plugin Directory] Review in Progress: My <Plugin>' );
		$rejection    = $this->conversation( 'Re: [WordPress Plugin Directory] Rejection Explanation: Old Plugin' );
		$this->conversation( 'Question about my plugin' );
		$elsewhere = $this->conversation( '[WordPress Plugin Directory] Notice: Other Plugin', $this->create_mailbox( 'Themes', 'themes@wordpress.test' ) );

		$html = $this->subjects( $conversation );

		$this->assertStringContainsString( 'data-number="' . $conversation->number . '"', $html );
		$this->assertStringContainsString( 'data-subject="[WordPress Plugin Directory] Review in Progress: My &lt;Plugin&gt;"', $html );
		$this->assertStringContainsString( 'data-short-subject="🔌 My &lt;Plugin&gt;"', $html );
		$this->assertStringNotContainsString( '<Plugin>', $html );

		$this->assertTrue( (bool) preg_match( '/data-subjects="([^"]*)"/', $html, $matches ) );
		$this->assertSame(
			array(
				$conversation->id => array(
					'subject' => '[WordPress Plugin Directory] Review in Progress: My <Plugin>',
					'marked'  => '🔌 My <Plugin>',
				),
				$rejection->id    => array(
					'subject' => 'Re: [WordPress Plugin Directory] Rejection Explanation: Old Plugin',
					'marked'  => '🔌🚫 Old Plugin',
				),
			),
			json_decode( html_entity_decode( $matches[1] ), true )
		);

		$this->assertSame( '', $this->subjects( $elsewhere ) );
	}

	/**
	 * A conversation without a marked subject still gets its title shortened, with nothing for the sidebar.
	 *
	 * @return void
	 */
	public function test_conversation_without_a_marked_subject(): void {
		$html = $this->subjects( $this->conversation( 'Question about my plugin' ) );

		$this->assertStringContainsString( 'data-short-subject=""', $html );
		$this->assertStringContainsString( 'data-subjects="{}"', $html );
	}

	/**
	 * Lists show a marked subject, with the subject itself hidden after it, on hover, and for copying; other subjects,
	 * and other mailboxes', as they are.
	 *
	 * @return void
	 */
	public function test_lists_mark_subjects(): void {
		$this->assertSame(
			'<span class="wporg-subject" data-wporg-subject="Re: [WordPress Plugin Directory] Security Notice: My &lt;Plugin&gt;" title="Re: [WordPress Plugin Directory] Security Notice: My &lt;Plugin&gt;">🔌🛡️ My &lt;Plugin&gt;</span><span hidden>Re: [WordPress Plugin Directory] Security Notice: My &lt;Plugin&gt;</span>',
			$this->row( $this->conversation( 'Re: [WordPress Plugin Directory] Security Notice: My <Plugin>' ) )
		);
		$this->assertSame( 'Question about my plugin', $this->row( $this->conversation( 'Question about my plugin' ) ) );
		$this->assertSame(
			'[WordPress Plugin Directory] Notice: My Plugin',
			$this->row( $this->conversation( '[WordPress Plugin Directory] Notice: My Plugin', $this->create_mailbox( 'Themes', 'themes@wordpress.test' ) ) )
		);
	}

	/**
	 * Creates a conversation from the sender.
	 *
	 * @param string       $subject Subject.
	 * @param Mailbox|null $mailbox Mailbox; the plugins team's if null.
	 * @return Conversation
	 */
	private function conversation( string $subject, ?Mailbox $mailbox = null ): Conversation {
		$conversation = $this->create_conversation( $mailbox ?? $this->mailbox, $this->sender );
		$conversation->update( array( 'subject' => $subject ) );

		return $conversation;
	}

	/**
	 * Renders what the module adds after a conversation's subject.
	 *
	 * @param Conversation $conversation Conversation.
	 * @return string
	 */
	private function subjects( Conversation $conversation ): string {
		ob_start();
		\Eventy::action( 'conversation.after_subject', $conversation, $conversation->mailbox );

		return trim( (string) ob_get_clean() );
	}

	/**
	 * Renders a conversation's subject in a list, as core does between its hooks.
	 *
	 * @param Conversation $conversation Conversation.
	 * @return string
	 */
	private function row( Conversation $conversation ): string {
		ob_start();
		\Eventy::action( 'conversations_table.before_subject', $conversation );
		echo e( $conversation->getSubject() );
		\Eventy::action( 'conversations_table.after_subject', $conversation );

		return (string) ob_get_clean();
	}
}
