<?php
namespace WordPressdotorg\Plugin_Directory;

require_once __DIR__ . '/class-plugin-share-image-layout.php';

/**
 * Generates dynamic social share images for plugin pages.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Share_Image {

	const WIDTH  = Plugin_Share_Image_Layout::CANVAS_WIDTH;
	const HEIGHT = Plugin_Share_Image_Layout::CANVAS_HEIGHT;

	/**
	 * Fallback for --wp--preset--gradient--midnight from wporg-parent-2021.
	 *
	 * @var array{angle: int, start: int[], end: int[]}
	 */
	const MIDNIGHT_GRADIENT = array(
		'angle' => 135,
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

		$stats[] = self::get_install_stat_item( $active_installs );

		return $stats;
	}

	/**
	 * Build the installs stat item.
	 *
	 * @param int $active_installs Active install count.
	 * @return array{icon: string, value: string, label: string}
	 */
	protected static function get_install_stat_item( $active_installs ) {
		if ( $active_installs < 10 ) {
			return array(
				'icon'  => 'download',
				'value' => '<10',
				'label' => __( 'Installs', 'wporg-plugins' ),
			);
		}

		if ( $active_installs >= 1000000 ) {
			$millions = intdiv( $active_installs, 1000000 );

			return array(
				'icon'  => 'download',
				'value' => sprintf( '%dM+', $millions ),
				'label' => __( 'Installs', 'wporg-plugins' ),
			);
		}

		if ( $active_installs >= 100000 ) {
			$thousands = intdiv( $active_installs, 1000 );

			return array(
				'icon'  => 'download',
				'value' => sprintf( '%dK+', $thousands ),
				'label' => __( 'Installs', 'wporg-plugins' ),
			);
		}

		return array(
			'icon'  => 'download',
			'value' => Template::format_active_installs_for_display( $active_installs ),
			'label' => __( 'Installs', 'wporg-plugins' ),
		);
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

		$data = self::get_data( $plugin );

		if ( ! $data ) {
			return false;
		}

		$image = imagecreatetruecolor( self::WIDTH, self::HEIGHT );

		if ( ! $image ) {
			return false;
		}

		$layout = Plugin_Share_Image_Layout::resolve();
		$fonts  = self::get_font_paths();
		$type   = $layout['type'];

		$allocate = static function ( $rgb ) use ( $image ) {
			return imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] );
		};

		$white            = imagecolorallocate( $image, 255, 255, 255 );
		$text_dark        = $allocate( $layout['colors']['title'] );
		$text_muted       = $allocate( $layout['colors']['description'] );
		$stat_icon_color  = $allocate( $layout['colors']['stat_icon'] );
		$stat_value_color = $allocate( $layout['colors']['stat_value'] );
		$stat_label_color = $allocate( $layout['colors']['stat_label'] );
		$icon_surface     = $allocate( $layout['colors']['icon_surface'] );

		imagefilledrectangle( $image, 0, 0, self::WIDTH, self::HEIGHT, $white );
		self::draw_footer_gradient( $image, $layout );

		$title_lines             = self::wrap_text_by_width(
			$data['title'],
			$layout['zones']['content']['width'],
			$type['title']['size'],
			$fonts['bold'] ?: $fonts['regular'],
			$type['title']['max_lines']
		);
		$layout['description_y'] = $layout['title']['y']
			+ ( count( $title_lines ) * $type['title']['line_height'] )
			+ $layout['description_gap'];

		self::draw_title_block( $image, $data['title'], $fonts, $text_dark, $layout, $title_lines );
		self::draw_description_block( $image, $data['description'], $fonts, $text_muted, $layout );
		self::draw_stats_row(
			$image,
			$data['stats'],
			$fonts,
			array(
				'icon'  => $stat_icon_color,
				'value' => $stat_value_color,
				'label' => $stat_label_color,
			),
			$layout
		);
		self::draw_icon(
			$image,
			$data['icon_url'],
			$layout['zones']['plugin_icon']['x'],
			$layout['zones']['plugin_icon']['y'],
			$layout['zones']['plugin_icon']['size'],
			$icon_surface
		);
		self::draw_wordpress_logo( $image, $layout );

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
	 * Draw the footer bar using the midnight theme gradient.
	 *
	 * Mirrors --wp--preset--gradient--midnight from wporg-parent-2021, which is
	 * available on the Plugin Directory via the parent theme global styles.
	 *
	 * @param \GdImage $image Destination image.
	 */
	protected static function draw_footer_gradient( $image, $layout = null ) {
		$gradient = self::get_midnight_gradient_stops();
		$footer   = ( $layout ?? Plugin_Share_Image_Layout::resolve() )['zones']['footer'];
		$y_start  = $footer['y'];
		$y_mid    = $y_start + ( $footer['height'] / 2 );
		$angle    = deg2rad( $gradient['angle'] );

		// CSS linear-gradient angles are measured clockwise from north.
		$dx = sin( $angle );
		$dy = -cos( $angle );

		$projections = array(
			0,
			self::WIDTH * $dx,
			self::HEIGHT * $dy,
			( self::WIDTH * $dx ) + ( self::HEIGHT * $dy ),
		);

		$min_proj = min( $projections );
		$max_proj = max( $projections );
		$range    = $max_proj - $min_proj ?: 1;

		for ( $x = 0; $x < self::WIDTH; $x++ ) {
			$t     = ( ( $x * $dx ) + ( $y_mid * $dy ) - $min_proj ) / $range;
			$t     = max( 0, min( 1, $t ) );
			$color = imagecolorallocate(
				$image,
				(int) round( $gradient['start'][0] + ( ( $gradient['end'][0] - $gradient['start'][0] ) * $t ) ),
				(int) round( $gradient['start'][1] + ( ( $gradient['end'][1] - $gradient['start'][1] ) * $t ) ),
				(int) round( $gradient['start'][2] + ( ( $gradient['end'][2] - $gradient['start'][2] ) * $t ) )
			);

			imageline( $image, $x, $y_start, $x, $y_start + $footer['height'] - 1, $color );
		}
	}

	/**
	 * Resolve midnight gradient stops from theme presets when available.
	 *
	 * @return array{angle: int, start: int[], end: int[]}
	 */
	protected static function get_midnight_gradient_stops() {
		static $stops = null;

		if ( null !== $stops ) {
			return $stops;
		}

		$stops = self::MIDNIGHT_GRADIENT;

		if ( class_exists( '\WP_Theme_JSON_Resolver' ) ) {
			$raw       = \WP_Theme_JSON_Resolver::get_merged_data()->get_raw_data();
			$gradients = $raw['settings']['color']['gradients'] ?? array();

			foreach ( $gradients as $gradient ) {
				if ( 'midnight' !== ( $gradient['slug'] ?? '' ) || empty( $gradient['gradient'] ) ) {
					continue;
				}

				$parsed = self::parse_linear_gradient( $gradient['gradient'] );
				if ( $parsed ) {
					$stops = $parsed;
					break;
				}
			}
		}

		return $stops;
	}

	/**
	 * Parse a two-stop CSS linear-gradient declaration.
	 *
	 * @param string $gradient CSS linear-gradient() value.
	 * @return array{angle: int, start: int[], end: int[]}|null
	 */
	protected static function parse_linear_gradient( $gradient ) {
		if ( ! preg_match(
			'/linear-gradient\(\s*([0-9.]+)deg\s*,\s*rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)\s*0%\s*,\s*rgb\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)\s*100%\s*\)/i',
			$gradient,
			$matches
		) ) {
			return null;
		}

		return array(
			'angle' => (int) round( (float) $matches[1] ),
			'start' => array( (int) $matches[2], (int) $matches[3], (int) $matches[4] ),
			'end'   => array( (int) $matches[5], (int) $matches[6], (int) $matches[7] ),
		);
	}

	/**
	 * Draw the plugin title block.
	 *
	 * @param \GdImage             $image      Destination image.
	 * @param string               $title      Plugin title.
	 * @param array                $fonts      Font paths.
	 * @param int                  $text_color Text color.
	 * @param array<string, mixed> $layout     Layout measurements.
	 * @param string[]|null        $lines      Pre-wrapped title lines.
	 */
	protected static function draw_title_block( $image, $title, $fonts, $text_color, $layout, $lines = null ) {
		$type      = $layout['type']['title'];
		$font_path = $fonts['bold'] ?: $fonts['regular'];
		$lines     = $lines ?? self::wrap_text_by_width(
			$title,
			$layout['zones']['content']['width'],
			$type['size'],
			$font_path,
			$type['max_lines']
		);

		$baseline_y = $layout['title']['y'];

		if ( count( $lines ) >= $type['max_lines'] ) {
			$last_index           = array_key_last( $lines );
			$lines[ $last_index ] = self::truncate_text_to_width(
				$lines[ $last_index ],
				$layout['zones']['content']['width'],
				$type['size'],
				$font_path
			);
		}

		foreach ( $lines as $line ) {
			self::draw_text_line(
				$image,
				$line,
				$layout['title']['x'],
				$baseline_y,
				$type['size'],
				$fonts['bold'],
				$text_color,
				true
			);

			$baseline_y += $type['line_height'];
		}
	}

	/**
	 * Draw the plugin description block.
	 *
	 * @param \GdImage           $image       Destination image.
	 * @param string             $description Plugin description.
	 * @param array              $fonts       Font paths.
	 * @param int                $text_color  Text color.
	 * @param array<string, int> $layout      Layout measurements.
	 */
	protected static function draw_description_block( $image, $description, $fonts, $text_color, $layout ) {
		$type      = $layout['type']['description'];
		$font_path = $fonts['regular'] ?: $fonts['bold'];
		$lines     = self::wrap_text_by_width(
			$description,
			$layout['zones']['content']['width'],
			$type['size'],
			$font_path,
			$type['max_lines']
		);

		$baseline_y = $layout['description_y'];

		if ( ! empty( $lines ) ) {
			$last_index           = array_key_last( $lines );
			$lines[ $last_index ] = self::truncate_text_to_width(
				$lines[ $last_index ],
				$layout['zones']['content']['width'],
				$type['size'],
				$font_path
			);
		}

		foreach ( $lines as $line ) {
			self::draw_text_line(
				$image,
				$line,
				$layout['zones']['content']['x'],
				$baseline_y,
				$type['size'],
				$fonts['regular'],
				$text_color
			);

			$baseline_y += $type['line_height'];
		}
	}

	/**
	 * Draw a single line of text with TTF or bitmap fallback.
	 *
	 * @param \GdImage     $image      Destination image.
	 * @param string       $text       Text to render.
	 * @param int          $x          X position.
	 * @param int          $baseline_y Baseline Y position.
	 * @param int          $font_size  Font size in points.
	 * @param string|false $font_path Font path.
	 * @param int          $color      Text color.
	 * @param bool         $emphasize  Whether to simulate a heavier weight.
	 */
	protected static function draw_text_line( $image, $text, $x, $baseline_y, $font_size, $font_path, $color, $emphasize = false ) {
		if ( $font_path ) {
			imagettftext( $image, $font_size, 0, $x, $baseline_y, $color, $font_path, $text );

			if ( $emphasize ) {
				imagettftext( $image, $font_size, 0, $x + 1, $baseline_y, $color, $font_path, $text );
			}

			return;
		}

		$bitmap_size = $font_size >= 30 ? 5 : 4;
		imagestring( $image, $bitmap_size, $x, $baseline_y - ( $font_size + 4 ), $text, $color );
	}

	/**
	 * Draw a plugin icon onto the canvas.
	 *
	 * @param \GdImage $image        Destination image.
	 * @param string   $url          Icon URL.
	 * @param int      $x            X position.
	 * @param int      $y            Y position.
	 * @param int      $size         Target size in pixels.
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

		$src_w = imagesx( $icon );
		$src_h = imagesy( $icon );

		imagecopyresampled( $image, $icon, $x, $y, 0, 0, $size, $size, $src_w, $src_h );
		imagedestroy( $icon );
	}

	/**
	 * Draw the WordPress logo in the bottom-right corner.
	 *
	 * @param \GdImage           $image  Destination image.
	 * @param array<string, int> $layout Layout measurements.
	 */
	protected static function draw_wordpress_logo( $image, $layout ) {
		$path = self::get_logo_path();

		if ( ! $path ) {
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

		$target_h = $layout['zones']['branding']['size'];
		$target_w = (int) round( $src_w * ( $target_h / $src_h ) );
		$x        = self::WIDTH - $layout['zones']['content']['x'] - $target_w;
		$y        = $layout['zones']['branding']['center_y'] - (int) round( $target_h / 2 );

		imagealphablending( $image, true );
		imagecopyresampled( $image, $logo, $x, $y, 0, 0, $target_w, $target_h, $src_w, $src_h );
		imagedestroy( $logo );
	}

	/**
	 * Locate the WordPress logo asset bundled with WordPress.org.
	 *
	 * @return string|false
	 */
	protected static function get_logo_path() {
		$path = dirname( __DIR__, 3 ) . '/style/images/about/WordPress-logotype-simplified.png';

		return file_exists( $path ) ? $path : false;
	}

	/**
	 * Draw the plugin stats row above the footer bar.
	 *
	 * @param \GdImage                                                      $image  Destination image.
	 * @param array<int, array{icon: string, value: string, label: string}> $stats  Stat items.
	 * @param array                                                         $fonts  Font paths.
	 * @param array{icon: int, value: int, label: int}                      $colors Stat colors.
	 * @param array<string, int>                                            $layout Layout measurements.
	 */
	protected static function draw_stats_row( $image, $stats, $fonts, $colors, $layout ) {
		if ( empty( $stats ) ) {
			return;
		}

		$dashicons_path = self::get_dashicons_font_path();
		$value_font     = $fonts['bold'] ?: $fonts['regular'];

		foreach ( $stats as $index => $stat ) {
			if ( $index >= Plugin_Share_Image_Layout::STAT_COLUMNS ) {
				break;
			}

			self::draw_stat_item(
				$image,
				$stat,
				Plugin_Share_Image_Layout::stat_column_x( $layout, $index ),
				$layout,
				$dashicons_path,
				$value_font,
				$fonts['regular'],
				$colors
			);
		}
	}

	/**
	 * Draw a single stat item with icon, value, and label.
	 *
	 * @param \GdImage                                          $image          Destination image.
	 * @param array{icon: string, value: string, label: string} $stat           Stat item.
	 * @param int                                               $x              Column X position.
	 * @param array<string, int>                                $layout         Layout measurements.
	 * @param string|false                                      $dashicons_path Dashicons font path.
	 * @param string|false                                      $value_font     Value font path.
	 * @param string|false                                      $label_font     Label font path.
	 * @param array{icon: int, value: int, label: int}          $colors     Stat colors.
	 */
	protected static function draw_stat_item( $image, $stat, $x, $layout, $dashicons_path, $value_font, $label_font, $colors ) {
		$type       = $layout['type']['stat'];
		$stats_zone = $layout['zones']['stats'];
		$glyph      = self::get_dashicon_glyph( $stat['icon'] );
		$value_x    = $x;
		$icon_slot  = $type['icon_slot'] ?? $type['icon_size'];

		if ( $glyph && $dashicons_path ) {
			$icon_y = self::align_baseline_to_target(
				$glyph,
				$type['icon_size'],
				$dashicons_path,
				$stats_zone['value_y'],
				$type['value_size'],
				$stat['value'],
				$value_font
			);

			imagettftext( $image, $type['icon_size'], 0, $x, $icon_y, $colors['icon'], $dashicons_path, $glyph );
			$value_x = $x + $icon_slot + $type['icon_gap'];
		}

		self::draw_text_line(
			$image,
			$stat['value'],
			$value_x,
			$stats_zone['value_y'],
			$type['value_size'],
			$value_font,
			$colors['value'],
			true
		);

		self::draw_text_line(
			$image,
			$stat['label'],
			$value_x,
			$stats_zone['label_y'],
			$type['label_size'],
			$label_font ?: $value_font,
			$colors['label']
		);
	}

	/**
	 * Align a dashicon baseline to visually center with a value glyph.
	 *
	 * @param string       $icon_glyph      Dashicon glyph.
	 * @param int          $icon_font_size  Dashicon font size.
	 * @param string|false $icon_font_path  Dashicon font path.
	 * @param int          $value_baseline  Value text baseline.
	 * @param int          $value_font_size Value font size.
	 * @param string       $value_text      Value text.
	 * @param string|false $value_font_path Value font path.
	 * @return int
	 */
	protected static function align_baseline_to_target( $icon_glyph, $icon_font_size, $icon_font_path, $value_baseline, $value_font_size, $value_text, $value_font_path ) {
		if ( ! $icon_font_path || ! $value_font_path ) {
			return $value_baseline;
		}

		$icon_box  = imagettfbbox( $icon_font_size, 0, $icon_font_path, $icon_glyph );
		$value_box = imagettfbbox( $value_font_size, 0, $value_font_path, $value_text );

		$icon_center  = ( min( $icon_box[5], $icon_box[7] ) + max( $icon_box[1], $icon_box[3] ) ) / 2;
		$value_center = ( min( $value_box[5], $value_box[7] ) + max( $value_box[1], $value_box[3] ) ) / 2;

		return (int) round( $value_baseline + $value_center - $icon_center );
	}

	/**
	 * Locate the Dashicons font bundled with WordPress.
	 *
	 * @return string|false
	 */
	protected static function get_dashicons_font_path() {
		$path = ABSPATH . 'wp-includes/fonts/dashicons.ttf';

		return file_exists( $path ) ? $path : false;
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

		if ( ! isset( $codepoints[ $icon ] ) ) {
			return false;
		}

		return function_exists( 'mb_chr' )
			? mb_chr( $codepoints[ $icon ], 'UTF-8' )
			: false;
	}

	/**
	 * Locate usable TrueType fonts in the container.
	 *
	 * @return array{regular: string|false, bold: string|false}
	 */
	protected static function get_font_paths() {
		$wporg_fonts = defined( 'WP_CONTENT_DIR' )
			? WP_CONTENT_DIR . '/mu-plugins/wporg-mu-plugins/fonts/'
			: '';

		$candidates = array(
			'regular' => array_filter(
				array(
					$wporg_fonts ? $wporg_fonts . 'Inter.ttf' : '',
					'/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
					'/usr/share/fonts/TTF/DejaVuSans.ttf',
					'C:\\Windows\\Fonts\\arial.ttf',
				)
			),
			'bold'    => array_filter(
				array(
					$wporg_fonts ? $wporg_fonts . 'Inter.ttf' : '',
					'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
					'/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
					'C:\\Windows\\Fonts\\arialbd.ttf',
				)
			),
		);

		$fonts = array(
			'regular' => false,
			'bold'    => false,
		);

		foreach ( $candidates['regular'] as $path ) {
			if ( file_exists( $path ) ) {
				$fonts['regular'] = $path;
				break;
			}
		}

		foreach ( $candidates['bold'] as $path ) {
			if ( file_exists( $path ) ) {
				$fonts['bold'] = $path;
				break;
			}
		}

		if ( ! $fonts['bold'] && $fonts['regular'] ) {
			$fonts['bold'] = $fonts['regular'];
		}

		return $fonts;
	}

	/**
	 * Measure rendered text width in pixels.
	 *
	 * @param string       $text      Input text.
	 * @param int          $font_size Font size in points.
	 * @param string|false $font_path Font path.
	 * @return int
	 */
	protected static function measure_text_width( $text, $font_size, $font_path ) {
		if ( ! $font_path ) {
			return (int) ( strlen( $text ) * ( $font_size * 0.55 ) );
		}

		$box = imagettfbbox( $font_size, 0, $font_path, $text );

		return abs( $box[2] - $box[0] );
	}

	/**
	 * Truncate text to fit within a pixel width.
	 *
	 * @param string       $text      Input text.
	 * @param int          $max_width Maximum width in pixels.
	 * @param int          $font_size Font size in points.
	 * @param string|false $font_path Font path.
	 * @return string
	 */
	protected static function truncate_text_to_width( $text, $max_width, $font_size, $font_path ) {
		if ( self::measure_text_width( $text, $font_size, $font_path ) <= $max_width ) {
			return $text;
		}

		$ellipsis = '…';
		$length   = strlen( $text );

		while ( $length > 0 ) {
			$candidate = rtrim( substr( $text, 0, $length ) ) . $ellipsis;

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
	 * @param string       $text      Input text.
	 * @param int          $max_width Maximum line width in pixels.
	 * @param int          $font_size Font size in points.
	 * @param string|false $font_path Font path.
	 * @param int          $max_lines Maximum number of lines.
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

	/**
	 * Truncate text to a maximum character length.
	 *
	 * @param string $text   Input text.
	 * @param int    $length Maximum length.
	 * @return string
	 */
	protected static function truncate_text( $text, $length ) {
		if ( strlen( $text ) <= $length ) {
			return $text;
		}

		return rtrim( substr( $text, 0, $length - 1 ) ) . '…';
	}
}
