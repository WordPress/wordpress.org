<?php
/**
 * Tests for pinning a conversation's header.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use Modules\WPOrgSite\Services\AltConversation;
use Modules\WPOrgSite\Services\UserChoice;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the script in the page's head that pins the header before the page shows; header.js does the rest.
 */
final class HeaderTest extends TestCase {

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
	 * The head's script pins the header as header.js remembers it, unless the agent uses the alternate design, which
	 * has no header to pin.
	 *
	 * @return void
	 */
	public function test_head_pins_header(): void {
		$user = $this->create_user();
		$this->actingAs( $user );

		$pins = 'localStorage.getItem("' . WPOrgSiteServiceProvider::HEADER_KEY . '")==="pinned"';
		$this->assertStringContainsString( $pins, $this->head() );
		$this->assertStringContainsString( 'classList.add("wporgsite-header-pinned")', $this->head() );

		UserChoice::set( $user, AltConversation::OPTION, true );
		$this->assertStringNotContainsString( $pins, $this->head() );
		$this->assertStringContainsString( 'classList.add("wporgsite-sidebar-collapsed")', $this->head() );

		$script = (string) file_get_contents( dirname( __DIR__ ) . '/Public/js/header.js' );
		$this->assertStringContainsString( "const KEY = '" . WPOrgSiteServiceProvider::HEADER_KEY . "';", $script );
	}

	/**
	 * What the page's head gets.
	 *
	 * @return string HTML.
	 */
	private function head(): string {
		ob_start();
		\Eventy::action( 'layout.head' );

		return (string) ob_get_clean();
	}
}
