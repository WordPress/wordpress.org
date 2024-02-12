<?php
/**
 * Plugin Name: WordPress.org Site Branding
 * Description: Ensures that <title> tags on WordPress.org always include WordPress, and WordPress favicons.
 */

namespace WordPressdotorg\MU_Plugins\Site_Branding {

	/**
	 * Filter the document title parts to ensure that WordPress is replaced into it consistently.
	 */
	function document_title_parts( $parts ) {
		global $rosetta;

		// In some places on WordPress.org the document title is used within the theme, don't affect title calls after the </head>.
		if ( did_action( 'wp_body_open' ) ) {
			return $parts;
		}

		$combined = implode( ' ', $parts );

		// Ensure that 'WordPress' is present in the title of the URL
		if ( false === strpos( $combined, 'WordPress' ) ) {
			$parts['wporg-suffix'] = get_wordpress_brand();
		}

		// Override anything that set part of the title directly to WordPress.org on rosetta sites.
		if ( isset( $rosetta ) ) {
			foreach ( [ 'site', 'tagline' ] as $field ) {
				if ( ! empty( $parts[ $field ] ) && 'WordPress.org' === $parts[ $field ] ) {
					$parts[ $field ] = get_wordpress_brand();
				}
			}
		}

		return $parts;
	}
	add_filter( 'document_title_parts', __NAMESPACE__ . '\document_title_parts', 100 );

	/**
	 * Always suffix the WordPress brand to bbPress titles.
	 */
	function bbp_title( $title ) {
		return $title . get_wordpress_brand();
	}
	add_filter( 'bbp_title', __NAMESPACE__ . '\bbp_title', 100 );

	/**
	 * Filter Jetpack opengraph tags to reference the localised WordPress.org site.
	 */
	function jetpack_open_graph_tags( $fields ) {
		if ( isset( $fields['og:site_name'] ) && 'WordPress.org' === $fields['og:site_name'] ) {
			$fields['og:site_name'] = get_wordpress_brand();
		}

		// Don't use our default Site Logo for default Jetpack Opengraph fields.
		$default_site_logo = get_site_icon_url(); // Namespaced filter, not the WordPress function.
		if ( $default_site_logo ) {
			if ( ! empty( $fields['og:image'] ) && $fields['og:image'] === $default_site_logo ) {
				$fields['og:image'] = jetpack_open_graph_image_default();
				unset( $fields['og:image:width'], $fields['og:image:height'], $fields['og:image:secure_url'] );
			}

			if ( ! empty( $fields['twitter:image'] ) && $fields['twitter:image'] === $default_site_logo ) {
				$fields['twitter:image'] = jetpack_twitter_cards_image_default();
			}
		}

		/*
		* Jetpack extracts image URLs like they are used in the content which leads
		* to blurry previews if they are too small.
		* This removes the size part of the image URL so the full URL is used.
		*/
		$strip_size = function( $url ) {
			return preg_replace( '/-\d+x\d+(\..+$)/', '$1', $url, 1 );
		};
		foreach ( [ 'og:image', 'og:image:secure_url', 'twitter:image' ] as $field ) {
			if ( isset( $fields[ $field ] ) ) {
				$fields[ $field ] = is_string( $fields[ $field ] ) ?
					$strip_size( $fields[ $field ] ) :
					array_map( $strip_size, $fields[ $field ] );
			}
		}

		return $fields;
	}
	add_filter( 'jetpack_open_graph_tags', __NAMESPACE__ . '\jetpack_open_graph_tags', 100 );

	/**
	 * Set a default og:image image.
	 */
	function jetpack_open_graph_image_default() {
		return 'https://s.w.org/images/home/wordpress-default-ogimage.png';
	}
	add_filter( 'jetpack_open_graph_image_default', __NAMESPACE__ . '\jetpack_open_graph_image_default' );
	
	/**
	 * To prevent a cropped version of the og:image on Twitter, provide a square version.
	 */
	function jetpack_twitter_cards_image_default() {
		return 'https://s.w.org/images/home/wordpress-default-image-square.png';
	}
	add_filter( 'jetpack_twitter_cards_image_default', __NAMESPACE__ . '\jetpack_twitter_cards_image_default' );

	/**
	 * Customize the Twitter username used as "twitter:site" Twitter Card Meta Tag.
	 *
	 * This username will also be appended to tweets launched by the tweet button.
	 *
	 * @param string $handle Twitter Username.
	 */
	function jetpack_twitter_cards_site_tag( $handle ) {
		return $handle ?: 'WordPress';
	}
	add_filter( 'jetpack_twitter_cards_site_tag', __NAMESPACE__ . '\jetpack_twitter_cards_site_tag' );

	/**
	 * Return the 'Brand' of the WordPress.org site.
	 * 
	 * This is "WordPress.org" or a localised variant such as "WordPress Deutch".
	 */
	function get_wordpress_brand() {
		global $rosetta;

		if ( ! isset( $rosetta ) ) {
			return 'WordPress.org';
		}

		$root_id = $rosetta->get_root_site_id();
		$name    = get_blog_option( $root_id, 'blogname' );

		if ( false !== strpos( $name, 'WordPress' ) ) {
			return $name;
		}

		return "WordPress.org $name";
	}

	/**
	 * Set a default Site Icon if one is not set.
	 * 
	 * Causes the icon to be set for all WordPress.org themes.
	 * 
	 * NOTE: See the above `jetpack_open_graph_tags()` function for where this is overridden for OpenGraph.
	 */
	function get_site_icon_url( $url = '', $size = 0 ) {
		// Return the favicon for the 32px variety if needed, the wmark image is just a higher resolution variant.
		if ( 32 === $size ) {
			return $url ?: 'https://s.w.org/favicon.ico?2';
		}

		return $url ?: 'https://s.w.org/images/wmark.png';
	}
	add_filter( 'get_site_icon_url', __NAMESPACE__ . '\get_site_icon_url', 10, 2 );
}

namespace WordPressdotorg {
	/**
	 * Function to call as `\WordPressdotorg\site_brand()` in other plugins.
	 */
	function site_brand() {
		return \WordPressdotorg\MU_Plugins\Site_Branding\get_wordpress_brand();
	}
}
