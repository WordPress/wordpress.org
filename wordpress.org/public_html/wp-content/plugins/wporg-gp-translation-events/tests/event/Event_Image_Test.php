<?php

use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Routes\Event\Image_Route;
use Wporg\TranslationEvents\Templates;
use Wporg\TranslationEvents\Tests\Event_Factory;
use Wporg\TranslationEvents\Urls;

class Event_Image_Test extends TestCase {
	/**
	 * Test that when the header template is fired, it generates the social metadata.
	 *
	 * @return void
	 */
	public function test_gp_head_metadata() {
		$html_title       = 'Test HTML Title';
		$url              = Urls::events_home();
		$html_description = 'This is a test description of the page.';
		$image_url        = Urls::event_default_image();
		$page_title       = 'Test Page Title';
		ob_start();
		Templates::header(
			array(
				'html_title'       => $html_title,
				'url'              => $url,
				'html_description' => $html_description,
				'image_url'        => $image_url,
				'page_title'       => $page_title,
			),
		);
		$output = ob_get_clean();

		$this->assertStringContainsString( '<meta name="twitter:card" content="summary" />', $output );
		$this->assertStringContainsString( '<meta name="twitter:site" content="@WordPress" />', $output );
		$this->assertStringContainsString( '<meta name="twitter:title" content="' . $html_title . '" />', $output );
		$this->assertStringContainsString( '<meta name="twitter:description" content="' . $html_description . '" />', $output );
		$this->assertStringContainsString( '<meta name="twitter:image" content="' . $image_url . '" />', $output );
		$this->assertStringContainsString( '<meta name="twitter:image:alt" content="' . $html_title . '" />', $output );

		$this->assertStringContainsString( '<meta property="og:url" content="' . $url . '" />', $output );
		$this->assertStringContainsString( '<meta property="og:title" content="' . $html_title . '" />', $output );
		$this->assertStringContainsString( '<meta property="og:description" content="' . $html_description . '" />', $output );
		$this->assertStringContainsString( '<meta property="og:site_name" content="' . esc_attr( get_bloginfo() ) . '" />', $output );
		$this->assertStringContainsString( '<meta property="og:image:url" content="' . $image_url . '" />', $output );
		$this->assertStringContainsString( '<meta property="og:image:secure_url" content="' . $image_url . '" />', $output );
		$this->assertStringContainsString( '<meta property="og:image:type" content="image/png" />', $output );
		$this->assertStringContainsString( '<meta property="og:image:width" content="1200" />', $output );
		$this->assertStringContainsString( '<meta property="og:image:height" content="675" />', $output );
		$this->assertStringContainsString( '<meta property="og:image:alt" content="' . $html_title . '" />', $output );
	}

	/**
	 * Test that the event image route generates a valid PNG image.
	 *
	 * @return void
	 */
	public function test_handle_generates_image() {
		// phpcs:disable
		error_reporting( 0 );
		// phpcs:enable
		ob_start();
		$this->event_factory = new Event_Factory();
		$event_id            = $this->event_factory->create_active( $this->now );

		$image_route = new Image_Route();
		$image_route->handle( $event_id );

		$output = ob_get_clean();

		// Verify the output is not empty and is a valid PNG image.
		$this->assertNotEmpty( $output );
		$this->assertStringStartsWith( "\x89PNG", $output, 'The output is not a valid PNG image' );

		// Create an image resource from the output.
		$image = imagecreatefromstring( $output );
		$this->assertNotFalse( $image, 'Failed to create image from output' );

		// Check the image dimensions.
		$width  = imagesx( $image );
		$height = imagesy( $image );
		$this->assertEquals( 1200, $width, 'Image width is not 1200 pixels' );
		$this->assertEquals( 675, $height, 'Image height is not 675 pixels' );

		// Verify the background color (35, 40, 45).
		$bg_color = imagecolorat( $image, 0, 0 );
		$bg_rgb   = imagecolorsforindex( $image, $bg_color );
		$this->assertEquals( 35, $bg_rgb['red'], 'Background red color is not 35' );
		$this->assertEquals( 40, $bg_rgb['green'], 'Background green color is not 40' );
		$this->assertEquals( 45, $bg_rgb['blue'], 'Background blue color is not 45' );

		// Check if the text color exists in the image.
		$text_color        = imagecolorallocate( $image, 255, 255, 255 );
		$text_color_exists = false;
		for ( $x = 0; $x < $width; $x++ ) {
			for ( $y = 0; $y < $height; $y++ ) {
				if ( imagecolorat( $image, $x, $y ) === $text_color ) {
					$text_color_exists = true;
					break 2;
				}
			}
		}
		$this->assertTrue( $text_color_exists, 'Text color not found in the image' );
	}
}
