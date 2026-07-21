<?php
/**
 * Tests for WordPress.org's plugin readme parser.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Readme\Parser;

/**
 * Exercises Parser end-to-end against fixture readmes, asserting that
 * the URL-bearing headers come out clean for both the bare and markdown
 * autolink forms.
 *
 * @group readme-parser
 */
class Test_Readme_Parser extends TestCase {

	/**
	 * Build a minimal readme with the given header line inserted into the
	 * field block. The fixture omits whichever URL-bearing header is under
	 * test so the assertion isn't fighting an unrelated default.
	 *
	 * @param string $header_line A `Field: Value` line to insert.
	 * @return string Complete readme contents.
	 */
	private static function readme_with( string $header_line ): string {
		return implode(
			"\n",
			array(
				'=== Test Plugin ===',
				'Contributors: testuser',
				'Tags: testing',
				'Tested up to: 6.9',
				'Stable tag: 1.0.0',
				$header_line,
				'',
				'Short description.',
				'',
				'== Description ==',
				'',
				'Body.',
				'',
			)
		);
	}

	/**
	 * Data provider for {@see test_donate_link()}.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function donate_link_provider(): array {
		$url = 'https://example.com/donate';
		return array(
			'bare URL'             => array( "Donate link: $url", $url ),
			'autolink'             => array( "Donate link: <$url>", $url ),
			'autolink with spaces' => array( "Donate link:   <$url>   ", $url ),
		);
	}

	/**
	 * `Donate link:` accepts the bare form and the markdown autolink form
	 * (which `markdownlint --fix` produces against bare URLs).
	 *
	 * @param string $header   Full header line under test.
	 * @param string $expected Expected `donate_link` value after parsing.
	 */
	#[DataProvider( 'donate_link_provider' )]
	public function test_donate_link( string $header, string $expected ): void {
		$parser = new Parser( self::readme_with( $header ) );
		$this->assertSame( $expected, $parser->donate_link );
	}

	/**
	 * Data provider for {@see test_license_uri()}.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function license_uri_provider(): array {
		$url = 'https://www.gnu.org/licenses/gpl-2.0.html';
		return array(
			'bare URL'             => array( "License URI: $url", $url ),
			'autolink'             => array( "License URI: <$url>", $url ),
			'autolink with spaces' => array( "License URI:   <$url>   ", $url ),
		);
	}

	/**
	 * `License URI:` accepts the bare form and the markdown autolink form.
	 *
	 * @param string $header   Full header line under test.
	 * @param string $expected Expected `license_uri` value after parsing.
	 */
	#[DataProvider( 'license_uri_provider' )]
	public function test_license_uri( string $header, string $expected ): void {
		$parser = new Parser( self::readme_with( $header ) );
		$this->assertSame( $expected, $parser->license_uri );
	}

	/**
	 * Data provider for {@see test_license_with_embedded_url()}.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function license_with_embedded_url_provider(): array {
		$url = 'https://www.gnu.org/licenses/gpl-2.0.html';
		return array(
			'bare URL'      => array( "License: GPLv2 - $url", 'GPLv2', $url ),
			'autolink URL'  => array( "License: GPLv2 - <$url>", 'GPLv2', $url ),
			'parens around' => array( "License: GPLv2 - ($url)", 'GPLv2', $url ),
		);
	}

	/**
	 * `License: GPLv2 - http://...` and the wrapped forms `<http://...>` and
	 * `(http://...)` should all extract the URL into `license_uri` and leave
	 * only `GPLv2` in `license` — no leftover bracket or paren.
	 *
	 * @param string $header           Full header line under test.
	 * @param string $expected_license Expected residual `license` value.
	 * @param string $expected_uri     Expected `license_uri` extracted from the line.
	 */
	#[DataProvider( 'license_with_embedded_url_provider' )]
	public function test_license_with_embedded_url( string $header, string $expected_license, string $expected_uri ): void {
		$parser = new Parser( self::readme_with( $header ) );
		$this->assertSame( $expected_license, $parser->license );
		$this->assertSame( $expected_uri, $parser->license_uri );
	}

	/**
	 * Data provider for {@see test_license_is_sanitized()}.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function license_markup_provider(): array {
		return array(
			'image payload'          => array( 'License: GPLv2 <img src=x onerror=alert(document.domain)>' ),
			'script payload'         => array( 'License: GPLv2 <script>alert(1)</script>' ),
			'payload containing url' => array( 'License: GPLv2 <img src=https://example.com/x onerror=alert(1)>' ),
			'unclosed tag'           => array( 'License: GPLv2 <img src=x onerror=alert(1)' ),
		);
	}

	/**
	 * A `License:` value that contains a GPL-compatible token passes validation, so it
	 * gets reported back to reviewers verbatim. It must not carry markup with it.
	 *
	 * @param string $header Full header line under test.
	 */
	#[DataProvider( 'license_markup_provider' )]
	public function test_license_is_sanitized( string $header ): void {
		$parser = new Parser( self::readme_with( $header ) );

		$this->assertStringStartsWith( 'GPLv2', $parser->license );
		$this->assertStringNotContainsString( '<', $parser->license );
		$this->assertStringNotContainsString( 'onerror', $parser->license );
	}
}
