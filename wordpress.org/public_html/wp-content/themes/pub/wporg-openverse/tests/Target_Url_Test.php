<?php
/**
 * Tests the URL the Openverse theme forwards visitors to.
 *
 * @package WordPressdotorg\Openverse\Theme\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

use function WordPressdotorg\Openverse\Theme\get_target_url;
use function WordPressdotorg\Openverse\Theme\is_valid_target_url;

/**
 * Covers the two decisions the redirect makes: which URL the request maps to,
 * and whether that URL is one the theme will send a visitor to.
 *
 * Extends the plain PHPUnit TestCase rather than WP_UnitTestCase, which is not
 * compatible with the PHPUnit 11 runner this suite uses. Nothing here writes to
 * the database, so no per-test transaction is needed.
 *
 * The group is declared as an attribute as well as `@group`: PHPUnit 11 ignores
 * a class-level `@group` docblock, while older runners ignore the attribute.
 *
 * @group openverse
 */
#[Group( 'openverse' )]
class Target_Url_Test extends TestCase {

	/**
	 * The origin the tests configure the theme with.
	 */
	private const ORIGIN = 'https://openverse.org';

	/**
	 * `REQUEST_URI` as it was before the running test replaced it.
	 *
	 * @var string|null
	 */
	private ?string $request_uri = null;

	/**
	 * Configures the theme the way the live site is configured.
	 */
	protected function setUp(): void {
		parent::setUp();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Stored verbatim so tear down can put it back.
		$this->request_uri = $_SERVER['REQUEST_URI'] ?? null;

		add_filter( 'theme_mod_ov_is_redirect_enabled', '__return_true' );
		add_filter( 'theme_mod_ov_redirect_url', array( $this, 'origin' ) );
	}

	/**
	 * Restores the globals and filters the test changed.
	 */
	protected function tearDown(): void {
		remove_filter( 'theme_mod_ov_is_redirect_enabled', '__return_true' );
		remove_filter( 'theme_mod_ov_redirect_url', array( $this, 'origin' ) );
		remove_filter( 'locale', array( $this, 'russian' ) );

		if ( null === $this->request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->request_uri;
		}

		parent::tearDown();
	}

	/**
	 * Filter callback supplying the configured origin.
	 */
	public function origin(): string {
		return self::ORIGIN;
	}

	/**
	 * Filter callback switching the site to Russian.
	 */
	public function russian(): string {
		return 'ru_RU';
	}

	/**
	 * Requests and the URL each one should be forwarded to.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function requests(): array {
		return array(
			'site root'                  => array( '/openverse/', self::ORIGIN . '/' ),
			'no trailing slash'          => array( '/openverse', self::ORIGIN . '/' ),
			'query straight after'       => array( '/openverse?q=dog', self::ORIGIN . '/?q=dog' ),
			'search'                     => array( '/openverse/search/?q=dog', self::ORIGIN . '/search/?q=dog' ),
			'encoded space'              => array( '/openverse/search/?q=cat%20dog', self::ORIGIN . '/search/?q=cat%20dog' ),
			'non-ascii'                  => array( '/openverse/search/?q=caf%C3%A9', self::ORIGIN . '/search/?q=caf%C3%A9' ),
			'several parameters'         => array( '/openverse/search/?q=dog&license=cc0', self::ORIGIN . '/search/?q=dog&license=cc0' ),
			'page'                       => array( '/openverse/about/', self::ORIGIN . '/about/' ),
			'subpath inside a later segment' => array( '/openverse/image/openverse-logo/', self::ORIGIN . '/image/openverse-logo/' ),
			'subpath as a later segment' => array( '/openverse/tag/openverse/', self::ORIGIN . '/tag/openverse/' ),
			'subpath in the query'       => array( '/openverse/search/?q=/openverse', self::ORIGIN . '/search/?q=/openverse' ),
			'longer first segment'       => array( '/openverse-search', self::ORIGIN . '/openverse-search' ),
		);
	}

	/**
	 * Only a leading, whole-segment subpath is removed, and the rest is
	 * forwarded untouched.
	 *
	 * @param string $request_uri The incoming request.
	 * @param string $expected    The URL it should map to.
	 */
	#[DataProvider( 'requests' )]
	public function test_forwards_the_request_path( string $request_uri, string $expected ): void {
		$_SERVER['REQUEST_URI'] = $request_uri;

		$this->assertSame( $expected, get_target_url() );
	}

	/**
	 * Requests whose remainder could be read as part of a host name.
	 *
	 * @return array<string, array{string}>
	 */
	public static function awkward_requests(): array {
		return array(
			'at sign after the subpath'   => array( '/openverse/openverse@example.com/' ),
			'dot after the subpath'       => array( '/openverse/openverse.example.com/' ),
			'doubled slash'               => array( '/openverse//example.com' ),
			'at sign only'                => array( '/openverse/@example.com' ),
			'backslash'                   => array( '/openverse/\\\\example.com' ),
			'encoded slashes'             => array( '/openverse/%2F%2Fexample.com' ),
			'traversal'                   => array( '/openverse/..//example.com' ),
			'subpath twice over'          => array( '/openverse/openverse/openverse@example.com/' ),
		);
	}

	/**
	 * The forwarded URL always stays on the configured host.
	 *
	 * The remainder is appended to an origin that carries no trailing slash, so
	 * anything that does not start the path cleanly would land in the authority
	 * instead.
	 *
	 * @param string $request_uri The incoming request.
	 */
	#[DataProvider( 'awkward_requests' )]
	public function test_keeps_the_configured_host( string $request_uri ): void {
		$_SERVER['REQUEST_URI'] = $request_uri;

		$this->assertSame(
			'openverse.org',
			wp_parse_url( get_target_url(), PHP_URL_HOST ),
			'Forwarded to a different host: ' . get_target_url()
		);
	}

	/**
	 * A locale slug is inserted between the origin and the path.
	 */
	public function test_inserts_the_locale_before_the_path(): void {
		add_filter( 'locale', array( $this, 'russian' ) );
		$_SERVER['REQUEST_URI'] = '/openverse/search/?q=dog';

		$this->assertSame( self::ORIGIN . '/ru/search/?q=dog', get_target_url() );
	}

	/**
	 * Configured origins, and whether the theme will redirect to them.
	 *
	 * @return array<string, array{string, bool}>
	 */
	public static function targets(): array {
		return array(
			'https'            => array( 'https://openverse.org/search/', true ),
			'http'             => array( 'http://openverse.org/search/', true ),
			'explicit port'    => array( 'https://openverse.org:8443/search/', true ),
			'no scheme'        => array( 'openverse.org/search/', false ),
			'scheme relative'  => array( '//openverse.org/search/', false ),
			'other scheme'     => array( 'ftp://openverse.org/search/', false ),
			'javascript'       => array( 'javascript:alert(1)', false ),
			'path only'        => array( '/search/', false ),
			'empty'            => array( '', false ),
			'unlisted host'    => array( 'https://example.com/search/', false ),
		);
	}

	/**
	 * Only an absolute http or https URL on an allowed host is redirected to.
	 *
	 * `wp_validate_redirect()` repairs rather than rejects, so a target with no
	 * host or no scheme comes back truthy and cannot be relied on alone.
	 *
	 * @param string $target   The URL the theme would redirect to.
	 * @param bool   $expected Whether it should be redirected to.
	 */
	#[DataProvider( 'targets' )]
	public function test_redirects_only_to_an_absolute_http_url( string $target, bool $expected ): void {
		$this->assertSame( $expected, is_valid_target_url( $target ) );
	}
}
