<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 * Generates dynamic social share images for plugin pages.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Share_Image {

	const WIDTH  = 1200;
	const HEIGHT = 630;

	const MARGIN_X      = 72;
	const MARGIN_TOP    = 72;
	const FOOTER_HEIGHT = 16;
	const ICON_SIZE     = 128;
	const ICON_GAP      = 48;
	const LOGO_RESERVE  = 108;
	const LOGO_SIZE     = 56;
	const STAT_COLUMNS  = 4;

	const TITLE_SIZE      = 40;
	const TITLE_LINE      = 52;
	const TITLE_MAX_LINES = 2;
	const DESC_SIZE       = 22;
	const DESC_LINE       = 34;
	const DESC_MAX_LINES  = 3;
	const DESC_GAP        = 20;
	const STAT_ICON_SIZE  = 26;
	const STAT_VALUE_SIZE = 26;
	const STAT_LABEL_SIZE = 14;
	const STAT_ICON_GAP   = 8;
	const STAT_ICON_SLOT  = 32;

	/**
	 * Midnight footer colors (matches --wp--preset--gradient--midnight).
	 *
	 * @var array{start: int[], end: int[]}
	 */
	const MIDNIGHT_FOOTER = array(
		'start' => array( 2, 3, 129 ),
		'end'   => array( 40, 116, 252 ),
	);

	/**
	 * Collect share-image data for a plugin.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return array|null
	 */
	public static function get_data( $plugin ) {
		$plugin = get_post( $plugin );

		if ( ! $plugin || 'plugin' !== $plugin->post_type || 'publish' !== $plugin->post_status ) {
			return null;
		}

		$icons = Template::get_plugin_icon( $plugin );
		$icon  = $icons['icon_2x'] ?? $icons['icon'] ?? '';

		if ( $icons['generated'] && str_ends_with( (string) $icon, '.svg' ) ) {
			$icon = '';
		}

		$active_installs = (int) get_post_meta( $plugin->ID, 'active_installs', true );

		return array(
			'title'       => get_the_title( $plugin ),
			'description' => wp_strip_all_tags( get_the_excerpt( $plugin ) ),
			'icon_url'    => $icon,
			'stats'       => self::get_stat_items( $plugin, $active_installs ),
		);
	}

	/**
	 * Build structured stat items for the stats row.
	 *
	 * @param \WP_Post $plugin          Plugin post object.
	 * @param int      $active_installs Active install count.
	 * @return array<int, array{icon: string, value: string, label: string}>
	 */
	protected static function get_stat_items( $plugin, $active_installs ) {
		$stats = array();

		$contributors = self::count_contributors( $plugin );
		$stats[]      = array(
			'icon'  => 'groups',
			'value' => number_format_i18n( $contributors ),
			'label' => _n( 'Contributor', 'Contributors', $contributors, 'wporg-plugins' ),
		);

		$locales = self::count_locales( $plugin );
		if ( $locales > 0 ) {
			$stats[] = array(
				'icon'  => 'translation',
				'value' => number_format_i18n( $locales ),
				'label' => _n( 'Locale', 'Locales', $locales, 'wporg-plugins' ),
			);
		}

		$rating = self::get_rating_value( $plugin );
		if ( $rating > 0 ) {
			$stats[] = array(
				'icon'  => 'star-filled',
				'value' => number_format_i18n( $rating, 1 ),
				'label' => __( 'Rating', 'wporg-plugins' ),
			);
		}

		$stats[] = array(
			'icon'  => 'download',
			'value' => self::format_install_count( $active_installs ),
			'label' => __( 'Installs', 'wporg-plugins' ),
		);

		return $stats;
	}

	/**
	 * Format an active-install count for the share-image stats row.
	 *
	 * @param int $active_installs Active install count.
	 * @return string
	 */
	protected static function format_install_count( $active_installs ) {
		if ( $active_installs < 10 ) {
			return '<10';
		}

		if ( $active_installs >= 1000000 ) {
			return sprintf( '%dM+', intdiv( $active_installs, 1000000 ) );
		}

		if ( $active_installs >= 100000 ) {
			return sprintf( '%dK+', intdiv( $active_installs, 1000 ) );
		}

		return Template::format_active_installs_for_display( $active_installs );
	}

	/**
	 * Count plugin contributors, including the plugin owner.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return int
	 */
	protected static function count_contributors( $plugin ) {
		$contributors = get_terms(
			array(
				'taxonomy'   => 'plugin_contributors',
				'object_ids' => array( $plugin->ID ),
				'fields'     => 'names',
			)
		);

		if ( is_wp_error( $contributors ) ) {
			$contributors = array();
		}

		$plugin_owner = get_the_author_meta( 'user_nicename', $plugin->post_author );
		if ( $plugin_owner && ! in_array( $plugin_owner, $contributors, true ) ) {
			$contributors = array_merge( array( $plugin_owner ), $contributors );
		}

		return count( array_unique( $contributors ) );
	}

	/**
	 * Count locales with active translations for a plugin.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return int
	 */
	protected static function count_locales( $plugin ) {
		$translations = Plugin_I18n::instance()->get_translations( $plugin->post_name );

		if ( empty( $translations ) ) {
			return 0;
		}

		if ( defined( 'WPORG_GLOBAL_NETWORK_ID' ) ) {
			$wp_locales = wp_list_pluck( $translations, 'wp_locale' );
			$count      = get_sites(
				array(
					'network_id' => WPORG_GLOBAL_NETWORK_ID,
					'public'     => 1,
					'path'       => '/',
					'locale__in' => $wp_locales,
					'number'     => '',
					'count'      => true,
				)
			);

			if ( $count ) {
				return (int) $count;
			}
		}

		return count( $translations );
	}

	/**
	 * Get the average plugin rating.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return float
	 */
	protected static function get_rating_value( $plugin ) {
		if ( class_exists( '\WPORG_Ratings' ) ) {
			return (float) ( \WPORG_Ratings::get_avg_rating( 'plugin', $plugin->post_name ) ?: 0 );
		}

		return (float) ( get_post_meta( $plugin->ID, 'rating', true ) ?: 0 );
	}

	/**
	 * Build the public share-image URL for a plugin.
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object.
	 * @return string|false
	 */
	public static function get_url( $post = null ) {
		$plugin = get_post( $post );

		if ( ! $plugin || 'plugin' !== $plugin->post_type ) {
			return false;
		}

		return home_url( "/share-image/{$plugin->post_name}.jpg" );
	}

	/**
	 * Render a JPEG share image for a plugin.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return string|false JPEG bytes, or false on failure.
	 */
	public static function render( $plugin ) {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			return false;
		}

		$font = self::get_inter_font_path();
		if ( ! $font ) {
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

		$icon_x        = self::WIDTH - self::MARGIN_X - self::ICON_SIZE;
		$content_width = $icon_x - self::ICON_GAP - self::MARGIN_X;
		$title_y       = self::MARGIN_TOP + 44;
		$stats_value_y = self::HEIGHT - self::FOOTER_HEIGHT - 78;
		$stats_label_y = self::HEIGHT - self::FOOTER_HEIGHT - 38;
		$column_width  = (int) floor( ( self::WIDTH - ( 2 * self::MARGIN_X ) - self::LOGO_RESERVE ) / self::STAT_COLUMNS );

		$white        = imagecolorallocate( $image, 255, 255, 255 );
		$text_dark    = imagecolorallocate( $image, 30, 30, 30 );
		$text_muted   = imagecolorallocate( $image, 80, 87, 94 );
		$stat_icon    = imagecolorallocate( $image, 113, 116, 127 );
		$stat_value   = imagecolorallocate( $image, 50, 55, 60 );
		$stat_label   = imagecolorallocate( $image, 113, 116, 127 );
		$icon_surface = imagecolorallocate( $image, 246, 247, 247 );

		imagefilledrectangle( $image, 0, 0, self::WIDTH, self::HEIGHT, $white );
		self::draw_footer_bar( $image );

		$title_lines = self::wrap_text_by_width( $data['title'], $content_width, self::TITLE_SIZE, $font, self::TITLE_MAX_LINES );
		self::draw_text_block( $image, $title_lines, self::MARGIN_X, $title_y, self::TITLE_SIZE, self::TITLE_LINE, $font, $text_dark, $content_width, true );

		$description_y = $title_y + ( count( $title_lines ) * self::TITLE_LINE ) + self::DESC_GAP;
		$desc_lines    = self::wrap_text_by_width( $data['description'], $content_width, self::DESC_SIZE, $font, self::DESC_MAX_LINES );
		self::draw_text_block( $image, $desc_lines, self::MARGIN_X, $description_y, self::DESC_SIZE, self::DESC_LINE, $font, $text_muted, $content_width );

		self::draw_stats_row(
			$image,
			$data['stats'],
			$font,
			array(
				'icon'  => $stat_icon,
				'value' => $stat_value,
				'label' => $stat_label,
			),
			$column_width,
			$stats_value_y,
			$stats_label_y
		);
		self::draw_icon( $image, $data['icon_url'], $icon_x, self::MARGIN_TOP, self::ICON_SIZE, $icon_surface );
		self::draw_wordpress_logo( $image, $stats_value_y - 18 );

		ob_start();
		imagejpeg( $image, null, 88 );
		$bytes = ob_get_clean();
		imagedestroy( $image );

		return $bytes;
	}

	/**
	 * Output HTTP headers and JPEG body for a plugin share image.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return bool Whether output was sent.
	 */
	public static function output( $plugin ) {
		$bytes = self::render( $plugin );

		if ( false === $bytes ) {
			return false;
		}

		status_header( 200 );
		header( 'Content-Type: image/jpeg' );
		header( 'Cache-Control: public, max-age=' . YEAR_IN_SECONDS );
		header( 'Expires: ' . gmdate( 'D, d M Y H:i:s \G\M\T', time() + YEAR_IN_SECONDS ) );
		echo $bytes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return true;
	}

	/**
	 * Draw a left-to-right midnight gradient footer bar.
	 *
	 * @param \GdImage $image Destination image.
	 */
	protected static function draw_footer_bar( $image ) {
		$y_start = self::HEIGHT - self::FOOTER_HEIGHT;
		$start   = self::MIDNIGHT_FOOTER['start'];
		$end     = self::MIDNIGHT_FOOTER['end'];

		for ( $x = 0; $x < self::WIDTH; $x++ ) {
			$t     = $x / ( self::WIDTH - 1 );
			$color = imagecolorallocate(
				$image,
				(int) round( $start[0] + ( ( $end[0] - $start[0] ) * $t ) ),
				(int) round( $start[1] + ( ( $end[1] - $start[1] ) * $t ) ),
				(int) round( $start[2] + ( ( $end[2] - $start[2] ) * $t ) )
			);
			imageline( $image, $x, $y_start, $x, $y_start + self::FOOTER_HEIGHT - 1, $color );
		}
	}

	/**
	 * Draw wrapped text lines, truncating the last line when needed.
	 *
	 * @param \GdImage $image       Destination image.
	 * @param string[] $lines       Pre-wrapped lines.
	 * @param int      $x           X position.
	 * @param int      $baseline_y  Starting baseline.
	 * @param int      $font_size   Font size.
	 * @param int      $line_height Line height.
	 * @param string   $font_path   Font path.
	 * @param int      $color       Text color.
	 * @param int      $max_width   Content width for truncation.
	 * @param bool     $emphasize   Whether to simulate bold via a 1px double-draw.
	 */
	protected static function draw_text_block( $image, $lines, $x, $baseline_y, $font_size, $line_height, $font_path, $color, $max_width, $emphasize = false ) {
		if ( ! empty( $lines ) ) {
			$last_index           = array_key_last( $lines );
			$lines[ $last_index ] = self::truncate_text_to_width( $lines[ $last_index ], $max_width, $font_size, $font_path );
		}

		foreach ( $lines as $line ) {
			imagettftext( $image, $font_size, 0, $x, $baseline_y, $color, $font_path, $line );

			// Inter ships as a single TTF; double-draw approximates bold weight.
			if ( $emphasize ) {
				imagettftext( $image, $font_size, 0, $x + 1, $baseline_y, $color, $font_path, $line );
			}

			$baseline_y += $line_height;
		}
	}

	/**
	 * Draw a plugin icon onto the canvas.
	 *
	 * @param \GdImage $image         Destination image.
	 * @param string   $url           Icon URL.
	 * @param int      $x             X position.
	 * @param int      $y             Y position.
	 * @param int      $size          Target size in pixels.
	 * @param int|null $surface_color Optional background fill color.
	 */
	protected static function draw_icon( $image, $url, $x, $y, $size, $surface_color = null ) {
		if ( $surface_color ) {
			imagefilledrectangle( $image, $x, $y, $x + $size, $y + $size, $surface_color );
		}

		if ( ! $url || str_ends_with( $url, '.svg' ) ) {
			return;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 5,
			)
		);

		if ( is_wp_error( $response ) ) {
			return;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( ! $body ) {
			return;
		}

		$icon = @imagecreatefromstring( $body ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $icon ) {
			return;
		}

		imagecopyresampled( $image, $icon, $x, $y, 0, 0, $size, $size, imagesx( $icon ), imagesy( $icon ) );
		imagedestroy( $icon );
	}

	/**
	 * Draw the WordPress logo aligned with the stats value row.
	 *
	 * @param \GdImage $image    Destination image.
	 * @param int      $center_y Vertical center for the logo.
	 */
	protected static function draw_wordpress_logo( $image, $center_y ) {
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

		$target_h = self::LOGO_SIZE;
		$target_w = (int) round( $src_w * ( $target_h / $src_h ) );
		$x        = self::WIDTH - self::MARGIN_X - $target_w;
		$y        = $center_y - (int) round( $target_h / 2 );

		imagealphablending( $image, true );
		imagecopyresampled( $image, $logo, $x, $y, 0, 0, $target_w, $target_h, $src_w, $src_h );
		imagedestroy( $logo );
	}

	/**
	 * Draw the plugin stats row above the footer bar.
	 *
	 * @param \GdImage                                                      $image         Destination image.
	 * @param array<int, array{icon: string, value: string, label: string}> $stats         Stat items.
	 * @param string                                                        $font          Inter font path.
	 * @param array{icon: int, value: int, label: int}                      $colors        Stat colors.
	 * @param int                                                           $column_width  Column width.
	 * @param int                                                           $stats_value_y Value baseline.
	 * @param int                                                           $stats_label_y Label baseline.
	 */
	protected static function draw_stats_row( $image, $stats, $font, $colors, $column_width, $stats_value_y, $stats_label_y ) {
		if ( empty( $stats ) ) {
			return;
		}

		$dashicons_path = ABSPATH . 'wp-includes/fonts/dashicons.ttf';
		if ( ! file_exists( $dashicons_path ) ) {
			$dashicons_path = false;
		}

		foreach ( $stats as $index => $stat ) {
			if ( $index >= self::STAT_COLUMNS ) {
				break;
			}

			$x       = self::MARGIN_X + ( $index * $column_width );
			$value_x = $x;
			$glyph   = self::get_dashicon_glyph( $stat['icon'] );

			if ( $glyph && $dashicons_path ) {
				// Fixed offset keeps icon visually centered with the value row.
				imagettftext( $image, self::STAT_ICON_SIZE, 0, $x, $stats_value_y - 2, $colors['icon'], $dashicons_path, $glyph );
				$value_x = $x + self::STAT_ICON_SLOT + self::STAT_ICON_GAP;
			}

			imagettftext( $image, self::STAT_VALUE_SIZE, 0, $value_x, $stats_value_y, $colors['value'], $font, $stat['value'] );
			imagettftext( $image, self::STAT_VALUE_SIZE, 0, $value_x + 1, $stats_value_y, $colors['value'], $font, $stat['value'] );
			imagettftext( $image, self::STAT_LABEL_SIZE, 0, $value_x, $stats_label_y, $colors['label'], $font, $stat['label'] );
		}
	}

	/**
	 * Map a dashicon slug to its font glyph.
	 *
	 * @param string $icon Dashicon slug.
	 * @return string|false
	 */
	protected static function get_dashicon_glyph( $icon ) {
		$codepoints = array(
			'groups'      => 0xF307,
			'translation' => 0xF326,
			'star-filled' => 0xF155,
			'download'    => 0xF316,
		);

		if ( ! isset( $codepoints[ $icon ] ) || ! function_exists( 'mb_chr' ) ) {
			return false;
		}

		return mb_chr( $codepoints[ $icon ], 'UTF-8' );
	}

	/**
	 * Locate the Inter font shipped with wporg-mu-plugins.
	 *
	 * @return string|false
	 */
	protected static function get_inter_font_path() {
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			return false;
		}

		$path = WP_CONTENT_DIR . '/mu-plugins/wporg-mu-plugins/fonts/Inter.ttf';

		return file_exists( $path ) ? $path : false;
	}

	/**
	 * Measure rendered text width in pixels.
	 *
	 * @param string $text      Input text.
	 * @param int    $font_size Font size in points.
	 * @param string $font_path Font path.
	 * @return int
	 */
	protected static function measure_text_width( $text, $font_size, $font_path ) {
		$box = imagettfbbox( $font_size, 0, $font_path, $text );

		return abs( $box[2] - $box[0] );
	}

	/**
	 * Truncate text to fit within a pixel width.
	 *
	 * @param string $text      Input text.
	 * @param int    $max_width Maximum width in pixels.
	 * @param int    $font_size Font size in points.
	 * @param string $font_path Font path.
	 * @return string
	 */
	protected static function truncate_text_to_width( $text, $max_width, $font_size, $font_path ) {
		if ( self::measure_text_width( $text, $font_size, $font_path ) <= $max_width ) {
			return $text;
		}

		// Character-based shortening; byte-based substr() could split a multibyte
		// character and imagettfbbox() fatals on invalid UTF-8.
		$ellipsis = '…';
		$length   = mb_strlen( $text );

		while ( $length > 0 ) {
			$candidate = rtrim( mb_substr( $text, 0, $length ) ) . $ellipsis;

			if ( self::measure_text_width( $candidate, $font_size, $font_path ) <= $max_width ) {
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
	 * @param int    $font_size Font size in points.
	 * @param string $font_path Font path.
	 * @param int    $max_lines Maximum number of lines.
	 * @return string[]
	 */
	protected static function wrap_text_by_width( $text, $max_width, $font_size, $font_path, $max_lines = 3 ) {
		$words = preg_split( '/\s+/', trim( $text ) );
		$lines = array();
		$line  = '';

		foreach ( $words as $word ) {
			$candidate = $line ? $line . ' ' . $word : $word;

			if ( self::measure_text_width( $candidate, $font_size, $font_path ) > $max_width ) {
				if ( $line ) {
					$lines[] = $line;
					$line    = $word;
				} else {
					$lines[] = self::truncate_text_to_width( $word, $max_width, $font_size, $font_path );
					$line    = '';
				}
			} else {
				$line = $candidate;
			}

			if ( count( $lines ) >= $max_lines ) {
				break;
			}
		}

		if ( $line && count( $lines ) < $max_lines ) {
			$lines[] = $line;
		}

		return $lines;
	}
}
