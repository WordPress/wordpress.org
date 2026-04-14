<?php
/**
 * Tests for Plugin_I18n::mark_gp_original().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Plugin_I18n;

/**
 * @group i18n
 */
class Plugin_I18n_Test extends TestCase {

	/**
	 * @var Plugin_I18n
	 */
	protected $i18n;

	protected function setUp(): void {
		parent::setUp();

		// Use reflection to create an instance without the constructor (which requires cache globals).
		$reflection = new \ReflectionClass( Plugin_I18n::class );
		$this->i18n = $reflection->newInstanceWithoutConstructor();
	}

	/**
	 * Test exact match replaces the entire content.
	 */
	public function test_mark_gp_original_exact_match() {
		$result = $this->i18n->mark_gp_original( 1, 'Hello World', 'Hello World' );

		$this->assertSame( '___TRANSLATION_1___', $result );
	}

	/**
	 * Test exact match with leading/trailing whitespace differences.
	 */
	public function test_mark_gp_original_trimmed_match() {
		$result = $this->i18n->mark_gp_original( 1, '  Hello World  ', 'Hello World' );

		$this->assertSame( '___TRANSLATION_1___', $result );
	}

	/**
	 * Test exact match where content has whitespace but original is trimmed.
	 */
	public function test_mark_gp_original_content_has_whitespace() {
		$result = $this->i18n->mark_gp_original( 1, 'Hello World', '  Hello World  ' );

		$this->assertSame( '___TRANSLATION_1___', $result );
	}

	/**
	 * Test plain text word boundary match (no HTML).
	 */
	public function test_mark_gp_original_plain_text() {
		$result = $this->i18n->mark_gp_original( 1, 'Hello', 'Say Hello to everyone' );

		$this->assertSame( 'Say ___TRANSLATION_1___ to everyone', $result );
	}

	/**
	 * Test standard HTML tag pair match: <tag>ORIGINAL</tag>.
	 */
	public function test_mark_gp_original_html_tag_pair() {
		$content = '<p>Hello World</p>';
		$result  = $this->i18n->mark_gp_original( 1, 'Hello World', $content );

		$this->assertSame( '<p>___TRANSLATION_1___</p>', $result );
	}

	/**
	 * Test HTML match with whitespace inside tags.
	 */
	public function test_mark_gp_original_html_with_whitespace_inside_tags() {
		$content = '<p>  Hello World  </p>';
		$result  = $this->i18n->mark_gp_original( 1, 'Hello World', $content );

		$this->assertSame( '<p>___TRANSLATION_1___</p>', $result );
	}

	/**
	 * Test nested HTML elements: <li><p>ORIGINAL</p></li>.
	 */
	public function test_mark_gp_original_nested_html_elements() {
		$content = '<li><p>Choose your methods</p></li>';
		$result  = $this->i18n->mark_gp_original( 1, 'Choose your methods', $content );

		$this->assertSame( '<li><p>___TRANSLATION_1___</p></li>', $result );
	}

	/**
	 * Test nested HTML elements with whitespace.
	 */
	public function test_mark_gp_original_nested_html_with_whitespace() {
		$content = "<li>\n  <p> Choose your methods </p>\n</li>";
		$result  = $this->i18n->mark_gp_original( 1, 'Choose your methods', $content );

		$this->assertSame( "<li>\n  <p>___TRANSLATION_1___</p>\n</li>", $result );
	}

	/**
	 * Test matching with normalized whitespace in plain text.
	 */
	public function test_mark_gp_original_normalized_whitespace_plain_text() {
		$content = 'Hello   World';
		$result  = $this->i18n->mark_gp_original( 1, 'Hello World', $content );

		$this->assertSame( '___TRANSLATION_1___', $result );
	}

	/**
	 * Test HTML content with inline elements preserved in original.
	 */
	public function test_mark_gp_original_html_with_inline_elements() {
		$content = '<p>Use <code>wp-cli</code> to run</p>';
		$result  = $this->i18n->mark_gp_original( 1, 'Use <code>wp-cli</code> to run', $content );

		$this->assertSame( '<p>___TRANSLATION_1___</p>', $result );
	}

	/**
	 * Test that non-matching content is returned unchanged.
	 */
	public function test_mark_gp_original_no_match() {
		$content = '<p>Something completely different</p>';
		$result  = $this->i18n->mark_gp_original( 1, 'Hello World', $content );

		$this->assertSame( $content, $result );
	}

	/**
	 * Test heading tags (h3, h4) match correctly.
	 */
	public function test_mark_gp_original_heading_tags() {
		$content = '<h3>Plugin Settings</h3>';
		$result  = $this->i18n->mark_gp_original( 1, 'Plugin Settings', $content );

		$this->assertSame( '<h3>___TRANSLATION_1___</h3>', $result );
	}

	/**
	 * Test multiple tag pairs in content - only exact inner content matches.
	 */
	public function test_mark_gp_original_multiple_tags() {
		$content = '<p>First paragraph</p><p>Second paragraph</p>';
		$result  = $this->i18n->mark_gp_original( 1, 'First paragraph', $content );

		$this->assertSame( '<p>___TRANSLATION_1___</p><p>Second paragraph</p>', $result );
	}

	/**
	 * Test that the original with HTML special characters (regex metacharacters) is properly escaped.
	 */
	public function test_mark_gp_original_regex_special_chars() {
		$content = '<p>Price is $10.00 (USD)</p>';
		$result  = $this->i18n->mark_gp_original( 1, 'Price is $10.00 (USD)', $content );

		$this->assertSame( '<p>___TRANSLATION_1___</p>', $result );
	}

	/**
	 * Test whitespace normalization helper.
	 */
	public function test_normalize_whitespace() {
		$this->assertSame( 'Hello World', $this->i18n->normalize_whitespace( '  Hello   World  ' ) );
		$this->assertSame( 'a b c', $this->i18n->normalize_whitespace( "a\n\tb\r\nc" ) );
		$this->assertSame( 'single', $this->i18n->normalize_whitespace( 'single' ) );
	}

	/**
	 * Reproduce the specific issue from GitHub issue #601:
	 * List items with content that has leading/trailing whitespace not matching.
	 */
	public function test_mark_gp_original_issue_601_list_item_whitespace() {
		// Simulates a <li> where the GlotPress original was not trimmed but the rendered content is slightly different.
		$content  = '<li>Choose your methods: Enable one or more authentication providers</li>';
		$original = 'Choose your methods: Enable one or more authentication providers';
		$result   = $this->i18n->mark_gp_original( 1, $original, $content );

		$this->assertSame( '<li>___TRANSLATION_1___</li>', $result );
	}

	/**
	 * Reproduce issue #601: list item wrapped in <p> tags.
	 */
	public function test_mark_gp_original_issue_601_li_with_p_wrapper() {
		$content  = '<li><p>Plugin settings: The plugin provides a settings page under Settings</p></li>';
		$original = 'Plugin settings: The plugin provides a settings page under Settings';
		$result   = $this->i18n->mark_gp_original( 1, $original, $content );

		$this->assertSame( '<li><p>___TRANSLATION_1___</p></li>', $result );
	}
}
