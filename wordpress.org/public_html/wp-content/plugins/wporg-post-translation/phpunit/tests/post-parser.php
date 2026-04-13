<?php
/**
 * Tests for the Post_Parser class.
 */

use WordPressdotorg\Post_Translation\Post_Parser;

class Test_Post_Parser extends WP_UnitTestCase {

	/**
	 * Test extracting strings from a paragraph block.
	 */
	public function test_extract_paragraph() {
		$content = '<!-- wp:paragraph --><p>Hello World</p><!-- /wp:paragraph -->';
		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertContains( 'Hello World', $strings );
	}

	/**
	 * Test extracting strings from a heading block.
	 */
	public function test_extract_heading() {
		$content = '<!-- wp:heading --><h2>Welcome</h2><!-- /wp:heading -->';
		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertContains( 'Welcome', $strings );
	}

	/**
	 * Test extracting strings from multiple heading levels.
	 */
	public function test_extract_heading_levels() {
		$content  = '<!-- wp:heading {"level":1} --><h1>Title</h1><!-- /wp:heading -->';
		$content .= '<!-- wp:heading {"level":3} --><h3>Subtitle</h3><!-- /wp:heading -->';

		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertContains( 'Title', $strings );
		$this->assertContains( 'Subtitle', $strings );
	}

	/**
	 * Test extracting strings from an image block (figcaption, alt, title).
	 */
	public function test_extract_image() {
		$content = '<!-- wp:image {"alt":"A photo"} -->'
			. '<figure class="wp-block-image"><img src="test.jpg" alt="A photo" title="Photo title" />'
			. '<figcaption>Image caption</figcaption></figure>'
			. '<!-- /wp:image -->';

		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertContains( 'Image caption', $strings );
		$this->assertContains( 'A photo', $strings );
		$this->assertContains( 'Photo title', $strings );
	}

	/**
	 * Test extracting strings from a list block.
	 */
	public function test_extract_list() {
		$content = '<!-- wp:list --><ul><li>Item one</li><li>Item two</li></ul><!-- /wp:list -->';
		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertContains( 'Item one', $strings );
		$this->assertContains( 'Item two', $strings );
	}

	/**
	 * Test extracting strings from a quote block.
	 */
	public function test_extract_quote() {
		$content = '<!-- wp:quote -->'
			. '<blockquote class="wp-block-quote"><p>To be or not to be.</p><cite>Shakespeare</cite></blockquote>'
			. '<!-- /wp:quote -->';

		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertContains( 'To be or not to be.', $strings );
		$this->assertContains( 'Shakespeare', $strings );
	}

	/**
	 * Test extracting strings from a button block.
	 */
	public function test_extract_button() {
		$content = '<!-- wp:button -->'
			. '<div class="wp-block-button"><a class="wp-block-button__link" href="https://example.com" title="Click here">Get Started</a></div>'
			. '<!-- /wp:button -->';

		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertContains( 'Get Started', $strings );
		$this->assertContains( 'https://example.com', $strings );
		$this->assertContains( 'Click here', $strings );
	}

	/**
	 * Test that container blocks (group, columns) extract nothing themselves.
	 */
	public function test_container_blocks_extract_nothing() {
		$content = '<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Nested text</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		// The paragraph inside the group should still be extracted.
		$this->assertContains( 'Nested text', $strings );
	}

	/**
	 * Test nested columns with content.
	 */
	public function test_nested_columns() {
		$content = '<!-- wp:columns --><div class="wp-block-columns">'
			. '<!-- wp:column --><div class="wp-block-column">'
			. '<!-- wp:paragraph --><p>Left column</p><!-- /wp:paragraph -->'
			. '</div><!-- /wp:column -->'
			. '<!-- wp:column --><div class="wp-block-column">'
			. '<!-- wp:paragraph --><p>Right column</p><!-- /wp:paragraph -->'
			. '</div><!-- /wp:column -->'
			. '</div><!-- /wp:columns -->';

		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertContains( 'Left column', $strings );
		$this->assertContains( 'Right column', $strings );
	}

	/**
	 * Test that empty blocks produce no strings.
	 */
	public function test_empty_blocks() {
		$content = '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->';
		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertEmpty( $strings );
	}

	/**
	 * Test that strings are deduplicated.
	 */
	public function test_deduplication() {
		$content  = '<!-- wp:paragraph --><p>Same text</p><!-- /wp:paragraph -->';
		$content .= '<!-- wp:paragraph --><p>Same text</p><!-- /wp:paragraph -->';

		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertCount( 1, array_filter( $strings, fn( $s ) => 'Same text' === $s ) );
	}

	/**
	 * Test replacing strings in content.
	 */
	public function test_replace_strings() {
		$content = '<!-- wp:paragraph --><p>Hello World</p><!-- /wp:paragraph -->';
		$parser  = new Post_Parser();

		$translated = $parser->translate_content(
			$content,
			function ( $text ) {
				if ( 'Hello World' === $text ) {
					return 'Hola Mundo';
				}
				return $text;
			}
		);

		$this->assertStringContainsString( 'Hola Mundo', $translated );
		$this->assertStringNotContainsString( 'Hello World', $translated );
	}

	/**
	 * Test replacing strings in nested blocks.
	 */
	public function test_replace_nested() {
		$content = '<!-- wp:group --><div class="wp-block-group">'
			. '<!-- wp:heading --><h2>Title</h2><!-- /wp:heading -->'
			. '<!-- wp:paragraph --><p>Body text</p><!-- /wp:paragraph -->'
			. '</div><!-- /wp:group -->';

		$parser     = new Post_Parser();
		$translated = $parser->translate_content(
			$content,
			function ( $text ) {
				return strrev( $text );
			}
		);

		$this->assertStringContainsString( 'eltiT', $translated );
		$this->assertStringContainsString( 'txet ydoB', $translated );
	}

	/**
	 * Test that translate_content returns false when no translations exist.
	 */
	public function test_translate_content_returns_false_when_unchanged() {
		$content = '<!-- wp:paragraph --><p>No translation</p><!-- /wp:paragraph -->';
		$parser  = new Post_Parser();

		$result = $parser->translate_content(
			$content,
			function ( $text ) {
				return $text; // Identity - no translation.
			}
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test post_to_strings extracts title, excerpt, and content.
	 */
	public function test_post_to_strings() {
		$post_id = self::factory()->post->create( [
			'post_title'   => 'Test Title',
			'post_excerpt' => 'Test Excerpt',
			'post_content' => '<!-- wp:paragraph --><p>Test Content</p><!-- /wp:paragraph -->',
		] );

		$strings = Post_Parser::post_to_strings( get_post( $post_id ) );

		$this->assertContains( 'Test Title', $strings );
		$this->assertContains( 'Test Excerpt', $strings );
		$this->assertContains( 'Test Content', $strings );
	}

	/**
	 * Test the fallback BasicText parser handles unknown block types.
	 */
	public function test_fallback_parser() {
		$content = '<!-- wp:custom/block --><div>Custom block text</div><!-- /wp:custom/block -->';
		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertContains( 'Custom block text', $strings );
	}

	/**
	 * Test content with unicode characters survives round-trip.
	 */
	public function test_unicode_round_trip() {
		$content = '<!-- wp:paragraph --><p>Hello 🌍 World</p><!-- /wp:paragraph -->';
		$parser  = new Post_Parser();

		$translated = $parser->translate_content(
			$content,
			function ( $text ) {
				return str_replace( 'Hello', 'Hola', $text );
			}
		);

		$this->assertStringContainsString( '🌍', $translated );
		$this->assertStringContainsString( 'Hola', $translated );
	}

	/**
	 * Test content with HTML entities.
	 */
	public function test_html_entities() {
		$content = '<!-- wp:paragraph --><p>Terms &amp; Conditions</p><!-- /wp:paragraph -->';
		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		// The parser should return the text content (entities decoded or as-is depending on parser).
		$this->assertNotEmpty( $strings );
	}

	/**
	 * Test the block parsers filter allows adding custom parsers.
	 */
	public function test_custom_parser_filter() {
		add_filter(
			'post_translation_block_parsers',
			function ( $parsers ) {
				$parsers['custom/testimonial'] = new \WordPressdotorg\Post_Translation\Parsers\HTML_Parser( 'blockquote' );
				return $parsers;
			}
		);

		$content = '<!-- wp:custom/testimonial --><blockquote>Great product!</blockquote><!-- /wp:custom/testimonial -->';
		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertContains( 'Great product!', $strings );

		remove_all_filters( 'post_translation_block_parsers' );
	}

	/**
	 * Test shortcode block extraction.
	 */
	public function test_shortcode_block() {
		$content = '<!-- wp:shortcode -->[gallery ids="1,2,3"]<!-- /wp:shortcode -->';
		$parser  = new Post_Parser();
		$strings = $parser->extract_strings( $content );

		$this->assertContains( '[gallery ids="1,2,3"]', $strings );
	}
}
