<?php
/**
 * Tests for the Modules page's list.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers leaving FreeScout's Modules Directory and Marketplace off the Modules page.
 */
final class ModulesPageTest extends TestCase {

	/**
	 * Registers the module, logs in an administrator, and stands in for FreeScout's modules directory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSiteServiceProvider::class );
		$this->actingAs( $this->create_user() );

		$freescout = \Config::get( 'app.freescout_url' );

		\Cache::put(
			'modules_directory',
			array(
				array(
					'alias'              => 'wporgsite-official',
					'name'               => 'Official Directory Module',
					'author'             => 'FreeScout',
					'authorUrl'          => $freescout,
					'detailsUrl'         => $freescout . '/module/official/',
					'version'            => '1.0.0',
					'description'        => 'A module.',
					'img'                => '',
					'requiredAppVersion' => '1.0.0',
				),
				array(
					'alias'              => 'wporgsite-third-party',
					'name'               => 'Marketplace Module',
					'author'             => 'Someone Else',
					'authorUrl'          => 'https://example.com',
					'detailsUrl'         => $freescout . '/module/third-party/',
					'version'            => '1.0.0',
					'description'        => 'A module.',
					'img'                => '',
					'requiredAppVersion' => '1.0.0',
				),
			),
			now()->addMinutes( 15 )
		);
	}

	/**
	 * Clears the stand-in directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		\Cache::forget( 'modules_directory' );

		parent::tearDown();
	}

	/**
	 * Only installed modules are listed.
	 *
	 * @return void
	 */
	public function test_lists_only_installed_modules(): void {
		// Laravel's assertDontSee() doesn't work with this PHPUnit.
		$page = $this->get( route( 'modules' ) )->assertStatus( 200 )->getContent();

		$this->assertStringNotContainsString( 'Official Directory Module', $page );
		$this->assertStringNotContainsString( 'Marketplace Module', $page );
		$this->assertStringNotContainsString( 'third-party-container', $page );
	}
}
