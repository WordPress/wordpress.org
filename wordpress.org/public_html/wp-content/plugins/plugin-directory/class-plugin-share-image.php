<?php
/**
 * Generates dynamic social share images for plugin pages.
 *
 * @package WordPressdotorg\Plugin_Directory
 */

namespace WordPressdotorg\Plugin_Directory;

/**
 * JPEG renderer for plugin Open Graph images.
 *
 * Stats, icons, and install formatting come from Template. This class paints
 * a 1200x630 card and serves it with a content-hashed URL so CDNs can cache
 * for a year without pinning stale title/install/rating data.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Share_Image {

	const WIDTH  = 1200;
	const HEIGHT = 630;

	/**
	 * Bumped when the painted layout changes so year-cached URLs get a new token.
	 */
	const LAYOUT = 2;

	/**
	 * Whether GD and the Inter font are available.
	 *
	 * @return bool
	 */
	public static function can_render() {
		static $can = null;

		if ( null === $can ) {
			$can = function_exists( 'imagecreatetruecolor' ) && (bool) self::font_path();
		}

		return $can;
	}

	/**
	 * Public share-image URL, or false when the image cannot be served.
	 *
	 * Closed/disabled plugins are public post statuses but the route 404s them,
	 * so they must not emit og:image. A missing GD/Inter install also returns
	 * false so plugin pages fall back to banner tags instead of a 500 URL.
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object.
	 * @return string|false
	 */
	public static function get_url( $post = null ) {
		$plugin = get_post( $post );

		if ( ! $plugin || 'plugin' !== $plugin->post_type || 'publish' !== $plugin->post_status ) {
			return false;
		}

		if ( ! self::can_render() ) {
			return false;
		}

		return home_url( '/share-image/' . $plugin->post_name . '_' . self::cache_token( $plugin ) . '.jpg' );
	}

	/**
	 * Collect the fields drawn onto the card.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return array{title: string, description: string, icon_url: string, stats: array}|null
	 */
	public static function get_data( $plugin ) {
		$plugin = get_post( $plugin );

		if ( ! $plugin || 'plugin' !== $plugin->post_type || 'publish' !== $plugin->post_status ) {
			return null;
		}

		$icons = Template::get_plugin_icon( $plugin );
		$icon  = $icons['icon_2x'] ?: $icons['icon'] ?: '';

		if ( ! empty( $icons['generated'] ) || str_ends_with( (string) $icon, '.svg' ) ) {
			$icon = '';
		}

		return array(
			'title'       => get_the_title( $plugin ),
			'description' => wp_strip_all_tags( get_the_excerpt( $plugin ) ),
			'icon_url'    => $icon,
			'stats'       => self::stats( $plugin ),
		);
	}

	/**
	 * Render a JPEG share image for a plugin.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return string|false JPEG bytes, or false on failure.
	 */
	public static function render( $plugin ) {
		$result = self::paint( $plugin );

		return $result ? $result['bytes'] : false;
	}

	/**
	 * Output HTTP headers and JPEG body for a plugin share image.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return bool Whether output was sent.
	 */
	public static function output( $plugin ) {
		$result = self::paint( $plugin );

		if ( ! $result ) {
			return false;
		}

		status_header( 200 );
		header( 'Content-Type: image/jpeg' );
		header( 'Cache-Control: public, max-age=' . $result['max_age'] );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + $result['max_age'] ) );
		echo $result['bytes']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return true;
	}

	/**
	 * Cache-buster embedded in the URL (slug + token, same idea as geopattern).
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return string
	 */
	protected static function cache_token( $plugin ) {
		$last_updated = get_post_meta( $plugin->ID, 'last_updated', true ) ?: $plugin->post_modified_gmt;

		return substr(
			md5(
				implode(
					'|',
					array(
						self::LAYOUT,
						$last_updated,
						(int) get_post_meta( $plugin->ID, 'active_installs', true ),
						(string) self::rating( $plugin ),
						get_locale(),
					)
				)
			),
			0,
			8
		);
	}

	/**
	 * Paint the card and return bytes plus cache TTL.
	 *
	 * A missing icon is fine and stays year-cached. A *failed* icon fetch is
	 * not: that JPEG would otherwise pin an icon-less card at CDNs for a year.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return array{bytes: string, max_age: int}|false
	 */
	protected static function paint( $plugin ) {
		if ( ! self::can_render() ) {
			return false;
		}

		$data = self::get_data( $plugin );
		if ( ! $data ) {
			return false;
		}

		$image = imagecreatetruecolor( self::WIDTH, self::HEIGHT );
		if ( ! $image ) {
			return false;
		}

		$font = self::font_path();
		if ( ! $font ) {
			imagedestroy( $image );
			return false;
		}

		$margin        = 72;
		$icon_size     = 128;
		$footer_h      = 16;
		$icon_x        = self::WIDTH - $margin - $icon_size;
		$content_width = $icon_x - 48 - $margin;
		$title_y       = $margin + 44;
		$stats_value_y = self::HEIGHT - $footer_h - 78;
		$stats_label_y = self::HEIGHT - $footer_h - 38;
		$column_width  = (int) floor( ( self::WIDTH - ( 2 * $margin ) - 108 ) / 4 );

		$white   = imagecolorallocate( $image, 255, 255, 255 );
		$dark    = imagecolorallocate( $image, 30, 30, 30 );
		$muted   = imagecolorallocate( $image, 80, 87, 94 );
		$value   = imagecolorallocate( $image, 50, 55, 60 );
		$label   = imagecolorallocate( $image, 113, 116, 127 );
		$surface = imagecolorallocate( $image, 246, 247, 247 );

		imagefilledrectangle( $image, 0, 0, self::WIDTH, self::HEIGHT, $white );
		self::draw_footer( $image, $footer_h );

		$title_lines = self::wrap_text( $data['title'], $content_width, 40, $font, 2 );
		self::draw_lines( $image, $title_lines, $margin, $title_y, 40, 52, $font, $dark, $content_width, true );

		$desc_y     = $title_y + ( count( $title_lines ) * 52 ) + 20;
		$desc_lines = self::wrap_text( $data['description'], $content_width, 22, $font, 3 );
		self::draw_lines( $image, $desc_lines, $margin, $desc_y, 22, 34, $font, $muted, $content_width );

		foreach ( $data['stats'] as $index => $stat ) {
			if ( $index >= 4 ) {
				break;
			}
			$x          = $margin + ( $index * $column_width );
			$cell       = max( 0, $column_width - 16 );
			$value_text = self::truncate_text( $stat['value'], $cell, 26, $font );
			$label_text = self::truncate_text( $stat['label'], $cell, 14, $font );
			self::draw_string( $image, $value_text, $x, $stats_value_y, 26, $font, $value, true );
			self::draw_string( $image, $label_text, $x, $stats_label_y, 14, $font, $label );
		}

		$icon_ok = self::draw_icon( $image, $data['icon_url'], $icon_x, $margin, $icon_size, $surface );
		self::draw_logo( $image, $stats_value_y - 18, $margin );

		ob_start();
		imagejpeg( $image, null, 88 );
		$bytes = ob_get_clean();
		imagedestroy( $image );

		if ( ! $bytes ) {
			return false;
		}

		return array(
			'bytes'   => $bytes,
			'max_age' => ( $data['icon_url'] && ! $icon_ok ) ? 5 * MINUTE_IN_SECONDS : YEAR_IN_SECONDS,
		);
	}

	/**
	 * Stat cells for the footer row, using directory helpers.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return array<int, array{value: string, label: string}>
	 */
	protected static function stats( $plugin ) {
		$items = array();

		$contributors = count( Template::get_plugin_contributors( $plugin ) );
		$items[]      = array(
			'value' => number_format_i18n( $contributors ),
			'label' => _n( 'Contributor', 'Contributors', $contributors, 'wporg-plugins' ),
		);

		$locales = Template::count_plugin_locales( $plugin );
		if ( $locales > 0 ) {
			$items[] = array(
				'value' => number_format_i18n( $locales ),
				'label' => _n( 'Locale', 'Locales', $locales, 'wporg-plugins' ),
			);
		}

		$rating = self::rating( $plugin );
		if ( $rating > 0 ) {
			$items[] = array(
				'value' => number_format_i18n( $rating, 1 ),
				'label' => __( 'Rating', 'wporg-plugins' ),
			);
		}

		$installs = (int) get_post_meta( $plugin->ID, 'active_installs', true );
		$items[]  = array(
			'value' => Template::format_active_installs_for_display( $installs ),
			'label' => __( 'Installs', 'wporg-plugins' ),
		);

		return $items;
	}

	/**
	 * Average rating on the 0–5 scale used by the directory.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return float
	 */
	protected static function rating( $plugin ) {
		if ( class_exists( '\WPORG_Ratings' ) ) {
			$rating = \WPORG_Ratings::get_avg_rating( 'plugin', $plugin->post_name );
			return $rating ? (float) $rating : 0.0;
		}

		$rating = get_post_meta( $plugin->ID, 'rating', true );
		return $rating ? (float) $rating : 0.0;
	}

	/**
	 * Draw a left-to-right midnight gradient footer.
	 *
	 * GD's resample of a 2×1 strip uses nearest-neighbour here, which produced
	 * a hard split. Blend on a 1px-high strip, then stretch it vertically.
	 *
	 * @param \GdImage $image    Destination image.
	 * @param int      $footer_h Footer height in pixels.
	 */
	protected static function draw_footer( $image, $footer_h ) {
		$strip = imagecreatetruecolor( self::WIDTH, 1 );
		if ( ! $strip ) {
			return;
		}

		$start = array( 2, 3, 129 );
		$end   = array( 40, 116, 252 );
		$span  = self::WIDTH - 1;

		for ( $x = 0; $x < self::WIDTH; $x++ ) {
			$t = $x / $span;
			imagesetpixel(
				$strip,
				$x,
				0,
				imagecolorallocate(
					$strip,
					(int) round( $start[0] + ( ( $end[0] - $start[0] ) * $t ) ),
					(int) round( $start[1] + ( ( $end[1] - $start[1] ) * $t ) ),
					(int) round( $start[2] + ( ( $end[2] - $start[2] ) * $t ) )
				)
			);
		}

		imagecopyresampled( $image, $strip, 0, self::HEIGHT - $footer_h, 0, 0, self::WIDTH, $footer_h, self::WIDTH, 1 );
		imagedestroy( $strip );
	}

	/**
	 * Draw wrapped lines, truncating the last line to width.
	 *
	 * @param \GdImage $image      Destination image.
	 * @param string[] $lines      Pre-wrapped lines.
	 * @param int      $x          X position.
	 * @param int      $baseline_y Starting baseline.
	 * @param int      $size       Font size.
	 * @param int      $line_h     Line height.
	 * @param string   $font       Font path.
	 * @param int      $color      Text color.
	 * @param int      $max_width  Content width for truncation.
	 * @param bool     $bold       Whether to simulate bold via a 1px double-draw.
	 */
	protected static function draw_lines( $image, $lines, $x, $baseline_y, $size, $line_h, $font, $color, $max_width, $bold = false ) {
		if ( $lines ) {
			$last           = array_key_last( $lines );
			$lines[ $last ] = self::truncate_text( $lines[ $last ], $max_width, $size, $font );
		}

		foreach ( $lines as $line ) {
			self::draw_string( $image, $line, $x, $baseline_y, $size, $font, $color, $bold );
			$baseline_y += $line_h;
		}
	}

	/**
	 * Draw a string. Inter ships as one TTF; a 1px double-draw approximates bold.
	 *
	 * @param \GdImage $image Destination image.
	 * @param string   $text  Text.
	 * @param int      $x     X position.
	 * @param int      $y     Baseline Y.
	 * @param int      $size  Font size.
	 * @param string   $font  Font path.
	 * @param int      $color Text color.
	 * @param bool     $bold  Whether to simulate bold.
	 */
	protected static function draw_string( $image, $text, $x, $y, $size, $font, $color, $bold = false ) {
		imagettftext( $image, $size, 0, $x, $y, $color, $font, $text );
		if ( $bold ) {
			imagettftext( $image, $size, 0, $x + 1, $y, $color, $font, $text );
		}
	}

	/**
	 * Draw a raster plugin icon. Returns false when a URL was given but fetch/decode failed.
	 *
	 * @param \GdImage $image   Destination image.
	 * @param string   $url     Icon URL.
	 * @param int      $x       X position.
	 * @param int      $y       Y position.
	 * @param int      $size    Target size in pixels.
	 * @param int      $surface Background fill color.
	 * @return bool
	 */
	protected static function draw_icon( $image, $url, $x, $y, $size, $surface ) {
		imagefilledrectangle( $image, $x, $y, $x + $size, $y + $size, $surface );

		if ( ! $url || str_ends_with( $url, '.svg' ) ) {
			return true;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! in_array( $host, array( 'ps.w.org', 'plugins.svn.wordpress.org' ), true ) ) {
			return true;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'             => 5,
				'limit_response_size' => MB_IN_BYTES,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return false;
		}

		$icon = @imagecreatefromstring( $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $icon ) {
			return false;
		}

		imagecopyresampled( $image, $icon, $x, $y, 0, 0, $size, $size, imagesx( $icon ), imagesy( $icon ) );
		imagedestroy( $icon );

		return true;
	}

	/**
	 * Draw the WordPress logotype, aligned with the stats value row.
	 *
	 * @param \GdImage $image    Destination image.
	 * @param int      $center_y Vertical center for the logo.
	 * @param int      $margin   Right margin.
	 */
	protected static function draw_logo( $image, $center_y, $margin ) {
		$path = dirname( __DIR__, 3 ) . '/style/images/about/WordPress-logotype-simplified.png';
		if ( ! file_exists( $path ) ) {
			return;
		}

		$logo = @imagecreatefrompng( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $logo ) {
			return;
		}

		$src_w = imagesx( $logo );
		$src_h = imagesy( $logo );
		if ( ! $src_w || ! $src_h ) {
			imagedestroy( $logo );
			return;
		}

		$target_h = 56;
		$target_w = (int) round( $src_w * ( $target_h / $src_h ) );
		$x        = self::WIDTH - $margin - $target_w;
		$y        = $center_y - (int) round( $target_h / 2 );

		imagealphablending( $image, true );
		imagecopyresampled( $image, $logo, $x, $y, 0, 0, $target_w, $target_h, $src_w, $src_h );
		imagedestroy( $logo );
	}

	/**
	 * Locate the Inter font shipped with wporg-mu-plugins.
	 *
	 * @return string|false
	 */
	protected static function font_path() {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			return false;
		}

		$path = WP_CONTENT_DIR . '/mu-plugins/wporg-mu-plugins/fonts/Inter.ttf';

		return file_exists( $path ) ? $path : false;
	}

	/**
	 * Measure rendered text width in pixels.
	 *
	 * @param string $text Text.
	 * @param int    $size Font size in points.
	 * @param string $font Font path.
	 * @return int
	 */
	protected static function text_width( $text, $size, $font ) {
		$box = imagettfbbox( $size, 0, $font, $text );
		if ( false === $box ) {
			return '' === $text ? 0 : PHP_INT_MAX;
		}

		return abs( $box[2] - $box[0] );
	}

	/**
	 * Truncate text to fit within a pixel width.
	 *
	 * Character-based shortening; byte-based substr() can split a multibyte
	 * character and imagettfbbox() fatals on invalid UTF-8.
	 *
	 * @param string $text      Input text.
	 * @param int    $max_width Maximum width in pixels.
	 * @param int    $size      Font size in points.
	 * @param string $font      Font path.
	 * @return string
	 */
	protected static function truncate_text( $text, $max_width, $size, $font ) {
		if ( self::text_width( $text, $size, $font ) <= $max_width ) {
			return $text;
		}

		$ellipsis = '…';
		$length   = mb_strlen( $text );

		while ( $length > 0 ) {
			$candidate = rtrim( mb_substr( $text, 0, $length ) ) . $ellipsis;
			if ( self::text_width( $candidate, $size, $font ) <= $max_width ) {
				return $candidate;
			}
			--$length;
		}

		return $ellipsis;
	}

	/**
	 * Wrap text into lines that fit within a pixel width.
	 *
	 * @param string $text      Input text.
	 * @param int    $max_width Maximum line width in pixels.
	 * @param int    $size      Font size in points.
	 * @param string $font      Font path.
	 * @param int    $max_lines Maximum number of lines.
	 * @return string[]
	 */
	protected static function wrap_text( $text, $max_width, $size, $font, $max_lines = 3 ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return array();
		}

		$words = preg_split( '/\s+/u', $text );
		if ( ! $words ) {
			return array();
		}

		$lines    = array();
		$line     = '';
		$overflow = false;

		foreach ( $words as $word ) {
			$candidate = $line ? $line . ' ' . $word : $word;

			if ( self::text_width( $candidate, $size, $font ) > $max_width ) {
				if ( $line ) {
					$lines[] = $line;
					$line    = $word;
				} else {
					$lines[] = self::truncate_text( $word, $max_width, $size, $font );
					$line    = '';
				}
			} else {
				$line = $candidate;
			}

			if ( count( $lines ) >= $max_lines ) {
				$overflow = true;
				break;
			}
		}

		if ( $line && count( $lines ) < $max_lines ) {
			$lines[] = $line;
		} elseif ( $line ) {
			$overflow = true;
		}

		if ( $overflow && $lines ) {
			$last           = array_key_last( $lines );
			$lines[ $last ] = self::truncate_text( rtrim( $lines[ $last ] ) . '…', $max_width, $size, $font );
		}

		return $lines;
	}
}
