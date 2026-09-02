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
use function WordPressdotorg\Openverse\Theme\is_redirect_enabled;
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
		remove_filter( 'locale', array( $this, 'polish' ) );

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
	 * Filter callback switching the site to Polish.
	 */
	public function polish(): string {
		return 'pl_PL';
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

		$target = get_target_url();

		$this->assertStringStartsWith(
			self::ORIGIN . '/',
			$target,
			'Forwarded somewhere other than a path under the origin: ' . $target
		);
	}

	/**
	 * A locale slug is inserted between the origin and the path.
	 */
	public function test_inserts_the_locale_before_the_path(): void {
		add_filter( 'locale', array( $this, 'polish' ) );
		$_SERVER['REQUEST_URI'] = '/openverse/search/?q=dog';

		$this->assertSame( self::ORIGIN . '/pl/search/?q=dog', get_target_url() );
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
			'another host'     => array( 'https://example.com/search/', false ),
		);
	}

	/**
	 * Only an absolute http or https URL on an allowed host is redirected to.
	 *
	 * The authority comes from the configured origin, never from the request,
	 * so the guard checks the target's host against that origin directly.
	 *
	 * @param string $target   The URL the theme would redirect to.
	 * @param bool   $expected Whether it should be redirected to.
	 */
	#[DataProvider( 'targets' )]
	public function test_redirects_only_to_an_absolute_http_url( string $target, bool $expected ): void {
		$this->assertSame( $expected, is_valid_target_url( $target ) );
	}

	/**
	 * Every request the theme forwards is accepted and sent as it was built.
	 *
	 * Asserting the guard on its own only restates it. This walks the whole
	 * path, from request through the guard to the header `wp_redirect()` would
	 * send, so a target the guard accepts but core would mangle shows up here.
	 *
	 * @param string $request_uri The incoming request.
	 */
	#[DataProvider( 'every_request' )]
	public function test_a_forwarded_request_is_sent_unchanged( string $request_uri ): void {
		$_SERVER['REQUEST_URI'] = $request_uri;
		$target                 = get_target_url();

		$this->assertTrue( is_valid_target_url( $target ), "Refused to redirect to {$target}" );
		$this->assertSame(
			$target,
			wp_sanitize_redirect( $target ),
			"wp_redirect() would not have sent {$target} unchanged"
		);
	}

	/**
	 * Every request used anywhere in this file.
	 *
	 * @return array<string, array{string}>
	 */
	public static function every_request(): array {
		$requests = array();

		foreach ( self::requests() as $name => $case ) {
			$requests[ $name ] = array( $case[0] );
		}

		return array_merge( $requests, self::awkward_requests() );
	}

	/**
	 * Values the switch can hold, and whether each one means "on".
	 *
	 * @return array<string, array{mixed, bool}>
	 */
	public static function switch_values(): array {
		return array(
			'boolean true'   => array( true, true ),
			'boolean false'  => array( false, false ),
			'one'            => array( '1', true ),
			'zero'           => array( '0', false ),
			'empty string'   => array( '', false ),
			'the word true'  => array( 'true', true ),
			'the word false' => array( 'false', false ),
			'upper case'     => array( 'FALSE', false ),
		);
	}

	/**
	 * The switch reads the string forms `wp theme mod set` stores.
	 *
	 * The command stores its argument verbatim, so the setting can hold
	 * `'false'`, and a plain truthiness test would turn the redirect on for it.
	 *
	 * @param mixed $stored   What the theme mod holds.
	 * @param bool  $expected Whether the redirect should run.
	 */
	#[DataProvider( 'switch_values' )]
	public function test_reads_the_switch_as_it_was_written( $stored, bool $expected ): void {
		remove_filter( 'theme_mod_ov_is_redirect_enabled', '__return_true' );
		add_filter( 'theme_mod_ov_is_redirect_enabled', static fn() => $stored );

		$this->assertSame( $expected, is_redirect_enabled() );

		remove_all_filters( 'theme_mod_ov_is_redirect_enabled' );
		add_filter( 'theme_mod_ov_is_redirect_enabled', '__return_true' );
	}

	/**
	 * Origins the setting can hold, and the URL each one forwards a search to.
	 *
	 * @return array<string, array{string, ?string}>
	 */
	public static function origins(): array {
		return array(
			'plain'              => array( 'https://openverse.org', 'https://openverse.org/search/' ),
			'trailing slash'     => array( 'https://openverse.org/', 'https://openverse.org/search/' ),
			'http'               => array( 'http://openverse.org', 'http://openverse.org/search/' ),
			'explicit port'      => array( 'https://openverse.org:8443', 'https://openverse.org:8443/search/' ),
			'upper case scheme'  => array( 'HTTPS://openverse.org', null ),
			'no scheme'          => array( 'openverse.org', null ),
			'scheme relative'    => array( '//openverse.org', null ),
			'other scheme'       => array( 'ftp://openverse.org', null ),
		);
	}

	/**
	 * A configured origin either forwards to itself or is refused outright.
	 *
	 * The trailing-slash row matters because the Customizer strips one and
	 * `wp theme mod set` does not.
	 *
	 * @param string      $origin   The configured origin.
	 * @param string|null $expected The URL a search forwards to, or null when
	 *                              the theme should render the page instead.
	 */
	#[DataProvider( 'origins' )]
	public function test_forwards_only_to_a_usable_origin( string $origin, ?string $expected ): void {
		remove_filter( 'theme_mod_ov_redirect_url', array( $this, 'origin' ) );
		add_filter( 'theme_mod_ov_redirect_url', static fn() => $origin );
		$_SERVER['REQUEST_URI'] = '/openverse/search/';

		$target = get_target_url();
		$sent   = is_valid_target_url( $target ) ? $target : null;

		$this->assertSame( $expected, $sent );

		remove_all_filters( 'theme_mod_ov_redirect_url' );
		add_filter( 'theme_mod_ov_redirect_url', array( $this, 'origin' ) );
	}
}
