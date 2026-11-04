<?php
/**
 * Tests for collapsing the sidebar.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers the script in the page's head that collapses the sidebar before it shows; sidebar.js does the rest.
 */
final class SidebarTest extends TestCase {

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
	 * The head gets the script, with core's nonce, reading what sidebar.js remembers.
	 *
	 * @return void
	 */
	public function test_head_collapses_sidebar(): void {
		ob_start();
		\Eventy::action( 'layout.head' );
		$head = (string) ob_get_clean();

		$this->assertStringContainsString( '<script nonce="' . \Helper::cspNonce() . '">', $head );
		$this->assertStringContainsString( 'localStorage.getItem("' . WPOrgSiteServiceProvider::SIDEBAR_KEY . '")==="collapsed"', $head );
		$this->assertStringContainsString( 'classList.add("wporgsite-sidebar-collapsed")', $head );

		$script = (string) file_get_contents( dirname( __DIR__ ) . '/Public/js/sidebar.js' );
		$this->assertStringContainsString( "const KEY = '" . WPOrgSiteServiceProvider::SIDEBAR_KEY . "';", $script );
	}
}
