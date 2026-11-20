<?php
/**
 * Tests the rewrite rules for favorites pages.
 *
 * @package WordPressdotorg\Photo_Directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Photo_Directory\Favorites;

/**
 * Tests how `/favorites/` paths are parsed.
 *
 * @group photo-directory
 */
#[Group( 'photo-directory' )]
class Favorites_Rewrite_Test extends TestCase {

	/**
	 * Permalink structure before the test.
	 *
	 * @var string
	 */
	private string $permalink_structure = '';

	/**
	 * `$_SERVER['REQUEST_URI']` before the test.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	/**
	 * Turns on pretty permalinks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		global $wp_rewrite;

		parent::setUp();

		$this->permalink_structure = (string) get_option( 'permalink_structure' );
		$this->request_uri         = $_SERVER['REQUEST_URI'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Saved as is, to restore after the test.

		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		$wp_rewrite->flush_rules( false );
	}

	/**
	 * Restores the permalink structure.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		global $wp_rewrite;

		$wp_rewrite->set_permalink_structure( $this->permalink_structure );
		$wp_rewrite->flush_rules( false );

		if ( null === $this->request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}

		parent::tearDown();
	}

	/**
	 * Parses a request path and returns the favorites-related query variables.
	 *
	 * @param string $path Request path.
	 * @return array<string, string>
	 */
	private function query_vars_for( string $path ): array {
		$_SERVER['REQUEST_URI'] = $path;

		$wp = new WP();
		$wp->parse_request();

		$query_vars = array_intersect_key(
			$wp->query_vars,
			array_flip(
				array(
					Favorites::QUERY_VAR_MY_FAVORITES,
					Favorites::QUERY_VAR_USER_FAVORITES,
					Favorites::QUERY_VAR_USER_SLUG,
					'feed',
					'paged',
				)
			)
		);
		ksort( $query_vars );

		return $query_vars;
	}

	/**
	 * Favorites paths and the query variables they should set.
	 *
	 * @return array<string, array{string, array<string, string>}>
	 */
	public static function favorites_paths(): array {
		return array(
			'my favorites'                => array( '/favorites/', array( 'favorites-current-user' => '1' ) ),
			'my favorites, page 2'        => array(
				'/favorites/page/2/',
				array(
					'favorites-current-user' => '1',
					'paged'                  => '2',
				),
			),
			"a user's favorites"          => array( '/favorites/someone/', array( 'favorites-user' => 'someone' ) ),
			"a user's favorites, page 3"  => array(
				'/favorites/someone/page/3/',
				array(
					'favorites-user' => 'someone',
					'paged'          => '3',
				),
			),
			'a user named page'           => array( '/favorites/page/', array( 'favorites-user' => 'page' ) ),
			'page 2 of a user named page' => array(
				'/favorites/page/page/2/',
				array(
					'favorites-user' => 'page',
					'paged'          => '2',
				),
			),
			"a user's favorites feed"     => array(
				'/favorites/someone/feed/',
				array(
					'feed'      => 'favorites-user-feed',
					'user_slug' => 'someone',
				),
			),
		);
	}

	/**
	 * Parses each favorites path to the right page.
	 *
	 * @param string                $path     Request path.
	 * @param array<string, string> $expected Expected query variables.
	 * @return void
	 */
	#[DataProvider( 'favorites_paths' )]
	public function test_favorites_path( string $path, array $expected ): void {
		ksort( $expected );

		$this->assertSame( $expected, $this->query_vars_for( $path ) );
	}

	/**
	 * Does not read extra path segments as a user's favorites page.
	 *
	 * @return void
	 */
	public function test_extra_segments_are_not_a_favorites_page(): void {
		$this->assertArrayNotHasKey( Favorites::QUERY_VAR_USER_FAVORITES, $this->query_vars_for( '/favorites/someone/extra/' ) );
		$this->assertArrayNotHasKey( Favorites::QUERY_VAR_USER_FAVORITES, $this->query_vars_for( '/favorites/someone/page/3/extra/' ) );
	}
}
