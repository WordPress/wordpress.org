<?php
/**
 * Tests for the module's scripts and stylesheet.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers adding the scripts, like the conversations' keyboard shortcuts and saved replies from the keyboard, and the
 * stylesheet, which do their work in the browser.
 */
final class AssetsTest extends TestCase {

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
	 * Every page gets them after core's, and they exist.
	 *
	 * @return void
	 */
	public function test_adds_assets(): void {
		$assets = array(
			'stylesheets' => array( '/css/site.css' ),
			'javascripts' => array( '/js/modules.js', '/js/alt-conversation.js', '/js/conversation.js', '/js/saved-replies.js', '/js/sidebar.js', '/js/header.js', '/js/discard.js' ),
		);

		foreach ( $assets as $filter => $files ) {
			$added = (array) \Eventy::filter( $filter, array( 'core' ) );

			$this->assertSame( 'core', $added[0] );
			foreach ( $files as $file ) {
				$this->assertContains( \Module::getPublicPath( WPOrgSiteServiceProvider::ALIAS ) . $file, $added );
				$this->assertFileExists( dirname( __DIR__ ) . '/Public' . $file );
			}
		}
	}
}
