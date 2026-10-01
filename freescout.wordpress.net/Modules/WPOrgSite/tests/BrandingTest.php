<?php
/**
 * Tests for WordPress.org's logos in FreeScout.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers replacing FreeScout's logos.
 */
final class BrandingTest extends TestCase {

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
	 * The favicon, header logo, and login banner are the module's images, which exist.
	 *
	 * @return void
	 */
	public function test_replaces_logos(): void {
		$images = array(
			'layout.favicon'     => 'wordpress-mark.svg',
			'layout.header_logo' => 'wordpress-mark-white.svg',
			'login.banner'       => 'wordpress-logo.svg',
		);

		foreach ( $images as $filter => $image ) {
			$this->assertSame( asset( '/modules/wporgsite/img/' . $image ), \Eventy::filter( $filter, 'freescout.png' ) );
			$this->assertFileExists( dirname( __DIR__ ) . '/Public/img/' . $image );
		}
	}
}
