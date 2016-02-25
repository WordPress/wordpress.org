<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 * Various helpers to retrieve data not stored within WordPress.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Template {

	/**
	 * @param string $plugin_slug
	 * @return int
	 */
	static function get_active_installs_count( $plugin_slug ) {
		if ( false === ( $count = wp_cache_get( $plugin_slug, 'plugin_active_installs' ) ) ) {
			global $wpdb;

			$count = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT count FROM rev2_daily_stat_summary WHERE type = 'plugin' AND type_name = %s AND stat = 'active_installs' LIMIT 1",
				$plugin_slug
			) );
			wp_cache_add( $plugin_slug, $count, 'plugin_active_installs', 1200 );
		}

		if ( $count < 10 ) {
			return 0;
		}

		if ( $count >= 1000000 ) {
			return 1000000;
		}

		return strval( $count )[0] * pow( 10, floor( log10( $count ) ) );
	}

	/**
	 * @return int
	 */
	static function get_total_downloads() {
		if ( false === ( $count = wp_cache_get( 'plugin_download_count', 'plugin_download_count' ) ) ) {
			global $wpdb;

			$count = $wpdb->get_var( "SELECT SUM(downloads) FROM `plugin_2_stats`" );
			wp_cache_add( 'plugin_download_count', $count, 'plugin_download_count', DAY_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * @return array
	 */
	static function get_plugin_sections() {
		$plugin       = get_post();
		$plugin_slug  = $plugin->post_name;

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

		$sections  = array();
		$title     = '';
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
	 * @param WP_Post|string $plugin An instance of a Plugin post, or the plugin slug.
	 * @return mixed
	 */
	static function get_plugin_icon( $plugin, $output = 'raw' ) {
		$plugin = Plugin_Directory::instance()->get_plugin_post( $plugin );
		if ( ! $plugin ) {
			return false;
		}
		$plugin_slug = $plugin->post_name;

		$raw_icons = get_post_meta( $plugin->ID, 'assets_icons', true );

		$icon = $icon_2x = $vector = $generated = false;
		foreach ( $raw_icons as $file => $info ) {
			switch ( $info['resolution'] ) {
				case '256x256':
					$icon_2x = self::get_asset_url( $plugin_slug, $info );
					break;
				case '128x128':
					$icon = self::get_asset_url( $plugin_slug, $info );
					break;
				case false && 'icon.svg' == $file:
					$icon = self::get_asset_url( $plugin_slug, $info );
					$vector = true;
					break;
			}
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
				$id = "plugin-icon-{$plugin_slug}";
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
				return compact( 'icon', 'icon_2x', 'vector', 'generated' );
		}
	}

	/**
	 * Retrieve the Plugin Icon details for a plugin.
	 *
	 * @param WP_Post|string $plugin An instance of a Plugin post, or the plugin slug.
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
}
