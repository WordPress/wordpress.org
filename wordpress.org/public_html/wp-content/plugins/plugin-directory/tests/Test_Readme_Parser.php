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
 * @group readme-parser
 */
class Test_Readme_Parser extends TestCase {

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
	 * `Donate link:` accepts the bare form and the markdown autolink form
	 * (which `markdownlint --fix` produces against bare URLs).
	 */
	#[DataProvider( 'donate_link_provider' )]
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
	 * `License URI:` accepts the bare form and the markdown autolink form.
	 */
	#[DataProvider( 'license_uri_provider' )]
	public function test_license_uri( string $header, string $expected ): void {
		$parser = new Parser( self::readme_with( $header ) );
		$this->assertSame( $expected, $parser->license_uri );
	}

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
	 */
	#[DataProvider( 'license_with_embedded_url_provider' )]
	public function test_license_with_embedded_url( string $header, string $expected_license, string $expected_uri ): void {
		$parser = new Parser( self::readme_with( $header ) );
		$this->assertSame( $expected_license, $parser->license );
		$this->assertSame( $expected_uri, $parser->license_uri );
	}
}
