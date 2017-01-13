<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 * Various helpers to retrieve data not stored within WordPress.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Template {

	/**
	 * Returns a string representing the number of active installs for an item.
	 *
	 * @static
	 *
	 * @param bool              $full Optional. Whether to include "active installs" suffix. Default: true.
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return string "1+ million" or "1+ million active installs" depending on $full.
	 */
	public static function active_installs( $full = true, $post = null ) {
		$post  = get_post( $post );
		$count = get_post_meta( $post->ID, 'active_installs', true ) ?: 0;

		if ( $count <= 10 ) {
			$text = __( 'Less than 10', 'wporg-plugins' );
		} elseif ( $count >= 1000000 ) {
			$text = __( '1+ million', 'wporg-plugins' );
		} else {
			$text = number_format_i18n( $count ) . '+';
		}

		return $full ? sprintf( __( '%s active installs', 'wporg-plugins' ), $text ) : $text;
	}

	/**
	 * Returns the number of downloads for a plugin.
	 *
	 * @static
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int|\WP_Post|null $post Optional.
	 * @return int
	 */
	public static function get_downloads_count( $post = null ) {
		$post = get_post( $post );

		if ( false === ( $count = wp_cache_get( $post->ID, 'plugin_download_count' ) ) ) {
			global $wpdb;

			// TODO: While the plugin ZIPs are still being served by bbPress, the download stats are stored there.
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT downloads FROM `" . PLUGINS_TABLE_PREFIX . "download_counts` WHERE topic_id = (SELECT topic_id FROM `" . PLUGINS_TABLE_PREFIX . "topics` WHERE topic_slug = %s )", $post->post_name ) );

			wp_cache_set( $post->ID, $count, 'plugin_download_count', HOUR_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * Returns the cumulative number of downloads of all plugins.
	 *
	 * @static
	 * @global \wpdb $wpdb WordPress database abstraction object.
	 *
	 * @return int
	 */
	public static function get_total_downloads() {
		if ( false === ( $count = wp_cache_get( 'plugin_download_count', 'plugin_download_count' ) ) ) {
			global $wpdb;

			$count = $wpdb->get_var( "SELECT SUM(downloads) FROM `" . PLUGINS_TABLE_PREFIX . "stats`" );
			wp_cache_set( 'plugin_download_count', $count, 'plugin_download_count', DAY_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * Displays a plugin's rating with the amount of ratings it has received.
	 *
	 * @static
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return string
	 */
	public static function get_star_rating( $post = null ) {
		$post = get_post( $post );

		$rating      = get_post_meta( $post->ID, 'rating', true ) ?: 0;
		$ratings     = get_post_meta( $post->ID, 'ratings', true ) ?: array();
		$num_ratings = array_sum( $ratings );

		return
			'<div class="plugin-rating" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">' .
				'<meta itemprop="ratingCount" content="' . esc_attr( $num_ratings ) . '"/>' .
				'<meta itemprop="ratingValue" content="' . esc_attr( $rating ) . '"/>' .
				Template::dashicons_stars( $rating ) .
				'<span class="rating-count">(' .
					'<a href="https://wordpress.org/support/plugin/' . $post->post_name . '/reviews/">' .
					sprintf(
						/* translators: 1: number of ratings */
						__( '%1$s<span class="screen-reader-text"> total ratings</span>' ),
						esc_html( $num_ratings )
					) .
				'</a>' .
				')</span>' .
			'</div>';
	}

	/**
	 * Returns the available sections for a plugin.
	 *
	 * @static
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return array
	 */
	public static function get_plugin_sections( $post = null ) {
		$plugin = get_post( $post );

		$default_sections = array(
			'description',
			'screenshots',
			'stats',
			'support',
			'reviews',
			'developers',
		);
		if ( ! get_post_meta( $plugin->ID, 'screenshots', true ) && ! get_post_meta( $plugin->ID, 'assets_screenshots', true ) ) {
			unset( $default_sections[ array_search( 'screenshots', $default_sections ) ] );
		}

		$raw_sections = get_post_meta( $plugin->ID, 'sections', true ) ?: array();
		$raw_sections = array_unique( array_merge( $raw_sections, $default_sections ) );

		$sections  = array();
		$title     = $url = '';
		$permalink = get_permalink();

		foreach ( $raw_sections as $section_slug ) {
			switch ( $section_slug ) {

				case 'description':
					$title = _x( 'Description', 'plugin tab title', 'wporg-plugins' );
					$url   = $permalink;
					break;

				case 'installation':
					$title = _x( 'Installation', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $permalink ) . $section_slug . '/';
					break;

				case 'faq':
					$title = _x( 'FAQ', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $permalink ) . $section_slug . '/';
					break;

				case 'screenshots':
					$title = _x( 'Screenshots', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $permalink ) . $section_slug . '/';
					break;

				case 'changelog':
					$title = _x( 'Changelog', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $permalink ) . $section_slug . '/';
					break;

				case 'stats':
					$title = _x( 'Stats', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $permalink ) . $section_slug . '/';
					break;

				case 'support':
					$title = _x( 'Support', 'plugin tab title', 'wporg-plugins' );
					$url   = 'https://wordpress.org/support/plugin/' . $plugin->post_name;
					break;

				case 'reviews':
					$title = _x( 'Reviews', 'plugin tab title', 'wporg-plugins' );
					$url   = 'https://wordpress.org/support/plugin/' . $plugin->post_name . '/reviews/';
					break;

				case 'developers':
					$title = _x( 'Contributors &amp; Developers', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $permalink ) . '/' . $section_slug . '/';
					break;

				case 'other_notes':
					$title = _x( 'Other Notes', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $permalink ) . '/' . $section_slug . '/';
					break;

				default:
					continue;
			}

			$sections[] = array(
				'slug'  => $section_slug,
				'url'   => $url,
				'title' => $title,
			);
		}

		return $sections;
	}

	/**
	 * Retrieve the Plugin Icon details for a plugin.
	 *
	 * @static
	 *
	 * @param int|\WP_Post|null $post   Optional. Post ID or post object. Defaults to global $post.
	 * @param string            $output Optional. Output type. 'html' or 'raw'. Default: 'raw'.
	 * @return mixed
	 */
	public static function get_plugin_icon( $post = null, $output = 'raw' ) {
		$plugin    = get_post( $post );
		$raw_icons = get_post_meta( $plugin->ID, 'assets_icons', true ) ?: array();

		$icon = $icon_2x = $svg = $generated = false;
		foreach ( $raw_icons as $file => $info ) {
			switch ( $info['resolution'] ) {
				case '256x256':
					$icon_2x = self::get_asset_url( $plugin, $info );
					break;

				case '128x128':
					$icon = self::get_asset_url( $plugin, $info );
					break;

				/* false = the resolution of the icon, this is NOT disabled */
				case false && 'icon.svg' == $file:
					$svg = self::get_asset_url( $plugin, $info );
					break;
			}
		}

		// Fallback to SVG if it exists.
		if ( ! $icon && $svg ) {
			$icon = $svg;
		}

		// Fallback to 2x if it exists.
		if ( ! $icon && $icon_2x ) {
			$icon = $icon_2x;
		}

		if ( ! $icon ) {
			$generated = true;

			$icon = new Plugin_Geopattern;
			$icon->setString( $plugin->post_name );

			// Use the average color of the first known banner as the icon background color
			if ( $color = get_post_meta( $plugin->ID, 'assets_banners_color', true ) ) {
				if ( strlen( $color ) === 6 && strspn( $color, 'abcdef0123456789' ) === 6 ) {
					$icon->setColor( '#' . $color );
				}
			}

			$icon = $icon->toDataURI();
		}

		switch ( $output ) {
			case 'html':
				$id    = "plugin-icon-{$plugin->post_name}";
				$html  = "<style type='text/css'>";
				$html .= "#{$id} { background-image: url('{$icon}'); } .plugin-icon { background-size: cover; height: 128px; width: 128px; }";
				if ( ! empty( $icon_2x ) && ! $generated ) {
					$html .= "@media only screen and (-webkit-min-device-pixel-ratio: 1.5), only screen and (min-resolution: 144dpi) { #{$id} { background-image: url('{$icon_2x}'); } }";
				}
				$html .= "</style>";
				$html .= "<div class='plugin-icon' id='{$id}'></div>";

				return $html;
				break;

			case 'raw':
			default:
				return compact( 'svg', 'icon', 'icon_2x', 'generated' );
		}
	}

	/**
	 * Retrieve the Plugin Icon details for a plugin.
	 *
	 * @static
	 *
	 * @param int|\WP_Post|null $post   Optional. Post ID or post object. Defaults to global $post.
	 * @param string            $output Optional. Output type. 'html' or 'raw'. Default: 'raw'.
	 * @return mixed
	 */
	public static function get_plugin_banner( $post = null, $output = 'raw' ) {
		$plugin = get_post( $post );

		$banner      = $banner_2x = false;
		$raw_banners = get_post_meta( $plugin->ID, 'assets_banners', true ) ?: array();

		// Split in rtl and non-rtl banners.
		$rtl_banners = array_filter( $raw_banners, function ( $info ) {
			return (bool) stristr( $info['filename'], '-rtl' );
		} );
		$raw_banners = array_diff_key( $raw_banners, $rtl_banners );

		// Default are non-rtl banners.
		foreach ( $raw_banners as $info ) {
			switch ( $info['resolution'] ) {
				case '1544x500':
					$banner_2x = self::get_asset_url( $plugin, $info );
					break;

				case '772x250':
					$banner = self::get_asset_url( $plugin, $info );
					break;
			}
		}

		if ( is_rtl() ) {
			foreach ( $rtl_banners as $info ) {
				switch ( $info['resolution'] ) {
					case '1544x500':
						$banner_2x = self::get_asset_url( $plugin, $info );
						break;

					case '772x250':
						$banner = self::get_asset_url( $plugin, $info );
						break;
				}
			}
		}

		if ( ! $banner ) {
			return false;
		}

		switch ( $output ) {
			case 'html':
				$id    = "plugin-banner-{$plugin->post_name}";
				$html  = "<style type='text/css'>";
				$html .= "#{$id} { background-image: url('{$banner}'); }";
				if ( ! empty( $banner_2x ) ) {
					$html .= "@media only screen and (-webkit-min-device-pixel-ratio: 1.5), only screen and (min-resolution: 144dpi) { #{$id} { background-image: url('{$banner_2x}'); } }";
				}
				$html .= "</style>";
				$html .= "<div class='plugin-banner' id='{$id}'></div>";

				return $html;
				break;

			case 'raw':
			default:
				return compact( 'banner', 'banner_2x' );
		}
	}

	/**
	 * Generates and returns the URL to a passed asset.
	 *
	 * Assets can be screenshots, icons, banners, etc.
	 *
	 * @static
	 *
	 * @param int|\WP_Post|null $post  Optional. Post ID or post object. Defaults to global $post.
	 * @param array             $asset Assets folder information.
	 * @param bool              $cdn   Optional. If the url should be CDN'ised. Default true.
	 * @return string
	 */
	public static function get_asset_url( $post, $asset, $cdn = true ) {
		if ( ! empty( $asset['location'] ) && 'plugin' == $asset['location'] ) {

			// Screenshots in the plugin folder - /plugins/plugin-name/screenshot-1.png.
			$format = 'https://plugins.svn.wordpress.org/!svn/bc/%1$s/%2$s/trunk/%3$s';
		} else {

			// Images in the assets folder - /plugin-name/assets/screenshot-1.png.
			$format = 'https://plugins.svn.wordpress.org/!svn/bc/%1$s/%2$s/assets/%3$s';
		}

		// Photon does not support SVG files. https://github.com/Automattic/jetpack/issues/81
		if ( strpos( $asset['filename'], '.svg' ) ) {
			$cdn = false;
		}

		$url = sprintf(
			$format,
			$asset['revision'],
			get_post( $post )->post_name,
			$asset['filename']
		);

		// Use Jetpacks Photon CDN when available.
		if ( $cdn && function_exists( 'jetpack_photon_url' ) ) {
			$url = jetpack_photon_url( $url, array( 'strip' => 'all' ) );
		}

		return esc_url( $url );
	}

	/**
	 * A helper method to create dashicon stars.
	 *
	 * @static
	 *
	 * @param int|array $args {
	 *    If numeric arg passed, assumed to be 'rating'.
	 *
	 *    @type int    $rating   The rating to display.
	 *    @type string $template The HTML template to use for each star.
	 *                           %1$s is the class, %2$s is the rating.
	 * }
	 * @return string The Rating HTML.
	 */
	public static function dashicons_stars( $args = array() ) {
		$args = wp_parse_args( ( is_numeric( $args ) ? array( 'rating' => $args ) : $args ), array(
			'rating'   => 0,
			'template' => '<span class="%1$s"></span>',
		) );

		$rating         = round( $args['rating'] / 0.5 ) * 0.5;
		$template       = $args['template'];
		$title_template = __( '%s out of 5 stars', 'wporg-plugins' );
		$title          = sprintf( $title_template, $rating );

		$output  = '<div class="wporg-ratings" aria-label="' . esc_attr( $title ) . '" data-title-template="' . esc_attr( $title_template ) . '" data-rating="' . esc_attr( $rating ) . '" style="color:#ffb900;">';
		$counter = round( $rating * 2 );
		for ( $i = 1; $i <= 5; $i++ ) {
			switch ( $counter ) {
				case 0:
					$output .= sprintf( $template, 'dashicons dashicons-star-empty', $i );
					break;

				case 1:
					$output .= sprintf( $template, 'dashicons dashicons-star-half', $i );
					$counter--;
					break;

				default:
					$output  .= sprintf( $template, 'dashicons dashicons-star-filled', $i );
					$counter -= 2;
					break;
			}
		}
		$output .= '</div>';

		return $output;
	}

	/**
	 * Generate a download link for a given plugin & version.
	 *
	 * @param int|\WP_Post|null $post    Optional. Post ID or post object. Defaults to global $post.
	 * @param string            $version The version to link to. Optional. Default: latest.
	 * @return string The Download URL.
	 */
	static function download_link( $post = null, $version = 'latest' ) {
		$post = get_post( $post );

		if ( 'latest' == $version || 'latest-stable' == $version ) {
			$version = get_post_meta( $post->ID, 'stable_tag', true ) ?: 'trunk';
		}

		if ( 'trunk' != $version ) {
			return sprintf( "https://downloads.wordpress.org/plugin/%s.%s.zip", $post->post_name, $version );
		} else {
			return sprintf( "https://downloads.wordpress.org/plugin/%s.zip", $post->post_name );
		}
	}

	/**
	 * Properly encodes a string to UTF-8.
	 *
	 * @static
	 *
	 * @param string $string
	 * @return string
	 */
	public static function encode( $string ) {
		$string = mb_convert_encoding( $string, 'UTF-8', 'ASCII, JIS, UTF-8, Windows-1252, ISO-8859-1' );

		return ent2ncr( htmlspecialchars_decode( htmlentities( $string, ENT_NOQUOTES, 'UTF-8' ), ENT_NOQUOTES ) );
	}

	/**
	 * Generates a link to toggle a plugin favorites state.
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @param mixed             $user The user to alter the favorite status for.
	 * @return string URL to toggle status.
	 */
	public static function get_favorite_link( $post = null, $user = 0 ) {
		$post = get_post( $post );

		$favorited = Tools::favorited_plugin( $post, $user );

		return add_query_arg( array(
			'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			( $favorited ? 'unfavorite' : 'favorite' ) => '1'
		), home_url( 'wp-json/plugins/v1/plugin/' . $post->post_name . '/favorite' ) );
	}
}
