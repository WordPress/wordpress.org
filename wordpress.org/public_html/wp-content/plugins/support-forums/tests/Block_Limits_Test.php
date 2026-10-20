<?php
/**
 * Tests that forum content is reduced to the blocks the forums support.
 *
 * @package support-forums
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Forums\Blocks;

/**
 * Covers Blocks::limit_blocks(), Blocks::block_pre_render(), and the filters the
 * constructor registers for them.
 *
 * @group blocks
 */
#[Group( 'blocks' )]
class Block_Limits_Test extends TestCase {

	/**
	 * The instance under test.
	 *
	 * Built once for the class: the constructor registers around twenty
	 * filters, which would pile up if every test made its own.
	 *
	 * @var Blocks
	 */
	protected static Blocks $blocks;

	/**
	 * Filters a test added, removed again on tear down.
	 *
	 * @var array<int, array{string, callable}>
	 */
	protected array $added_filters = array();

	/**
	 * Build the shared instance.
	 *
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		self::$blocks = new Blocks();
	}

	/**
	 * Remove any filter a test registered.
	 *
	 * WP_UnitTestCase would roll these back; a plain TestCase does not.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->added_filters as $filter ) {
			remove_filter( $filter[0], $filter[1] );
		}
		$this->added_filters = array();

		parent::tearDown();
	}

	/**
	 * Add a filter and register it for removal on tear down.
	 *
	 * @param string   $hook     Filter name.
	 * @param callable $callback Filter callback.
	 * @return void
	 */
	protected function add_temporary_filter( string $hook, callable $callback ): void {
		add_filter( $hook, $callback );
		$this->added_filters[] = array( $hook, $callback );
	}

	/**
	 * The constructor wires up the filters every other test here bypasses.
	 *
	 * The behaviour tests call limit_blocks() and block_pre_render() directly,
	 * so dropping the registrations would leave them green while forum content
	 * went unfiltered. The priorities are part of the contract: 100 puts the
	 * limiter after bbPress' own content filters and before the content is
	 * stored, and 7 puts it before Blocks Everywhere renders at 8.
	 *
	 * @return void
	 */
	public function test_constructor_registers_the_content_filters(): void {
		foreach ( array( 'topic', 'reply', 'forum' ) as $type ) {
			$this->assertSame( 100, has_filter( "bbp_new_{$type}_pre_content", array( self::$blocks, 'limit_blocks' ) ) );
			$this->assertSame( 100, has_filter( "bbp_edit_{$type}_pre_content", array( self::$blocks, 'limit_blocks' ) ) );
			$this->assertSame( 7, has_filter( "bbp_get_{$type}_content", array( self::$blocks, 'limit_blocks' ) ) );
		}

		$this->assertSame( 10, has_filter( 'pre_render_block', array( self::$blocks, 'block_pre_render' ) ) );
	}

	/**
	 * Content the filter has no reason to touch is returned byte for byte.
	 *
	 * @param string $content Forum content.
	 * @return void
	 */
	#[DataProvider( 'data_untouched_content' )]
	public function test_leaves_content_it_need_not_change( string $content ): void {
		$this->assertSame( $content, self::$blocks->limit_blocks( $content ) );
	}

	/**
	 * Content that must survive unchanged.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_untouched_content(): array {
		return array(
			'plain text'      => array( 'Just a sentence, no blocks at all.' ),
			'empty string'    => array( '' ),
			'html, no blocks' => array( '<p>Hand written <strong>markup</strong>.</p>' ),
			'supported block' => array( "<!-- wp:paragraph -->\n<p>Hello.</p>\n<!-- /wp:paragraph -->" ),
			'supported list'  => array( "<!-- wp:list -->\n<ul><!-- wp:list-item -->\n<li>One</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->" ),
		);
	}

	/**
	 * An unsupported block is removed while its supported siblings stay.
	 *
	 * @return void
	 */
	public function test_removes_an_unsupported_block(): void {
		$content = "<!-- wp:paragraph -->\n<p>Kept.</p>\n<!-- /wp:paragraph -->\n"
			. "<!-- wp:html -->\n<script>alert(1)</script>\n<!-- /wp:html -->";

		$result = self::$blocks->limit_blocks( $content );

		$this->assertStringContainsString( 'Kept.', $result );
		$this->assertStringNotContainsString( 'wp:html', $result );
		$this->assertStringNotContainsString( '<script>', $result );
	}

	/**
	 * An unsupported block nested inside a supported one is removed too.
	 *
	 * @return void
	 */
	public function test_removes_a_nested_unsupported_block(): void {
		$content = "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">"
			. "<!-- wp:paragraph -->\n<p>Kept.</p>\n<!-- /wp:paragraph -->"
			. "<!-- wp:html -->\n<iframe src=\"https://example.com\"></iframe>\n<!-- /wp:html -->"
			. "</blockquote>\n<!-- /wp:quote -->";

		$result = self::$blocks->limit_blocks( $content );

		$this->assertStringNotContainsString( 'wp:html', $result );
		$this->assertStringNotContainsString( '<iframe', $result );
	}

	/**
	 * An unsupported block hidden behind an unbalanced delimiter cannot come back.
	 *
	 * Serializing a filtered tree can leave an unclosed delimiter as literal
	 * innerContent, which the filter does not see but a later parse does. The
	 * result has to be inert however many times it is re-parsed.
	 *
	 * @param string $content Forum content carrying an unbalanced delimiter.
	 * @return void
	 */
	#[DataProvider( 'data_unbalanced_delimiters' )]
	public function test_unsupported_block_does_not_survive_a_reparse( string $content ): void {
		$result = self::$blocks->limit_blocks( $content );

		$this->assertFalse(
			$this->has_unsupported_block( parse_blocks( $result ) ),
			'An unsupported block survived the first parse of the filtered content.'
		);
		$this->assertFalse(
			$this->has_unsupported_block( parse_blocks( self::$blocks->limit_blocks( $result ) ) ),
			'An unsupported block survived a second round trip.'
		);
	}

	/**
	 * Content whose delimiters do not pair up.
	 *
	 * @return array<string, array{string}>
	 */
	public static function data_unbalanced_delimiters(): array {
		return array(
			'opener with no closer'   => array( "<!-- wp:paragraph -->\n<p>Text.</p>\n<!-- /wp:paragraph -->\n<!-- wp:html -->" ),
			'closer with no opener'   => array( "<!-- /wp:html -->\n<!-- wp:paragraph -->\n<p>Text.</p>\n<!-- /wp:paragraph -->" ),
			'delimiter in inner html' => array( "<!-- wp:paragraph -->\n<p>&lt;!-- wp:html --&gt;</p>\n<!-- /wp:paragraph -->\n<!-- wp:html -->\n<span></span>\n<!-- /wp:html -->" ),
			'nested opener'           => array( "<!-- wp:quote -->\n<blockquote><!-- wp:html --></blockquote>\n<!-- /wp:quote -->" ),
		);
	}

	/**
	 * The render guard drops an unsupported block while a forum filter is running.
	 *
	 * @return void
	 */
	public function test_render_guard_drops_unsupported_block_in_forum_content(): void {
		$parsed = array( 'blockName' => 'core/html' );

		// Outside the bbPress content filters the guard leaves rendering alone.
		$this->assertNull( self::$blocks->block_pre_render( null, $parsed ) );

		$seen = null;
		$this->add_temporary_filter(
			'bbp_get_reply_content',
			function ( $content ) use ( &$seen, $parsed ) {
				$seen = self::$blocks->block_pre_render( null, $parsed );
				return $content;
			}
		);
		apply_filters( 'bbp_get_reply_content', 'content' );

		$this->assertSame( '', $seen, 'An unsupported block was allowed to render in reply content.' );
	}

	/**
	 * A supported block still renders while a forum filter is running.
	 *
	 * @return void
	 */
	public function test_render_guard_keeps_supported_block(): void {
		$seen = 'unset';
		$this->add_temporary_filter(
			'bbp_get_topic_content',
			function ( $content ) use ( &$seen ) {
				$seen = self::$blocks->block_pre_render( null, array( 'blockName' => 'core/paragraph' ) );
				return $content;
			}
		);
		apply_filters( 'bbp_get_topic_content', 'content' );

		$this->assertNull( $seen, 'A supported block was blocked from rendering.' );
	}

	/**
	 * Whether a parsed tree names a block the forums do not support.
	 *
	 * Mirrors the plugin's own private check so the assertions do not depend on
	 * how the filtered string happens to be serialized.
	 *
	 * @param array[] $blocks Parsed blocks.
	 * @return bool
	 */
	protected function has_unsupported_block( array $blocks ): bool {
		$supported = array( 'core/paragraph', 'core/list', 'core/list-item', 'core/code', 'core/quote', 'core/image', 'core/embed' );

		foreach ( $blocks as $block ) {
			if ( isset( $block['blockName'] ) && null !== $block['blockName'] && ! in_array( $block['blockName'], $supported, true ) ) {
				return true;
			}

			if ( ! empty( $block['innerBlocks'] ) && $this->has_unsupported_block( $block['innerBlocks'] ) ) {
				return true;
			}
		}

		return false;
	}
}
