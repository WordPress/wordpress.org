<?php
/**
 * Tests for Submit_Plugin::is_allowed_zip_url().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Abilities\Plugins\Plugin_Directory\Tools\Submit_Plugin;

/**
 * Covers which URLs the submit-plugin ability is willing to download a ZIP from.
 *
 * @group abilities
 */
#[Group( 'abilities' )]
class Submit_Plugin_Zip_URL_Test extends TestCase {

	/**
	 * Loads the ability, which lives in a plugin the test environment does not activate.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		require_once WP_PLUGIN_DIR . '/wporg-abilities/plugins/plugin-directory/class-ability-base.php';
		require_once WP_PLUGIN_DIR . '/wporg-abilities/plugins/plugin-directory/tools/class-submit-plugin.php';
	}

	/**
	 * Only HTTPS URLs on the default port of a listed host may be requested.
	 *
	 * @dataProvider data_zip_urls
	 *
	 * @param string $url      URL the download would request.
	 * @param bool   $expected Whether it may be requested.
	 * @return void
	 */
	#[DataProvider( 'data_zip_urls' )]
	public function test_only_listed_hosts_may_be_requested( string $url, bool $expected ): void {
		$this->assertSame( $expected, Submit_Plugin::is_allowed_zip_url( $url ) );
	}

	/**
	 * Data provider for test_only_listed_hosts_may_be_requested().
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function data_zip_urls(): array {
		return array(
			'release on a listed host'            => array( 'https://github.com/example/plugin/releases/download/1.0/plugin.zip', true ),
			'host a listed download redirects to' => array( 'https://release-assets.githubusercontent.com/github-production-release-asset/1/abc', true ),
			'unlisted host'                       => array( 'https://example.org/plugin.zip', false ),
			'listed host as a subdomain label'    => array( 'https://github.com.example.org/plugin.zip', false ),
			'listed host on another port'         => array( 'https://github.com:8080/plugin.zip', false ),
			'listed host over plain HTTP'         => array( 'http://github.com/example/plugin/archive/refs/heads/trunk.zip', false ),
		);
	}
}
