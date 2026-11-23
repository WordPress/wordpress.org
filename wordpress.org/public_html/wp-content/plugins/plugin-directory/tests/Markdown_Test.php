<?php
/**
 * Tests for the readme Markdown renderer.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Markdown;

/**
 * Covers how a `<` that doesn't open a real tag renders, and that real HTML still does.
 *
 * @group readme
 */
#[Group( 'readme' )]
class Markdown_Test extends TestCase {

	/**
	 * A `<` with no `>` anywhere after it used to exhaust the PCRE JIT stack on long
	 * sections, which made the plugin import fatal.
	 */
	public function test_stray_angle_bracket_in_long_section(): void {
		$text = "* Minimal footprint (<35KB total scripts).\n\n" . str_repeat( "Plenty of description text.\n", 1000 ) . 'The end.';

		$html = ( new Markdown() )->transform( $text );

		$this->assertStringContainsString( 'Minimal footprint (&lt;35KB total scripts).', $html );
		$this->assertStringEndsWith( 'The end.</p>', $html );
	}

	/**
	 * A stray `<` must not swallow text up to the next tag, which kept the heading after it
	 * from being treated as a block.
	 */
	public function test_stray_angle_bracket_before_heading(): void {
		$html = ( new Markdown() )->transform( "* No longer needed for <PHP7\n\n= 1.0 =\n\n* Initial release" );

		$this->assertStringContainsString( 'needed for &lt;PHP7', $html );
		$this->assertStringContainsString( "\n<h4>1.0</h4>\n", $html );
		$this->assertStringNotContainsString( '<p><h4>', $html );
	}

	/**
	 * Inline tags stay inline.
	 */
	public function test_inline_html_is_kept(): void {
		$html = ( new Markdown() )->transform( 'Some <span class="note">inline</span> and <a href="https://example.org/">linked</a> text.' );

		$this->assertSame( '<p>Some <span class="note">inline</span> and <a href="https://example.org/">linked</a> text.</p>', $html );
	}

	/**
	 * Block tags are still passed through without Markdown applied inside them.
	 */
	public function test_block_html_is_kept(): void {
		$html = ( new Markdown() )->transform( "<div>\n*not emphasis*\n</div>\n\n*emphasis*" );

		$this->assertSame( "<div>\n*not emphasis*\n</div>\n\n<p><em>emphasis</em></p>", $html );
	}
}
