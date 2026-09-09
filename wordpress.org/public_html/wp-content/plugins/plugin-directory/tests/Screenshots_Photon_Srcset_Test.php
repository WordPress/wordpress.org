<?php
/**
 * Tests for screenshot image sources.
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
 * Tests that screenshot markup avoids Photon resize candidates.
 *
 * @group shortcodes
 */
#[Group( 'shortcodes' )]
class Screenshots_Photon_Srcset_Test extends TestCase {

	/**
	 * Screenshot markup keeps the revision-aware source without a Photon srcset.
	 */
	public function test_image_block_uses_direct_source_without_photon_srcset(): void {
		$source  = 'https://ps.w.org/srcset-regression/assets/screenshot-1.png?rev=123';
		$method  = new ReflectionMethod( Screenshots::class, 'build_image_block' );
		$post_id = wp_insert_post(
			array(
				'post_name'         => 'srcset-regression',
				'post_title'        => 'Srcset Regression',
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
					'src'      => $source,
					'filename' => 'screenshot-1.png',
					'revision' => 123,
				),
				9000001,
				true,
				array( 1200, 800 )
			);
		} finally {
			wp_reset_postdata();
			wp_delete_post( $post_id, true );
		}

		$this->assertStringContainsString( 'src="' . $source . '"', $markup );
		$this->assertStringNotContainsString( 'srcset=', $markup );
		$this->assertStringNotContainsString( 'i0.wp.com', $markup );
	}
}
