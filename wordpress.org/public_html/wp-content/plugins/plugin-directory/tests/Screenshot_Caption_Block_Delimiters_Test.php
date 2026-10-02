<?php
/**
 * Tests that screenshot captions cannot contribute block grammar.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WordPressdotorg\Plugin_Directory\Readme\Parser;

require_once __DIR__ . '/fixtures/production-environment.php';

/**
 * A caption is display text. It travels from the readme into post meta and is
 * then composed into the Image block markup that Screenshots::display() hands
 * to do_blocks(), so it has to stay a text leaf of that block at both ends.
 *
 * @group shortcodes
 */
#[Group( 'shortcodes' )]
class Screenshot_Caption_Block_Delimiters_Test extends TestCase {

	/**
	 * A caption carrying block-comment syntax.
	 *
	 * @var string
	 */
	const CAPTION = 'Caption <!-- /wp:image --><!-- wp:post-navigation-link {"type":"next","label":"x"} /--><!-- wp:image -->';

	/**
	 * The parser keeps comment syntax out of the stored caption.
	 */
	public function test_parser_drops_comments_from_screenshot_captions(): void {
		$readme = implode(
			"\n",
			array(
				'=== Test Plugin ===',
				'Contributors: testuser',
				'Tags: testing',
				'Tested up to: 6.9',
				'Stable tag: 1.0.0',
				'',
				'Short description.',
				'',
				'== Screenshots ==',
				'',
				'1. ' . self::CAPTION,
				'',
			)
		);

		$file = wp_tempnam( 'readme.txt' );
		file_put_contents( $file, $readme ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture, no filesystem abstraction in play.

		try {
			$parser = new Parser( $file );
		} finally {
			wp_delete_file( $file );
		}

		$this->assertArrayHasKey( 1, $parser->screenshots );
		$this->assertStringNotContainsString( '<!--', $parser->screenshots[1] );
		$this->assertStringNotContainsString( '-->', $parser->screenshots[1] );
		$this->assertStringContainsString( 'Caption', $parser->screenshots[1] );
	}

	/**
	 * A caption already stored with comment syntax stays inside its own block.
	 */
	public function test_stored_caption_cannot_open_a_sibling_block(): void {
		$method  = new ReflectionMethod( Screenshots::class, 'build_image_block' );
		$post_id = wp_insert_post(
			array(
				'post_name'         => 'caption-delimiters',
				'post_title'        => 'Caption Delimiters',
				'post_type'         => 'plugin',
				'post_status'       => 'publish',
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', true ),
			)
		);

		setup_postdata( get_post( $post_id ) );

		try {
			$markup = $method->invoke(
				null,
				array(
					'src'      => 'https://ps.w.org/caption-delimiters/assets/screenshot-1.png',
					'filename' => 'screenshot-1.png',
					'caption'  => self::CAPTION,
				),
				9000002,
				true,
				array( 1200, 800 )
			);
		} finally {
			wp_reset_postdata();
			wp_delete_post( $post_id, true );
		}

		$names = array_values(
			array_filter(
				wp_list_pluck( parse_blocks( $markup ), 'blockName' )
			)
		);

		$this->assertSame( array( 'core/image' ), $names );
	}
}
