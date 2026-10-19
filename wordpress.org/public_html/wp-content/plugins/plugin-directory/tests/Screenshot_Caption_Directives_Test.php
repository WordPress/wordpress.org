<?php
/**
 * Tests that a screenshot caption cannot carry Interactivity directives.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

require_once __DIR__ . '/fixtures/production-environment.php';

/**
 * Every screenshot renders as a lightbox Image block, so the page carries the
 * Interactivity runtime and a `data-wp-` attribute on a caption would rewrite
 * its own tag once hydrated. A caption is display text.
 *
 * @group shortcodes
 */
#[Group( 'shortcodes' )]
class Screenshot_Caption_Directives_Test extends TestCase {

	/**
	 * A caption whose anchor would bind its own `href` after hydration.
	 *
	 * @var string
	 */
	const CAPTION = '<a data-wp-interactive="core/image" data-wp-context=\'{"target":"javascript:alert(1)"}\' data-wp-bind--href="context.target" href="#">Open screenshot details</a>';

	/**
	 * A caption reaches the block as display markup, with no directives left on it.
	 */
	public function test_caption_directives_are_dropped(): void {
		$method  = new ReflectionMethod( Screenshots::class, 'build_image_block' );
		$post_id = wp_insert_post(
			array(
				'post_name'         => 'caption-directives',
				'post_title'        => 'Caption Directives',
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
					'src'      => 'https://ps.w.org/caption-directives/assets/screenshot-1.png',
					'filename' => 'screenshot-1.png',
					'caption'  => self::CAPTION,
				),
				9000003,
				true,
				array( 1200, 800 )
			);
		} finally {
			wp_reset_postdata();
			wp_delete_post( $post_id, true );
		}

		$this->assertStringNotContainsString( 'data-wp-', $markup );
		$this->assertStringNotContainsString( 'javascript:', $markup );
		$this->assertStringContainsString( '>Open screenshot details</a>', $markup );
	}
}
