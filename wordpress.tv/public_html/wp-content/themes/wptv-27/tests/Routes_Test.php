<?php
/**
 * Tests the legacy routes the theme keeps from wptv2.
 *
 * @package WordPressTV
 */

declare( strict_types = 1 );

namespace WordPressTV\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Existing WordPress.tv URLs must keep resolving to the same query.
 */
class Routes_Test extends TestCase {

	/**
	 * Request URI before the test.
	 *
	 * @var string|null
	 */
	private ?string $request_uri;

	/**
	 * Regenerates the rewrite rules, which the test install stored before the theme added its own.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->request_uri = $_SERVER['REQUEST_URI'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only restored.
		flush_rewrite_rules( false );
	}

	/**
	 * Restores the request URI.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_SERVER['REQUEST_URI'] = $this->request_uri;

		parent::tearDown();
	}

	/**
	 * Legacy URLs, with the query variable and value each resolves to.
	 *
	 * @return array[]
	 */
	public static function data_routes(): array {
		return array(
			'API'               => array( '/api/videos.json', 'wptvapi', 'videos.json' ),
			'oEmbed'            => array( '/oembed/', 'oembed', '1' ),
			'Amara subtitles'   => array( '/unisubs/', 'unisubs', '1' ),
			'event archive'     => array( '/event/wordcamp-europe-2024/', 'event', 'wordcamp-europe-2024' ),
			'producer username' => array( '/producer-username/wordpressdotorg/', 'producer-username', 'wordpressdotorg' ),
		);
	}

	/**
	 * A legacy URL resolves to the theme's query variable.
	 *
	 * @param string $path      Request path.
	 * @param string $query_var Query variable.
	 * @param string $value     Expected value.
	 * @return void
	 */
	#[DataProvider( 'data_routes' )]
	public function test_legacy_url_resolves( string $path, string $query_var, string $value ): void {
		$_SERVER['REQUEST_URI'] = $path;

		// A new WP instance lacks the taxonomies' query variables.
		$wp = clone $GLOBALS['wp'];
		$wp->parse_request();

		$this->assertSame( $value, $wp->query_vars[ $query_var ] ?? null );
	}
}
