<?php
/**
 * Tests taking the global footer from wordpress.org's footer endpoint.
 *
 * @package WordPressTV
 */

declare( strict_types = 1 );

namespace WordPressTV\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function WordPressTV\GlobalFooter\extract_footer;

/**
 * Only the footer element makes it onto WordPress.tv pages.
 */
class Global_Footer_Test extends TestCase {

	/**
	 * Endpoint responses, with the markup each yields.
	 *
	 * @return array[]
	 */
	public static function data_responses(): array {
		$footer = '<footer class="global-footer wp-block-group"><nav aria-label="Footer"><ul><li><a href="https://wordpress.org/about/">About</a></li></ul></nav></footer>';

		return array(
			'footer and wp_footer() output' => array( "\n$footer\n<script id=\"wp-emoji-settings\" type=\"application/json\">{}</script>\n</body>\n</html>", $footer ),
			'error page'                    => array( '<html><head><title>403 Forbidden</title></head><body></body></html>', null ),
			'unclosed footer'               => array( '<footer class="global-footer">', null ),
		);
	}

	/**
	 * The footer element is kept, without the scripts and closing tags after it.
	 *
	 * @param string      $body     Response body.
	 * @param string|null $expected Footer markup, or null when the response has none.
	 * @return void
	 */
	#[DataProvider( 'data_responses' )]
	public function test_footer_is_extracted( string $body, ?string $expected ): void {
		$this->assertSame( $expected, extract_footer( $body ) );
	}
}
