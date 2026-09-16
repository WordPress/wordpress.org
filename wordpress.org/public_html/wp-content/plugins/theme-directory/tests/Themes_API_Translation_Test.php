<?php
/**
 * Tests that a translated theme header is held to the header's markup boundary.
 *
 * A translation replaces the sanitized Name or Description for every consumer of
 * the API, the localised directory pages among them.
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
	 * Data provider for {@see test_plain_text_fields_drop_all_markup()}.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function plain_text_field_provider(): array {
		return array(
			'name'        => array( 'name' ),
			'description' => array( 'description' ),
		);
	}

	/**
	 * The Name and the flattened Description are plain text by the time they are returned.
	 *
	 * @param string $field The field being translated.
	 */
	#[DataProvider( 'plain_text_field_provider' )]
	public function test_plain_text_fields_drop_all_markup( string $field ): void {
		$translation = 'Mon Thème <script id="x">alert(1)</script><em>joli</em>';

		$this->assertSame( 'Mon Thème joli', Themes_API::sanitize_translation( $field, $translation ) );
	}

	/**
	 * Data provider for {@see test_description_section_keeps_header_markup_only()}.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function description_section_provider(): array {
		return array(
			'script dropped'              => array(
				'Un thème <script>alert(1)</script>joli',
				'Un thème alert(1)joli',
			),
			'event attribute dropped'     => array(
				'<strong onmouseover="alert(1)">joli</strong>',
				'<strong>joli</strong>',
			),
			'directive dropped'           => array(
				'<a href="https://example.org/" data-wp-bind--href="context.t">doc</a>',
				'<a href="https://example.org/">doc</a>',
			),
			'header markup survives'      => array(
				'Un thème <strong>joli</strong>, voir la <a href="https://example.org/" title="doc">doc</a>.',
				'Un thème <strong>joli</strong>, voir la <a href="https://example.org/" title="doc">doc</a>.',
			),
			'entities are not re-encoded' => array(
				'Th&egrave;me &amp; style',
				'Th&egrave;me &amp; style',
			),
		);
	}

	/**
	 * The Description section carries the markup a style.css header may carry.
	 *
	 * @param string $translation The translation as GlotPress stored it.
	 * @param string $expected    The value the API may return.
	 */
	#[DataProvider( 'description_section_provider' )]
	public function test_description_section_keeps_header_markup_only( string $translation, string $expected ): void {
		$this->assertSame( $expected, Themes_API::sanitize_translation( 'sections/description', $translation ) );
	}
}
