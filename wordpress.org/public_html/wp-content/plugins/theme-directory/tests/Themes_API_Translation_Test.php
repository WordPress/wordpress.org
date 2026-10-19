<?php
/**
 * Tests that a translated theme header is held to the header's markup boundary.
 *
 * A translation replaces the Name or Description for every consumer of the API,
 * the localised directory pages among them.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `Themes_API::sanitize_translation()`.
 *
 * @group themes-api
 */
#[Group( 'themes-api' )]
class Themes_API_Translation_Test extends TestCase {

	/**
	 * Data provider for {@see test_translation_matches_the_stored_header()}.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function translation_provider(): array {
		return array(
			'markup dropped'              => array(
				'Mon Thème <script id="x">alert(1)</script><em>joli</em>',
				'Mon Thème joli',
			),
			'event attribute dropped'     => array(
				'<strong onmouseover="alert(1)">joli</strong>',
				'joli',
			),
			'shortcode delimiters inert'  => array(
				'Un thème [gallery] joli',
				'Un thème &#91;gallery&#93; joli',
			),
			'entities are not re-encoded' => array(
				'Th&egrave;me &amp; style',
				'Th&egrave;me &amp; style',
			),
		);
	}

	/**
	 * Every translated header is plain text with inert shortcode delimiters, as the
	 * import leaves the English value.
	 *
	 * @param string $translation The translation as GlotPress stored it.
	 * @param string $expected    The value the API may return.
	 */
	#[DataProvider( 'translation_provider' )]
	public function test_translation_matches_the_stored_header( string $translation, string $expected ): void {
		$this->assertSame( $expected, Themes_API::sanitize_translation( $translation ) );
	}
}
