<?php
/**
 * Tests for URL headers in WordPress.org's plugin readme parser.
 *
 * Verifies that URL-bearing headers (`Donate link:`, `License URI:`, and the
 * URL embedded in `License:`) accept both the bare form
 *     Donate link: https://example.com
 * and the markdown autolink form
 *     Donate link: <https://example.com>
 *
 * The autolink form is what `markdownlint --fix` produces against bare URLs
 * in a README.md, so accepting it lets plugin authors lint their README.md
 * without breaking the wp.org plugin page.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use WordPressdotorg\Plugin_Directory\Readme\Parser;

class TestReadmeParserUrls extends WP_UnitTestCase {

	/**
	 * Build a minimal readme with `$header_line` inserted into the field
	 * block. The fixture omits whichever URL-bearing header is under test
	 * so the assertion isn't fighting an unrelated default.
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

	public static function donate_link_provider(): array {
		$url = 'https://example.com/donate';
		return array(
			'bare URL'             => array( "Donate link: $url", $url ),
			'autolink'             => array( "Donate link: <$url>", $url ),
			'autolink with spaces' => array( "Donate link:   <$url>   ", $url ),
		);
	}

	/**
	 * @dataProvider donate_link_provider
	 */
	public function test_donate_link( string $header, string $expected ): void {
		$parser = new Parser( self::readme_with( $header ) );
		$this->assertSame( $expected, $parser->donate_link );
	}

	public static function license_uri_provider(): array {
		$url = 'https://www.gnu.org/licenses/gpl-2.0.html';
		return array(
			'bare URL'             => array( "License URI: $url", $url ),
			'autolink'             => array( "License URI: <$url>", $url ),
			'autolink with spaces' => array( "License URI:   <$url>   ", $url ),
		);
	}

	/**
	 * @dataProvider license_uri_provider
	 */
	public function test_license_uri( string $header, string $expected ): void {
		$parser = new Parser( self::readme_with( $header ) );
		$this->assertSame( $expected, $parser->license_uri );
	}

	/**
	 * `License: GPLv2 - https://...` and `License: GPLv2 - <https://...>` should
	 * both extract the URL into `license_uri` and leave only `GPLv2` in
	 * `license` — no leftover `<` from the autolink form.
	 *
	 * @dataProvider license_with_embedded_url_provider
	 */
	public function test_license_with_embedded_url( string $header, string $expected_license, string $expected_uri ): void {
		$parser = new Parser( self::readme_with( $header ) );
		$this->assertSame( $expected_license, $parser->license );
		$this->assertSame( $expected_uri, $parser->license_uri );
	}

	public static function license_with_embedded_url_provider(): array {
		$url = 'https://www.gnu.org/licenses/gpl-2.0.html';
		return array(
			'bare URL'     => array( "License: GPLv2 - $url", 'GPLv2', $url ),
			'autolink URL' => array( "License: GPLv2 - <$url>", 'GPLv2', $url ),
		);
	}
}
