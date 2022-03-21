<?php
/**
 * Utility functions for colors.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class ColorUtils {

	/**
	 * The base or primary colors we're most interested in as the tentpoles for
	 * nearest color calculations
	 *
	 * @var array
	 */
	const COLORS = [
		'red'    => [ 'hex' => '#FF0000' ],
		'orange' => [ 'hex' => '#FFA500' ],
		'yellow' => [ 'hex' => '#FFFF00' ],
		'green'  => [ 'hex' => '#008000' ],
		'blue'   => [ 'hex' => '#0000FF' ],
		'violet' => [ 'hex' => '#EE82EE' ],
		'brown'  => [ 'hex' => '#A52A2A' ],
		'black'  => [ 'hex' => '#000000' ],
		'gray'   => [ 'hex' => '#808080' ],
		'white'  => [ 'hex' => '#FFFFFF' ],
		'pink'   => [ 'hex' => '#FFC0CB' ],
	];

	/**
	 * Memoized value of COLORS with RGB and HSL values calculated.
	 *
	 * @access protected
	 * @var array
	 */
	protected static $base_colors_with_values = [];

	/**
	 * Converts RGB color value to HSL.
	 *
	 * @link https://gist.github.com/brandonheyer/5254516
	 *
	 * @param int $r The red RGB value for a color.
	 * @param int $g The green RGB value for a color.
	 * @param int $b The blue RGB value for a color.
	 * @return array {
	 *     The HSL values for the color
	 *
	 *     @type float $0 The hue (H) value for a color.
	 *     @type float $1 The saturation (S) value for a color.
	 *     @type float $2 The lightness (L) value for a color.
	 * }
	 */
	public static function rgb_to_hsl( $r, $g, $b ) {
		$oldR = $r;
		$oldG = $g;
		$oldB = $b;

		$r /= 255;
		$g /= 255;
		$b /= 255;

		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );

		$h = null;
		$s = null;
		$l = ( $max + $min ) / 2;
		$d = $max - $min;

		if ( $d == 0 ) {
			$h = $s = 0; // achromatic
		} else {
			$s = $d / ( 1 - abs( 2 * $l - 1 ) );

			switch ( $max ) {
				case $r:
					$h = 60 * fmod( ( ( $g - $b ) / $d ), 6 );
					if ( $b > $g ) {
						$h += 360;
					}
					break;

				case $g:
					$h = 60 * ( ( $b - $r ) / $d + 2 );
					break;

				case $b:
					$h = 60 * ( ( $r - $g ) / $d + 4 );
					break;
			}
		}

		return [ round( $h, 2 ), round( $s, 2 ), round( $l, 2 ) ];
	}

	/**
	 * Converts RGB color values to hexadecimal color string.
	 *
	 * @param int $r The red RGB value for a color.
	 * @param int $g The green RGB value for a color.
	 * @param int $b The blue RGB value for a color.
	 * @return string The hexadecimal value of the color (includes '#' character).
	 */
	public static function rgb_to_hex( $r, $g, $b ) {
		return strtoupper( sprintf( "#%02x%02x%02x", $r, $g, $b ) );
	}

	/**
	 * Converts hexadecimal color string to RGB values.
	 *
	 * @param string $hex Color in full hexadecimal notation, e.g. #FF0055.
	 * @return array {
	 *     The RGB values for the color.
	 *
	 *     @type int $0 The red RGB value for a color.
	 *     @type int $1 The green RGB value for a color.
	 *     @type int $2 The blue RGB value for a color.
	 * }
	 */
	public static function hex_to_rgb( $hex ) {
		return [
			hexdec( substr( $hex, 1, 2 ) ),
			hexdec( substr( $hex, 3, 2 ) ),
			hexdec( substr( $hex, 5, 2 ) ),
		];
	}

	/**
	 * Returns the list of base colors and their RGB and HSL values.
	 *
	 * @return array {
	 *     Associative array of colors with associated color information.
	 *
	 *     @type float $r The red RGB value for the color.
	 *     @type float $g The green RGB value for the color.
	 *     @type float $b The blue RGB value for the color.
	 *     @type float $h The hue HSL value for the color.
	 *     @type float $h The saturation HSL value for the color.
	 *     @type float $h The lightness HSL value for the color.
	 * }
	 */
	public static function get_base_colors() {
		if ( self::$base_colors_with_values ) {
			return self::$base_colors_with_values;
		}

		$colors = self::COLORS;

		// Get the r, g, b, and h, s, l values for each color.
		foreach ( $colors as $color_name => $color_info ) {
			list( $r, $g, $b ) = self::hex_to_rgb( $color_info['hex'] );
			list( $h, $s, $l ) = self::rgb_to_hsl( $r, $g, $b );

			$color_info = array_merge( $color_info, [
				'r' => $r,
				'g' => $g,
				'b' => $b,
				'h' => $h,
				's' => $s,
				'l' => $l,
			] );

			$colors[ $color_name ] = $color_info;
		}

		return self::$base_colors_with_values = $colors;
	}

	/**
	 * Returns the nearest base color for a given color.
	 *
	 * @link https://github.com/dtao/nearest-color/blob/master/nearestColor.js
	 *
	 * @param int|string $r The red RGB value for a color, or a hex color code that includes the preceding '#'.
	 * @param int        $g Optional. The green RGB value for a color if first argument isn't a hex string.
	 * @param int        $b Optional. The blue RGB value for a color if first argument isn't a hex string.
	 * @return string Nearest color's name or "unknown" if not known.
	 */
	public static function get_nearest_color( $r, $g = '', $b = '' ) {
		$colors = self::get_base_colors();

		if ( strval( $r )[0] === '#' ) {
			$hex = $r;
			list( $r, $g, $b ) = self::hex_to_rgb( $hex );
		} else {
			$hex = self::rgb_to_hex( $r, $g, $b );
		}

		list( $h, $s, $l ) = self::rgb_to_hsl( $r, $g, $b );

		$df = -1;
		$found = null;

		foreach ( $colors as $color_name => $color_info ) {
			if ( $hex === $color_info['hex'] ) {
				return $color_name;
			}

			$rgb_distance_sq = pow( $r - $color_info['r'], 2)
				+ pow( $g - $color_info['g'], 2)
				+ pow( $b - $color_info['b'], 2);

			$hsl_distance_sq = abs( pow( $h - $color_info['h'], 2 ) )
				+ pow( $s - $color_info['s'], 2 )
				+ abs( pow( $l - $color_info['l'], 2 ) );

			$combined_distance = $rgb_distance_sq + $hsl_distance_sq * 255;

			if ( $df < 0 || $df > $combined_distance ) {
				$df = $combined_distance;
				$found = $color_name;
			}
		}

		return $found ?? 'unknown';
	}

}