<?php

namespace WordCamp\RemoteCSS;
defined( 'WPINC' ) or die();

class Test_Synchronize_Remote_CSS extends \WP_UnitTestCase {
	/**
	 * Test that the sanitized css matches a known good version
	 *
	 * @covers WordCamp\RemoteCSS\sanitize_and_save_unsafe_css()
	 */
	public function test_css_was_sanitized() {
		$unsanitized_css          = file_get_contents( __DIR__ . '/unsanitized.css' );
		$known_good_sanitized_css = file_get_contents( __DIR__ . '/sanitized.css'   );
		$maybe_sanitized_css      = sanitize_unsafe_css( $unsanitized_css );

		$this->assertEquals( $maybe_sanitized_css, $known_good_sanitized_css );
	}
}
