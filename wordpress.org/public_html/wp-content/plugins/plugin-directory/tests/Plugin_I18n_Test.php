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

	public function test_mark_gp_original_exact_match() {
		$result = $this->i18n->mark_gp_original( 1, 'Hello World', 'Hello World' );

		$this->assertSame( '___TRANSLATION_1___', $result );
	}

	public function test_mark_gp_original_trimmed_match() {
		$result = $this->i18n->mark_gp_original( 1, '  Hello World  ', 'Hello World' );

		$this->assertSame( '___TRANSLATION_1___', $result );
	}

	public function test_mark_gp_original_content_has_whitespace() {
		$result = $this->i18n->mark_gp_original( 1, 'Hello World', '  Hello World  ' );

		$this->assertSame( '  ___TRANSLATION_1___  ', $result );
	}

	public function test_mark_gp_original_plain_text() {
		$result = $this->i18n->mark_gp_original( 1, 'Hello', 'Say Hello to everyone' );

		$this->assertSame( 'Say ___TRANSLATION_1___ to everyone', $result );
	}

	public function test_mark_gp_original_html_tag_pair() {
		$result = $this->i18n->mark_gp_original( 1, 'Hello World', '<p>Hello World</p>' );

		$this->assertSame( '<p>___TRANSLATION_1___</p>', $result );
	}

	public function test_mark_gp_original_html_with_whitespace_inside_tags() {
		$result = $this->i18n->mark_gp_original( 1, 'Hello World', '<p>  Hello World  </p>' );

		$this->assertSame( '<p>___TRANSLATION_1___</p>', $result );
	}

	public function test_mark_gp_original_html_with_inline_elements() {
		$result = $this->i18n->mark_gp_original( 1, 'Use <code>wp-cli</code> to run', '<p>Use <code>wp-cli</code> to run</p>' );

		$this->assertSame( '<p>___TRANSLATION_1___</p>', $result );
	}

	public function test_mark_gp_original_no_match() {
		$content = '<p>Something completely different</p>';

		$this->assertSame( $content, $this->i18n->mark_gp_original( 1, 'Hello World', $content ) );
	}

	public function test_mark_gp_original_heading_tags() {
		$result = $this->i18n->mark_gp_original( 1, 'Plugin Settings', '<h3>Plugin Settings</h3>' );

		$this->assertSame( '<h3>___TRANSLATION_1___</h3>', $result );
	}

	public function test_mark_gp_original_multiple_tags() {
		$result = $this->i18n->mark_gp_original( 1, 'First paragraph', '<p>First paragraph</p><p>Second paragraph</p>' );

		$this->assertSame( '<p>___TRANSLATION_1___</p><p>Second paragraph</p>', $result );
	}

	public function test_mark_gp_original_regex_special_chars() {
		$result = $this->i18n->mark_gp_original( 1, 'Price is $10.00 (USD)', '<p>Price is $10.00 (USD)</p>' );

		$this->assertSame( '<p>___TRANSLATION_1___</p>', $result );
	}

	/**
	 * Issue #601: untrimmed <li> content in GlotPress should still match rendered content.
	 */
	public function test_mark_gp_original_issue_601_list_item_whitespace() {
		$content  = '<li>Choose your methods: Enable one or more authentication providers</li>';
		$original = ' Choose your methods: Enable one or more authentication providers ';
		$result   = $this->i18n->mark_gp_original( 1, $original, $content );

		$this->assertSame( '<li>___TRANSLATION_1___</li>', $result );
	}

	/**
	 * List items followed by a nested <ul> instead of </li>.
	 */
	public function test_mark_gp_original_li_followed_by_nested_ul() {
		$content = '<li>Parent item<ul><li>Child</li></ul></li>';
		$result  = $this->i18n->mark_gp_original( 1, 'Parent item', $content );

		$this->assertSame( '<li>___TRANSLATION_1___<ul><li>Child</li></ul></li>', $result );
	}

	/**
	 * List items followed by a nested <ol> instead of </li>.
	 */
	public function test_mark_gp_original_li_followed_by_nested_ol() {
		$content = '<li>Parent item<ol><li>Child</li></ol></li>';
		$result  = $this->i18n->mark_gp_original( 1, 'Parent item', $content );

		$this->assertSame( '<li>___TRANSLATION_1___<ol><li>Child</li></ol></li>', $result );
	}
}
