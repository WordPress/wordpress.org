<?php

use WordPressdotorg\Plugin_Directory\Markdown;

/**
 * @group plugin-directory
 * @group markdown
 */
class Tests_Markdown extends WP_UnitTestCase {

	function test_transform_basic_paragraph(): void {
		$md     = new Markdown();
		$result = $md->transform( 'Hello World' );
		$this->assertSame( '<p>Hello World</p>', $result );
	}

	function test_transform_empty_string(): void {
		$md     = new Markdown();
		$result = $md->transform( '' );
		$this->assertSame( '', $result );
	}

	function test_transform_trims_output(): void {
		$md     = new Markdown();
		$result = $md->transform( "  \n  Hello  \n  " );
		$this->assertSame( $result, trim( $result ) );
	}

	/**
	 * Test the custom `= Section Title =` header syntax.
	 *
	 * This is WordPress plugin readme specific — converts `= Title =` to <h4>.
	 */
	function test_transform_equals_header(): void {
		$md     = new Markdown();
		$result = $md->transform( '= Section Title =' );
		$this->assertStringContainsString( '<h4>Section Title</h4>', $result );
	}

	function test_transform_multiple_equals_headers(): void {
		$md     = new Markdown();
		$result = $md->transform( "= First =\n\nContent\n\n= Second =" );
		$this->assertStringContainsString( '<h4>First</h4>', $result );
		$this->assertStringContainsString( '<h4>Second</h4>', $result );
	}

	function test_equals_header_with_leading_whitespace(): void {
		$md     = new Markdown();
		$result = $md->transform( '  = Indented Header =' );
		$this->assertStringContainsString( '<h4>Indented Header</h4>', $result );
	}

	/**
	 * Test code_trick: <pre><code> blocks preserve underscores in code.
	 *
	 * This is custom logic in Markdown::code_trick() — pre-existing HTML code blocks
	 * are converted to backtick format before markdown processing, so markdown
	 * does not mangle underscores and other special characters inside code.
	 *
	 * The block needs surrounding content so trim() does not strip indentation.
	 */
	function test_code_trick_preserves_underscores_in_pre_code(): void {
		$md    = new Markdown();
		$input = "Some text before.\n\n<pre><code>\$my_var = some_function();\n\$other_var = 1;</code></pre>\n\nSome text after.";
		$result = $md->transform( $input );

		// Underscores should NOT be converted to <em> tags inside code blocks.
		$this->assertStringNotContainsString( '<em>', $result );
		$this->assertStringContainsString( 'my_var', $result );
		$this->assertStringContainsString( 'some_function', $result );
	}

	function test_code_trick_inline_code_preserves_underscores(): void {
		$md     = new Markdown();
		$input  = "Use <code>my_var_name</code> for the setting.";
		$result = $md->transform( $input );

		// Inline code should also preserve underscores.
		$this->assertStringNotContainsString( '<em>', $result );
		$this->assertStringContainsString( 'my_var_name', $result );
	}

	/**
	 * Test code_trick: bbPress-style backtick code blocks at line start are
	 * converted to indented code (4 spaces) for markdown processing.
	 */
	function test_code_trick_bbpress_backtick_block(): void {
		$md     = new Markdown();
		$input  = "Some text.\n\n`some_code_here`\n\nMore text.";
		$result = $md->transform( $input );

		$this->assertStringContainsString( 'some_code_here', $result );
	}

	/**
	 * Test that inline markdown code (backticks) in mid-line is preserved.
	 */
	function test_inline_backtick_code_preserved(): void {
		$md     = new Markdown();
		$result = $md->transform( 'Use `add_filter()` to modify output.' );
		$this->assertStringContainsString( '<code>add_filter()</code>', $result );
	}

	/**
	 * Test standard markdown features (these verify the upstream MarkdownExtra
	 * library works correctly through our transform() wrapper).
	 */
	function test_transform_bold(): void {
		$md     = new Markdown();
		$result = $md->transform( '**bold text**' );
		$this->assertStringContainsString( '<strong>bold text</strong>', $result );
	}

	function test_transform_italic(): void {
		$md     = new Markdown();
		$result = $md->transform( '*italic text*' );
		$this->assertStringContainsString( '<em>italic text</em>', $result );
	}

	function test_transform_link(): void {
		$md     = new Markdown();
		$result = $md->transform( '[Example](https://example.com)' );
		$this->assertStringContainsString( '<a href="https://example.com">Example</a>', $result );
	}

	function test_transform_unordered_list(): void {
		$md     = new Markdown();
		$result = $md->transform( "* Item 1\n* Item 2\n* Item 3" );
		$this->assertStringContainsString( '<li>Item 1</li>', $result );
		$this->assertStringContainsString( '<ul>', $result );
	}
}
