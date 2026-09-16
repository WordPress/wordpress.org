<?php
/**
 * Tests that a GlotPress translation is held to its own field's markup boundary.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Plugin_I18n;

/**
 * A translation replaces the value the readme parser sanitized at import, and is
 * then displayed wherever that value was.
 *
 * @group i18n
 */
#[Group( 'i18n' )]
class Plugin_I18n_Sanitization_Test extends TestCase {

	/**
	 * The translator under test.
	 *
	 * @var Plugin_I18n
	 */
	protected $i18n;

	/**
	 * Builds the translator without its constructor, which needs cache globals.
	 */
	protected function setUp(): void {
		parent::setUp();

		$reflection = new \ReflectionClass( Plugin_I18n::class );
		$this->i18n = $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * Data provider for {@see test_markup_fields_keep_section_markup_only()}.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function markup_field_provider(): array {
		return array(
			'script in a section'               => array(
				'description',
				'Bonjour <script>alert(1)</script>',
				'Bonjour alert(1)',
			),
			'directives in a section'           => array(
				'faq',
				'<a data-wp-interactive="core/image" data-wp-context=\'{"t":"javascript:alert(1)"}\' data-wp-bind--href="context.t" href="#">Détails</a>',
				'<a href="#">Détails</a>',
			),
			'directives in a caption'           => array(
				'screenshot-1',
				'<a data-wp-bind--href="context.t" href="#">Légende</a>',
				'<a href="#">Légende</a>',
			),
			'event attribute in a caption'      => array(
				'screenshot-2',
				'<strong onmouseover="alert(1)">Légende</strong>',
				'<strong>Légende</strong>',
			),
			'comment syntax in a caption'       => array(
				'screenshot-3',
				'Légende <!-- /wp:image --><!-- wp:image -->',
				'Légende',
			),
			'section markup survives unchanged' => array(
				'changelog',
				'<h4>1.0</h4><ul><li>Correction d\'un <code>bug</code>, voir la <a href="https://example.org/" rel="nofollow">doc</a>.</li></ul>',
				'<h4>1.0</h4><ul><li>Correction d\'un <code>bug</code>, voir la <a href="https://example.org/" rel="nofollow">doc</a>.</li></ul>',
			),
		);
	}

	/**
	 * Sections and screenshot captions carry readme markup and nothing else.
	 *
	 * @param string $key         The translation key the field translates under.
	 * @param string $translation The translation as GlotPress stored it.
	 * @param string $expected    The value the field may display.
	 */
	#[DataProvider( 'markup_field_provider' )]
	public function test_markup_fields_keep_section_markup_only( string $key, string $translation, string $expected ): void {
		$this->assertSame( $expected, $this->i18n->sanitize_translation( $key, $translation ) );
	}

	/**
	 * Data provider for {@see test_plain_text_fields_drop_all_markup()}.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function plain_text_field_provider(): array {
		return array(
			'title'       => array( 'title' ),
			'excerpt'     => array( 'excerpt' ),
			'block title' => array( 'block_title:' . md5( 'Block' ) ),
		);
	}

	/**
	 * Title, short description and block titles are plain text in every consumer.
	 *
	 * @param string $key The translation key the field translates under.
	 */
	#[DataProvider( 'plain_text_field_provider' )]
	public function test_plain_text_fields_drop_all_markup( string $key ): void {
		$translation = 'Mon Plugin <script>alert(1)</script><a href="#" data-wp-bind--href="context.t">x</a>';

		$this->assertSame( 'Mon Plugin x', $this->i18n->sanitize_translation( $key, $translation ) );
		$this->assertStringNotContainsString( 'alert', $this->i18n->sanitize_translation( $key, $translation ) );
	}

	/**
	 * The title and short description are stored entity-encoded, so a translation is too.
	 *
	 * A block title is not: it is stored as the block declared it.
	 */
	public function test_plain_text_fields_match_their_stored_encoding(): void {
		$this->assertSame( 'Tom &amp; Jerry &lt; Co', $this->i18n->sanitize_translation( 'title', 'Tom & Jerry < Co' ) );
		$this->assertSame( 'Tom &amp; Jerry', $this->i18n->sanitize_translation( 'excerpt', 'Tom &amp; Jerry' ) );
		$this->assertSame( 'Tom & Jerry', $this->i18n->sanitize_translation( 'block_title:' . md5( 'B' ), 'Tom & Jerry' ) );
	}

	/**
	 * A stored marker cannot pick its own substitution site.
	 *
	 * `title` is on the readme's allow-list, so a readme can store marker syntax
	 * inside an attribute and aim a translation at a context it would survive.
	 */
	public function test_a_stored_marker_is_not_a_substitution_site(): void {
		$stored = '<a href="https://example.org/" title="___TRANSLATION_7___">Docs</a>';

		$this->assertSame(
			'<a href="https://example.org/" title="">Docs</a>',
			$this->i18n->translate_marked_gp_originals(
				Plugin_I18n::remove_translation_markers( $stored ),
				array( 7 => '" onmouseover="alert(1)' ),
				array( 7 => 'Docs' )
			)
		);
	}

	/**
	 * A marker split across a marker does not survive the one that wraps it.
	 */
	public function test_a_marker_cannot_be_spliced_from_the_text_around_one(): void {
		$crafted = '___TRAN___TRANSLATION_1___SLATION_7___';

		$this->assertSame( '', Plugin_I18n::remove_translation_markers( $crafted ) );
	}

	/**
	 * Sanitizing the assembled field catches what a substitution opened up.
	 */
	public function test_assembled_field_drops_an_attribute_a_substitution_opened(): void {
		$assembled = '<a href="https://example.org/" title="" onmouseover="alert(1)">Docs</a>';

		$this->assertSame(
			'<a href="https://example.org/" title="">Docs</a>',
			$this->i18n->sanitize_translation( 'description', $assembled )
		);
	}
}
