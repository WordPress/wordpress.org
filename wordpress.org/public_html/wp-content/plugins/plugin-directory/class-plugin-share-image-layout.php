<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 * Layout constants for plugin share images.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Share_Image_Layout {

	public const CANVAS_WIDTH  = 1200;
	public const CANVAS_HEIGHT = 630;

	public const STAT_COLUMNS = 4;

	private const SPEC = array(
		'margin_x'         => 72,
		'margin_top'       => 72,
		'footer_height'    => 16,
		'icon_size'        => 128,
		'icon_gap'         => 48,
		'logo_reserve'     => 108,
		'logo_size'        => 56,
		'title_size'       => 40,
		'title_line'       => 52,
		'title_max'        => 2,
		'description_size' => 22,
		'description_line' => 34,
		'description_max'  => 3,
		'description_gap'  => 20,
		'stat_icon_size'   => 26,
		'stat_value_size'  => 26,
		'stat_label_size'  => 14,
		'stat_icon_gap'    => 8,
		'stat_icon_slot'   => 32,
		'colors'           => array(
			'title'        => array( 30, 30, 30 ),
			'description'  => array( 80, 87, 94 ),
			'stat_icon'    => array( 113, 116, 127 ),
			'stat_value'   => array( 50, 55, 60 ),
			'stat_label'   => array( 113, 116, 127 ),
			'icon_surface' => array( 246, 247, 247 ),
		),
	);

	/**
	 * Resolve layout measurements for the canvas.
	 *
	 * @return array<string, mixed>
	 */
	public static function resolve() {
		$margin_x     = self::SPEC['margin_x'];
		$margin_top   = self::SPEC['margin_top'];
		$footer_h     = self::SPEC['footer_height'];
		$icon_size    = self::SPEC['icon_size'];
		$icon_gap     = self::SPEC['icon_gap'];
		$logo_reserve = self::SPEC['logo_reserve'];

		$icon_x        = self::CANVAS_WIDTH - $margin_x - $icon_size;
		$content_width = $icon_x - $icon_gap - $margin_x;
		$stats_right   = self::CANVAS_WIDTH - $margin_x - $logo_reserve;
		$stats_width   = $stats_right - $margin_x;
		$column_width  = (int) floor( $stats_width / self::STAT_COLUMNS );

		$stats_value_y = self::CANVAS_HEIGHT - $footer_h - 78;
		$stats_label_y = self::CANVAS_HEIGHT - $footer_h - 38;

		return array(
			'canvas'          => array(
				'width'  => self::CANVAS_WIDTH,
				'height' => self::CANVAS_HEIGHT,
			),
			'colors'          => self::SPEC['colors'],
			'type'            => array(
				'title'       => array(
					'size'        => self::SPEC['title_size'],
					'line_height' => self::SPEC['title_line'],
					'max_lines'   => self::SPEC['title_max'],
				),
				'description' => array(
					'size'        => self::SPEC['description_size'],
					'line_height' => self::SPEC['description_line'],
					'max_lines'   => self::SPEC['description_max'],
				),
				'stat'        => array(
					'icon_size'  => self::SPEC['stat_icon_size'],
					'value_size' => self::SPEC['stat_value_size'],
					'label_size' => self::SPEC['stat_label_size'],
					'icon_gap'   => self::SPEC['stat_icon_gap'],
					'icon_slot'  => self::SPEC['stat_icon_slot'],
				),
			),
			'zones'           => array(
				'content'     => array(
					'x'     => $margin_x,
					'y'     => $margin_top,
					'width' => $content_width,
				),
				'plugin_icon' => array(
					'x'    => $icon_x,
					'y'    => $margin_top,
					'size' => $icon_size,
				),
				'stats'       => array(
					'x'            => $margin_x,
					'column_width' => $column_width,
					'value_y'      => $stats_value_y,
					'label_y'      => $stats_label_y,
				),
				'branding'    => array(
					'center_y' => $stats_value_y - 18,
					'size'     => self::SPEC['logo_size'],
				),
				'footer'      => array(
					'y'      => self::CANVAS_HEIGHT - $footer_h,
					'height' => $footer_h,
				),
			),
			'title'           => array(
				'x' => $margin_x,
				'y' => $margin_top + 44,
			),
			'description_gap' => self::SPEC['description_gap'],
		);
	}

	/**
	 * Get the X origin for a stat column index.
	 *
	 * @param array<string, mixed> $layout Resolved layout.
	 * @param int                  $index  Zero-based column index.
	 * @return int
	 */
	public static function stat_column_x( $layout, $index ) {
		$stats = $layout['zones']['stats'];

		return $stats['x'] + ( $index * $stats['column_width'] );
	}
}
