<?php
/**
 * Tests for Plugin_Share_Image_Layout.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Plugin_Share_Image_Layout;

/**
 * @group share-image
 */
class Plugin_Share_Image_Layout_Test extends TestCase {

	public function test_canvas_dimensions() {
		$this->assertSame( 1200, Plugin_Share_Image_Layout::CANVAS_WIDTH );
		$this->assertSame( 630, Plugin_Share_Image_Layout::CANVAS_HEIGHT );
		$this->assertSame( 4, Plugin_Share_Image_Layout::STAT_COLUMNS );
	}

	public function test_resolve_returns_expected_zones() {
		$layout = Plugin_Share_Image_Layout::resolve();

		$this->assertSame( 1200, $layout['canvas']['width'] );
		$this->assertSame( 630, $layout['canvas']['height'] );
		$this->assertSame( 72, $layout['zones']['content']['x'] );
		$this->assertSame( 128, $layout['zones']['plugin_icon']['size'] );
		$this->assertSame( 26, $layout['type']['stat']['icon_size'] );
		$this->assertSame( 26, $layout['type']['stat']['value_size'] );
		$this->assertSame( 32, $layout['type']['stat']['icon_slot'] );
		$this->assertSame( 8, $layout['type']['stat']['icon_gap'] );
	}

	public function test_content_width_avoids_plugin_icon() {
		$layout = Plugin_Share_Image_Layout::resolve();

		$icon_zone    = $layout['zones']['plugin_icon'];
		$content_zone = $layout['zones']['content'];

		$this->assertLessThan(
			$icon_zone['x'],
			$content_zone['x'] + $content_zone['width']
		);
	}

	public function test_stat_columns_are_equal_width() {
		$layout = Plugin_Share_Image_Layout::resolve();
		$stats  = $layout['zones']['stats'];

		$first_x  = Plugin_Share_Image_Layout::stat_column_x( $layout, 0 );
		$second_x = Plugin_Share_Image_Layout::stat_column_x( $layout, 1 );
		$third_x  = Plugin_Share_Image_Layout::stat_column_x( $layout, 2 );
		$fourth_x = Plugin_Share_Image_Layout::stat_column_x( $layout, 3 );

		$this->assertSame( $stats['x'], $first_x );
		$this->assertSame( $first_x + $stats['column_width'], $second_x );
		$this->assertSame( $second_x + $stats['column_width'], $third_x );
		$this->assertSame( $third_x + $stats['column_width'], $fourth_x );
	}

	public function test_branding_center_y_aligns_with_stat_value_row() {
		$layout = Plugin_Share_Image_Layout::resolve();

		$this->assertSame(
			$layout['zones']['stats']['value_y'] - 18,
			$layout['zones']['branding']['center_y']
		);
	}
}
