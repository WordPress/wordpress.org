<?php
namespace WordPressdotorg\Plugin_Directory;

// Explicitly require dependencies so this file can be sourced outside the Plugin Directory.
require_once( __DIR__ . '/class-plugin-geopattern.php' );
require_once( __DIR__ . '/class-plugin-geopattern-svg.php' );
require_once( __DIR__ . '/class-plugin-geopattern-svgtext.php' );

/**
 * Various helpers to retrieve data not stored within WordPress.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Template {

	/**
	 * Prints markup information in the head of a page.
	 *
	 * @link http://schema.org/SoftwareApplication
	 * @link https://developers.google.com/search/docs/data-types/software-apps
	 *
	 * @static
	 */
	public static function json_ld_schema() {
		// Schema for the front page.
		if ( is_front_page() ) :
			echo PHP_EOL;
			?>
<script type="application/ld+json">
	{
		"@context": "http://schema.org",
		"@type": "WebSite",
		"name": <?php echo wp_json_encode( __( 'WordPress Plugins', 'wporg-plugins' ) ); ?>,
		"url": <?php echo wp_json_encode( home_url( '/' ) ); ?>,
		"potentialAction": [
			{
				"@type": "SearchAction",
				"target": <?php echo wp_json_encode( home_url( '?s={search_term_string}' ) ); ?>,
				"query-input": "required name=search_term_string"
			}
		]
	}
</script>
			<?php
		endif;

		// Schema for plugin pages.
		if ( is_singular( 'plugin' ) ) :
			$plugin = get_queried_object();

			$rating      = get_post_meta( $plugin->ID, 'rating', true ) ?: 0;
			$ratings     = get_post_meta( $plugin->ID, 'ratings', true ) ?: [];
			$num_ratings = array_sum( $ratings );

			echo PHP_EOL;
			?>
<script type="application/ld+json">
	[
		{
			"@context": "http://schema.org",
			"@type": "BreadcrumbList",
			"itemListElement": [
				{
					"@type": "ListItem",
					"position": 1,
					"item": {
						"@id": "https://wordpress.org/",
						"name": "WordPress"
					}
				},
				{
					"@type": "ListItem",
					"position": 2,
					"item": {
						"@id": <?php echo wp_json_encode( home_url( '/' ) ); ?>,
						"name": <?php echo wp_json_encode( __( 'WordPress Plugins', 'wporg-plugins' ) ) . PHP_EOL; ?>
					}
				}
			]
		},
		{
			"@context": "http://schema.org",
			"@type": "SoftwareApplication",
			"applicationCategory": "http://schema.org/OtherApplication",
			"name": <?php echo wp_json_encode( get_the_title( $plugin ) ); ?>,
			"description": <?php echo wp_json_encode( get_the_excerpt( $plugin ) ); ?>,
			"softwareVersion": <?php echo wp_json_encode( $plugin->version ); ?>,
			"fileFormat": "application/zip",
			"downloadUrl": <?php echo wp_json_encode( self::download_link( $plugin ) ); ?>,
			"dateModified": <?php echo wp_json_encode( get_post_modified_time( 'c', false, $plugin ) ); ?>,
			"aggregateRating": {
				"@type": "AggregateRating",
				"worstRating": 0,
				"bestRating": 5,
				"ratingValue": <?php echo wp_json_encode( $rating ); ?>,
				"ratingCount": <?php echo wp_json_encode( $num_ratings ); ?>,
				"reviewCount": <?php echo wp_json_encode( $num_ratings ) . PHP_EOL; ?>
			},
			"interactionStatistic": {
				"@type": "InteractionCounter",
				"interactionType": "http://schema.org/DownloadAction",
				"userInteractionCount": <?php echo wp_json_encode( self::get_downloads_count( $plugin ) ) . PHP_EOL; ?>
			},
			"offers": {
				"@type": "Offer",
				"price": "0.00",
				"priceCurrency": "USD",
				"seller": {
					"@type": "Organization",
					"name": "WordPress.org",
					"url": "https://wordpress.org"
				}
			}
		}
	]
</script>
			<?php
		endif;
	}

	/**
	 * Prints meta description in the head of a page.
	 *
	 * @static
	 */
	public static function meta_description() {
		if ( is_singular( 'plugin' ) ) {
			printf( '<meta name="description" value="%s"/>',
				esc_attr( get_the_excerpt( get_queried_object() ) )
			);
		}
	}

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
			$text = __( 'Fewer than 10', 'wporg-plugins' );
		} elseif ( $count >= 1000000 ) {
			$million_count = intdiv( $count, 1000000 );
			/* translators: %d: The integer number of million active installs */
			$text = sprintf( _n( '%d+ million', '%d+ million', $million_count, 'wporg-plugins' ), $million_count );
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
			'<div class="plugin-rating">' .
				Template::dashicons_stars( $rating ) .
				'<span class="rating-count">(' .
					'<a href="https://wordpress.org/support/plugin/' . $post->post_name . '/reviews/">' .
					sprintf(
						/* translators: 1: number of ratings */
						__( '%1$s<span class="screen-reader-text"> total ratings</span>', 'wporg-plugins' ),
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
			$format = 'https://plugins.svn.wordpress.org/%1$s/trunk/%2$s';
		} else {

			// Images in the assets folder - /plugin-name/assets/screenshot-1.png.
			$format = 'https://plugins.svn.wordpress.org/%1$s/assets/%2$s';
		}

		$url = sprintf(
			$format,
			get_post( $post )->post_name,
			$asset['filename']
		);

		if ( $cdn ) {
			$url = str_replace( 'plugins.svn.wordpress.org', 'ps.w.org', $url );
		}

		// Add a cache-buster based on the file revision.
		$url = add_query_arg( 'rev', $asset['revision'], $url );

		return esc_url_raw( $url );
	}

	/**
	 * Returns the URL to the plugin support forum.
	 *
	 * @static
	 *
	 * @param int|\WP_Post|null $post  Optional. Post ID or post object. Defaults to global $post.
	 * @return string
	 */
	public static function get_support_url( $post = null ) {
		$post = get_post( $post );

		/*
		* bbPress and BuddyPress get special treatment here.
		* In the future we could open this up to all plugins that define a custom support URL.
		*/
		if ( 'buddypress' === $post->post_name ) {
			$url = 'https://buddypress.org/support/';
		} elseif ( 'bbpress' === $post->post_name ) {
			$url = 'https://bbpress.org/forums/';
		} else {
			$url = 'https://wordpress.org/support/plugin/' . $post->post_name;
		}

		return $url;
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
	public static function download_link( $post = null, $version = 'latest' ) {
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

	/**
	 * Adds hreflang link attributes to WordPress.org pages.
	 *
	 * @link https://support.google.com/webmasters/answer/189077?hl=en Use hreflang for language and regional URLs.
	 * @link https://sites.google.com/site/webmasterhelpforum/en/faq-internationalisation FAQ: Internationalisation.
	 */
	public static function hreflang_link_attributes() {
		global $wpdb;

		if ( ! get_post() ) {
			return;
		}

		wp_cache_add_global_groups( array( 'locale-associations' ) );

		if ( false === ( $sites = wp_cache_get( 'local-sites-'.get_post()->post_name, 'locale-associations' ) ) ) {

			// get subdomain/locale associations
			$subdomains = $wpdb->get_results( 'SELECT locale, subdomain FROM locales', OBJECT_K ); 

			$sites = Plugin_I18n::instance()->get_locales();

			require_once GLOTPRESS_LOCALES_PATH;

			foreach ( $sites as $key => $site ) {
				$gp_locale = \GP_Locales::by_field( 'wp_locale', $site->wp_locale );
				if ( empty( $gp_locale ) ) {
					unset( $sites[ $key ] );
					continue;
				}

				$sites[ $key ]->subdomain = $subdomains[ $site->wp_locale ]->subdomain;

				// Note that Google only supports ISO 639-1 codes.
				if ( isset( $gp_locale->lang_code_iso_639_1 ) && isset( $gp_locale->country_code ) ) {
					$hreflang = $gp_locale->lang_code_iso_639_1 . '-' . $gp_locale->country_code;
				} elseif ( isset( $gp_locale->lang_code_iso_639_1 ) ) {
					$hreflang = $gp_locale->lang_code_iso_639_1;
				} elseif ( isset( $gp_locale->lang_code_iso_639_2 ) ) {
					$hreflang = $gp_locale->lang_code_iso_639_2;
				} elseif ( isset( $gp_locale->lang_code_iso_639_3 ) ) {
					$hreflang = $gp_locale->lang_code_iso_639_3;
				}

				if ( $hreflang ) {
					$sites[ $key ]->hreflang = strtolower( $hreflang );
				} else {
					unset( $sites[ $key ] );
				}
			}

			// Add en_US to the list of sites.
			$sites['en_US'] = (object) array(
				'locale'    => 'en_US',
				'hreflang'  => 'en',
				'subdomain' => ''
			);

			uasort( $sites, function( $a, $b ) {
				return strcasecmp( $a->hreflang, $b->hreflang );
			} );

			wp_cache_set( 'local-sites-'.get_post()->post_name, $sites, 'locale-associations', DAY_IN_SECONDS );
		}

		foreach ( $sites as $site ) {
			$url = sprintf(
				'https://%swordpress.org%s',
				$site->subdomain ? "{$site->subdomain}." : '',
				$_SERVER[ 'REQUEST_URI' ]
			);

			printf(
				'<link rel="alternate" href="%s" hreflang="%s" />' . "\n",
				esc_url( $url ),
				esc_attr( $site->hreflang )
			);
		}
	}
}
