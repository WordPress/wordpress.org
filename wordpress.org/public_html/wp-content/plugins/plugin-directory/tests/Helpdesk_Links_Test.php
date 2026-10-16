<?php
/**
 * Tests the links to the helpdesk the plugins team emails authors from.
 *
 * Links go to Help Scout until FreeScout's Plugins mailbox is configured, so the switch is a configuration change.
 *
 * @package plugin-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Tools\Helpdesk;

/**
 * Tests for `Helpdesk`.
 *
 * @group plugin-directory
 */
#[Group( 'plugin-directory' )]
class Helpdesk_Links_Test extends TestCase {

	/**
	 * Filter that sets FreeScout's Plugins mailbox, while it's added.
	 *
	 * @var Closure|null
	 */
	protected $mailbox_filter = null;

	/**
	 * Removes the mailbox filter.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( $this->mailbox_filter ) {
			remove_filter( 'wporg_plugins_freescout_mailbox_id', $this->mailbox_filter );
			$this->mailbox_filter = null;
		}

		parent::tearDown();
	}

	/**
	 * Without FreeScout's mailbox, links go to Help Scout, as they did.
	 *
	 * @return void
	 */
	public function test_links_to_help_scout_until_freescout_is_configured(): void {
		$this->assertFalse( Helpdesk::is_freescout() );
		$this->assertSame( 'HS', Helpdesk::short_name() );
		$this->assertSame( 'https://secure.helpscout.net/search/?query=mailbox:Plugins%20My%20Plugin', Helpdesk::search_url( 'My Plugin' ) );
		$this->assertSame( 'https://secure.helpscout.net/conversation/1000000028/12345', Helpdesk::conversation_url( 1000000028, 12345, false ) );
		$this->assertSame(
			'https://secure.helpscout.net/mailbox/ad3e85554c5bd064/new-ticket/?name=Jane+Author&email=jane%40example.org&cc=bob%40example.org%2Csam%40example.org&subject=Review+in+Progress%3A+My+Plugin',
			Helpdesk::new_conversation_url( $this->author(), array( 'bob@example.org', 'sam@example.org' ), 'Review in Progress: My Plugin' )
		);
	}

	/**
	 * With FreeScout's mailbox, links search it, and open a new conversation in it with every committer.
	 *
	 * @return void
	 */
	public function test_links_to_freescout_once_configured(): void {
		$this->mailbox_filter = static function (): int {
			return 3;
		};
		add_filter( 'wporg_plugins_freescout_mailbox_id', $this->mailbox_filter );

		$this->assertTrue( Helpdesk::is_freescout() );
		$this->assertSame( array( 'FreeScout', 'FS' ), array( Helpdesk::name(), Helpdesk::short_name() ) );
		$this->assertSame( 'https://freescout.wordpress.net/search?q=jane%40example.org&f%5Bmailbox%5D=3', Helpdesk::search_url( 'jane@example.org' ) );
		$this->assertSame( 'https://freescout.wordpress.net/conversation/42', Helpdesk::conversation_url( 42, 12345, true ) );
		$this->assertSame(
			'https://freescout.wordpress.net/mailbox/3/new-ticket?to=jane%40example.org%2Cbob%40example.org&subject=Review+in+Progress%3A+My+Plugin',
			Helpdesk::new_conversation_url( $this->author(), array( 'bob@example.org' ), 'Review in Progress: My Plugin' )
		);
	}

	/**
	 * A plugin author.
	 *
	 * @return WP_User
	 */
	protected function author(): WP_User {
		$author               = new WP_User();
		$author->display_name = 'Jane Author';
		$author->user_email   = 'jane@example.org';

		return $author;
	}
}
