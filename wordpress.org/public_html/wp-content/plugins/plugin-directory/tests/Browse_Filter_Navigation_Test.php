<?php
/**
 * Tests for the Plugin Directory browse filter navigation.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

require_once WP_CONTENT_DIR . '/themes/wporg-plugins-2024/inc/block-config.php';

/**
 * Tests the Plugin Directory browse filter navigation.
 *
 * @group plugin-directory
 */
#[Group( 'plugin-directory' )]
class Browse_Filter_Navigation_Test extends TestCase {
	/**
	 * Original request URI.
	 *
	 * @var string|null
	 */
	protected ?string $original_request_uri;

	/**
	 * Original HTTP host.
	 *
	 * @var string|null
	 */
	protected ?string $original_http_host;

	/**
	 * Original main query variables.
	 *
	 * @var array
	 */
	protected array $original_query_vars;

	/**
	 * Set request and query globals for an isolated navigation test.
	 */
	protected function setUp(): void {
		global $wp_query;

		parent::setUp();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Test-only globals are captured and restored without using their contents in output.
		$this->original_request_uri = $_SERVER['REQUEST_URI'] ?? null;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Test-only globals are captured and restored without using their contents in output.
		$this->original_http_host  = $_SERVER['HTTP_HOST'] ?? null;
		$this->original_query_vars = $wp_query->query_vars;
		$wp_query->init();
		$_SERVER['HTTP_HOST'] = wp_parse_url( home_url(), PHP_URL_HOST );
		$port                 = wp_parse_url( home_url(), PHP_URL_PORT );
		if ( $port ) {
			$_SERVER['HTTP_HOST'] .= ':' . $port;
		}
	}

	/**
	 * Restore request and query globals after the test.
	 */
	protected function tearDown(): void {
		global $wp_query;

		$wp_query->parse_query( $this->original_query_vars );

		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}

		if ( null === $this->original_http_host ) {
			unset( $_SERVER['HTTP_HOST'] );
		} else {
			$_SERVER['HTTP_HOST'] = $this->original_http_host;
		}

		parent::tearDown();
	}

	/**
	 * Build the menu through the theme's registered WordPress filter.
	 *
	 * @param string $request_uri Current request URI.
	 * @param array  $query_vars  WordPress query variables.
	 * @return array
	 */
	private function get_section_menu( string $request_uri, array $query_vars = array() ): array {
		global $wp_query;
		$wp_query->parse_query( $query_vars );
		$_SERVER['REQUEST_URI'] = $request_uri;
		$menu                   = apply_filters( 'wporg_block_navigation_menus', array() );

		return $menu['section-bar'];
	}

	/**
	 * Find a navigation item by label.
	 *
	 * @param array  $menu  Navigation items.
	 * @param string $label Item label.
	 * @return array
	 */
	private function find_menu_item( array $menu, string $label ): array {
		foreach ( $menu as $item ) {
			if ( $label === $item['label'] ) {
				return $item;
			}
		}

		$this->fail( 'Missing navigation item: ' . $label );
	}

	/**
	 * The All link keeps the current listing.
	 *
	 * @param string $path       Listing path.
	 * @param array  $query_vars WordPress query variables.
	 */
	#[DataProvider( 'listing_provider' )]
	public function test_all_link_preserves_listing( string $path, array $query_vars ): void {
		$menu = $this->get_section_menu( $path, $query_vars );

		$this->assertSame(
			home_url() . $path,
			$this->find_menu_item( $menu, 'All' )['url']
		);
	}

	/**
	 * Provide listing paths.
	 *
	 * @return array
	 */
	public static function listing_provider(): array {
		return array(
			'blocks'          => array( '/browse/blocks/', array( 'browse' => 'blocks' ) ),
			'popular'         => array( '/browse/popular/', array( 'browse' => 'popular' ) ),
			'featured'        => array( '/browse/featured/', array( 'browse' => 'featured' ) ),
			'beta'            => array( '/browse/beta/', array( 'browse' => 'beta' ) ),
			'production_path' => array( '/plugins/browse/blocks/', array( 'browse' => 'blocks' ) ),
			'tag'             => array( '/tags/widget/', array( 'plugin_tags' => 'widget' ) ),
			'category'        => array( '/category/widgets/', array( 'plugin_category' => 'widgets' ) ),
		);
	}

	/**
	 * The All link continues to point to the homepage there.
	 */
	public function test_all_link_points_to_homepage_on_homepage(): void {
		$menu = $this->get_section_menu( '/' );

		$this->assertSame( home_url( '/' ), $this->find_menu_item( $menu, 'All' )['url'] );
	}

	/**
	 * The All link retains the search URL without the business-model filter.
	 */
	public function test_all_link_uses_search_url_on_search_results(): void {
		$menu = $this->get_section_menu( '/?s=block+editor&plugin_business_model=commercial', array( 's' => 'block editor' ) );

		$this->assertTrue( is_search() );
		$this->assertSame( get_search_link(), $this->find_menu_item( $menu, 'All' )['url'] );
	}

	/**
	 * Community and Commercial links retain the browse section and add filters.
	 */
	public function test_community_and_commercial_links_keep_browse_section(): void {
		$menu = $this->get_section_menu( '/browse/blocks/', array( 'browse' => 'blocks' ) );

		$this->assertSame(
			home_url( '/browse/blocks/' ) . '?plugin_business_model=community',
			$this->find_menu_item( $menu, 'Community' )['url']
		);
		$this->assertSame(
			home_url( '/browse/blocks/' ) . '?plugin_business_model=commercial',
			$this->find_menu_item( $menu, 'Commercial' )['url']
		);
	}
}
