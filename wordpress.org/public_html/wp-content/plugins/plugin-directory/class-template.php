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
	 * @param bool $full whether to include "actuve installs" suffix. Default: true.
	 * @return string "1+ million" or "1+ milllion active installs" depending on $full.
	 */
	static function active_installs( $full = true, $post = null ) {
		$post = get_post( $post );

		$count = get_post_meta( $post->ID, 'active_installs', true );
	
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
	 * @param \WP_Post|int $post Optional.
	 * @return int
	 */
	static function get_downloads_count( $post = null ) {
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
	 * @return int
	 */
	static function get_total_downloads() {
		if ( false === ( $count = wp_cache_get( 'plugin_download_count', 'plugin_download_count' ) ) ) {
			global $wpdb;

			$count = $wpdb->get_var( "SELECT SUM(downloads) FROM `" . PLUGINS_TABLE_PREFIX . "stats`" );
			wp_cache_set( 'plugin_download_count', $count, 'plugin_download_count', DAY_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * @return array
	 */
	static function get_plugin_sections( $post = null ) {
		$plugin      = get_post( $post );
		$plugin_slug = $plugin->post_name;

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

		$raw_sections = get_post_meta( $plugin->ID, 'sections', true );
		$raw_sections = array_unique( array_merge( $raw_sections, $default_sections ) );

		$sections     = array();
		$title = $url = '';
		$permalink    = get_permalink();

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
					$url   = 'https://wordpress.org/support/plugin/' . $plugin_slug;
					break;

				case 'reviews':
					$title = _x( 'Reviews', 'plugin tab title', 'wporg-plugins' );
					$url   = 'https://wordpress.org/support/view/plugin-reviews/' . $plugin_slug;
					break;

				case 'developers':
					$title = _x( 'Developers', 'plugin tab title', 'wporg-plugins' );
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
	 * @param \WP_Post|string $plugin An instance of a Plugin post, or the plugin slug.
	 * @param string          $output Output type. 'html' or 'raw'. Default: 'raw'.
	 * @return mixed
	 */
	static function get_plugin_icon( $plugin, $output = 'raw' ) {
		$plugin = Plugin_Directory::instance()->get_plugin_post( $plugin );
		if ( ! $plugin ) {
			return false;
		}
		$plugin_slug = $plugin->post_name;

		$raw_icons = get_post_meta( $plugin->ID, 'assets_icons', true );

		$icon = $icon_2x = $svg = $generated = false;
		foreach ( $raw_icons as $file => $info ) {
			switch ( $info['resolution'] ) {
				case '256x256':
					$icon_2x = self::get_asset_url( $plugin_slug, $info );
					break;

				case '128x128':
					$icon = self::get_asset_url( $plugin_slug, $info );
					break;

				/* false = the resolution of the icon, this is NOT disabled */
				case false && 'icon.svg' == $file:
					$svg   = self::get_asset_url( $plugin_slug, $info );
					break;
			}
		}

		if ( ! $icon && $svg ) {
			$icon = $svg;
		}

		if ( ! $icon ) {
			$generated = true;

			$icon = new Plugin_Geopattern;
			$icon->setString( $plugin->post_name );

			// Use the average color of the first known banner as the icon background color
			if ( $color = get_post_meta( $plugin->ID, 'assets_banners_color', true ) ) {
				if ( strlen( $color ) == 6 && strspn( $color, 'abcdef0123456789' ) == 6 ) {
					$icon->setColor( '#' . $color );
				}
			}

			$icon = $icon->toDataURI();
		}

		switch ( $output ) {
			case 'html':
				$id   = "plugin-icon-{$plugin_slug}";
				$html = "<style type='text/css'>";
				$html .= "#{$id} { width:128px; height:128px; background-image: url('{$icon}'); background-size:128px 128px; }";
				if ( ! empty( $icon_2x ) && ! $generated ) {
					$html .= "@media only screen and (-webkit-min-device-pixel-ratio: 1.5) { #{$id} { background-image: url('{$icon_2x}'); } }";
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
	 * @param \WP_Post|string $plugin An instance of a Plugin post, or the plugin slug.
	 * @param string          $output Output type. Default: 'raw'.
	 * @return mixed
	 */
	static function get_plugin_banner( $plugin, $output = 'raw' ) {
		$plugin = Plugin_Directory::instance()->get_plugin_post( $plugin );
		if ( ! $plugin ) {
			return false;
		}
		$plugin_slug = $plugin->post_name;

		$raw_banners = get_post_meta( $plugin->ID, 'assets_banners', true );

		$banner = $banner_2x = false;
		foreach ( $raw_banners as $file => $info ) {
			switch ( $info['resolution'] ) {
				case '1544x500':
					$banner_2x = self::get_asset_url( $plugin_slug, $info );
					break;

				case '772x250':
					$banner = self::get_asset_url( $plugin_slug, $info );
					break;
			}
		}

		if ( ! $banner ) {
			return false;
		}

		switch ( $output ) {
			case 'raw':
			default:
				return compact( 'banner', 'banner_2x' );
		}
	}

	/**
	 * @param $plugin
	 * @param $asset
	 * @return string
	 */
	static function get_asset_url( $plugin, $asset ) {
		if ( ! empty( $asset['location'] ) && 'plugin' == $asset['location'] ) {
			// Screenshots in the plugin folder - /plugins/plugin-name/screenshot-1.png
			$format = 'https://s.w.org/plugins/%s/%s?rev=%s';
		} else {
			// Images in the assets folder - /plugin-name/assets/screenshot-1.png
			$format = 'https://ps.w.org/%s/assets/%s?rev=%s';
		}

		return esc_url( sprintf(
			$format,
			$plugin,
			$asset['filename'],
			$asset['revision']
		) );
	}

	/**
	 * A helper method to create dashicon stars.
	 *
	 * @type int|array {
	 *    If numeric arg passed, assumed to be 'rating'.
	 *
	 *    @type int    $rating   The rating to display.
	 *    @type string $template The HTML template to use for each star.
	 *                           %1$s is the class, %2$s is the rating.
	 * }
	 * @return string The Rating HTML.
	 */
	static function dashicons_stars( $args = array() ) {
		$defaults = array(
			'rating' => 0,
			'template' => '<span class="%1$s"></span>'
		);
		$r = wp_parse_args( ( is_numeric( $args ) ? array( 'rating' => $args ) : $args ), $defaults );

		$rating = round( $r['rating'] / 0.5 ) * 0.5;
		$template = $r['template'];
		$title_template = __( '%s out of 5 stars', 'wporg-plugins' );
		$title = sprintf( $title_template, $rating );

		$output = '<div class="wporg-ratings" title="' . esc_attr( $title ) . '" data-title-template="' . esc_attr( $title_template ) . '" data-rating="' . esc_attr( $rating ) . '" style="color:#ffb900;">';
		$counter = round( $rating * 2 );
		for  ( $i = 1; $i <= 5; $i++ ) {
			switch ($counter) {
			case 0:
				$output .= sprintf( $template, 'dashicons dashicons-star-empty', $i );
				break;
			case 1:
				$output .= sprintf( $template, 'dashicons dashicons-star-half', $i );
				$counter--;
				break;
			default:
				$output .= sprintf( $template, 'dashicons dashicons-star-filled', $i );
				$counter -= 2;
				break;
			}
		}
		$output .= '</div>';
		return $output;
	}

	/**
	 * Generate a Download link for a given plugin & version.
	 *
	 * @param \WP_Post $post    The Plugin Post.
	 * @param string   $version The version to link to. Optional. Default: latest.
	 * @return string The Download URL.
	 */
	static function download_link( $post = null, $version = 'latest' ) {
		$post = get_post( $post );

		if ( 'latest' == $version || 'latest-stable' == $version ) {
			$version = get_post_meta( $post->ID, 'stable_tag', true );
		}

		if ( 'trunk' != $version ) {
			return sprintf( "https://downloads.wordpress.org/plugin/%s.%s.zip", $post->post_name, $version );
		} else {
			return sprintf( "https://downloads.wordpress.org/plugin/%s.zip", $post->post_name );
		}
	}
}
