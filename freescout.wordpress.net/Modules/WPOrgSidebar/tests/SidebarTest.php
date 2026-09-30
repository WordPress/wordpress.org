<?php
/**
 * Tests for the sidebar panels and their endpoint.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Tests;

use App\Conversation;
use App\User;
use Modules\WPOrgSidebar\Providers\WPOrgSidebarServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers panel rendering and access control on the panel endpoint.
 */
final class SidebarTest extends TestCase {

	/**
	 * Conversation under test.
	 *
	 * @var Conversation
	 */
	private $conversation;

	/**
	 * Registers the module and creates a conversation.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSidebarServiceProvider::class );

		// Routes registered after boot aren't in the name index yet.
		$this->app['router']->getRoutes()->refreshNameLookups();

		$this->conversation = $this->create_conversation( $this->create_mailbox(), $this->create_sender() );
	}

	/**
	 * Each configured panel gets a placeholder pointing at its endpoint.
	 *
	 * @return void
	 */
	public function test_renders_a_placeholder_per_panel(): void {
		ob_start();
		\Eventy::action( 'conversation.after_customer_sidebar', $this->conversation );
		$html = (string) ob_get_clean();

		foreach ( array_keys( (array) config( 'wporgsidebar.panels' ) ) as $panel ) {
			$this->assertStringContainsString( '/wporgsidebar/' . $this->conversation->id . '/' . $panel . '"', $html );
		}
	}

	/**
	 * Without a secret, panels are left out rather than failing on every load.
	 *
	 * @return void
	 */
	public function test_renders_nothing_without_secret(): void {
		config( array( 'wporgsidebar.secret' => '' ) );

		ob_start();
		\Eventy::action( 'conversation.after_customer_sidebar', $this->conversation );

		$this->assertSame( '', trim( (string) ob_get_clean() ) );
	}

	/**
	 * Only configured panels are proxied.
	 *
	 * @return void
	 */
	public function test_unknown_panel_is_not_found(): void {
		$this->actingAs( $this->create_user() )
			->get( '/wporgsidebar/' . $this->conversation->id . '/unknown' )
			->assertStatus( 404 );
	}

	/**
	 * Agents can't read panels for conversations in mailboxes they can't access.
	 *
	 * @return void
	 */
	public function test_requires_access_to_the_conversation(): void {
		$this->actingAs( $this->create_user( User::ROLE_USER ) )
			->get( '/wporgsidebar/' . $this->conversation->id . '/profile' )
			->assertStatus( 403 );
	}

	/**
	 * An unreachable API yields an error the panel can show, not an exception.
	 *
	 * @return void
	 */
	public function test_unreachable_api_is_a_bad_gateway(): void {
		$this->actingAs( $this->create_user() )
			->get( '/wporgsidebar/' . $this->conversation->id . '/profile' )
			->assertStatus( 502 )
			->assertExactJson( array( 'html' => '' ) );
	}
}
