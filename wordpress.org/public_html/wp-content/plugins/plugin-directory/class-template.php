<?php
namespace WordPressdotorg\Plugin_Directory;

// Explicitly require dependencies so this file can be sourced outside the Plugin Directory.
require_once __DIR__ . '/class-plugin-geopattern.php';
require_once __DIR__ . '/class-plugin-geopattern-svg.php';
require_once __DIR__ . '/class-plugin-geopattern-svgtext.php';

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
		$schema = false;

		// Schema for the front page.
		if ( is_front_page() ) {
			$schema = [
				"@context" => "http://schema.org",
				"@type"    => "WebSite",
				"name"     => __( 'WordPress Plugins', 'wporg-plugins' ),
				"url"      => home_url( '/' ),
				"potentialAction" => [
					[
						"@type"       => "SearchAction",
						"target"      => home_url( '?s={search_term_string}' ),
						"query-input" => "required name=search_term_string"
					]
				]
			];

		// Schema for plugin pages.
		} elseif ( is_singular( 'plugin' ) && 'publish' === get_post_status( get_queried_object_id() ) ) {
			$schema = self::plugin_json_jd_schema( get_queried_object() );
		}

		// Print the schema.
		if ( $schema ) {
			echo PHP_EOL, '<script type="application/ld+json">', PHP_EOL;
			// Output URLs without escaping the slashes, and print it human readable.
			echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
			echo PHP_EOL, '</script>', PHP_EOL;
		}
	}

	/**
	 * Fetches JSON LD schema for a specific plugin.
	 *
	 * @static
	 *
	 * @param \WP_Post $plugin Plugin to output JSON LD Schema for.
	 * @return array Schema object.
	 */
	protected static function plugin_json_jd_schema( $plugin ) {
		if ( class_exists( '\WPORG_Ratings' ) ) {
			$rating  = \WPORG_Ratings::get_avg_rating( 'plugin', $plugin->post_name ) ?: 0;
			$ratings = \WPORG_Ratings::get_rating_counts( 'plugin', $plugin->post_name ) ?: [];
		} else {
			$rating  = get_post_meta( $plugin->ID, 'rating', true ) ?: 0;
			$ratings = get_post_meta( $plugin->ID, 'ratings', true ) ?: [];
		}

		$num_ratings = array_sum( $ratings );
		$images      = [];
		$banners     = self::get_plugin_banner( $plugin );
		$icons       = self::get_plugin_icon( $plugin );

		// First non-generated icon.
		if ( $icons && ! $icons['generated'] ) {
			foreach ( [ 'svg', 'icon_2x', 'icon' ] as $field ) {
				if ( !empty( $icons[ $field ] ) ) {
					$images[] = $icons[ $field ];
					break;
				}
			}
		}
		// Largest appropriate banner. rtl fields returned when is_rtl().
		if ( $banners ) {
			foreach ( [ 'banner_2x_rtl', 'banner_rtl', 'banner_2x', 'banner' ] as $field ) {
				if ( !empty( $banners[ $field ] ) ) {
					$images[] = $banners[ $field ];
					break;
				}
			}
		}

		$schema = [];

		// Add the Plugin 'SoftwareApplication' node.
		$software_application = [
			"@context"            => "http://schema.org",
			"@type"               => [
				"SoftwareApplication",
				"Product"
			],
			"applicationCategory" => "Plugin",
			"operatingSystem"     => "WordPress",
			"name"                => get_the_title( $plugin ),
			"url"                 => get_permalink( $plugin ),
			"description"         => get_the_excerpt( $plugin ),
			"softwareVersion"     => $plugin->version,
			"fileFormat"          => "application/zip",
			"downloadUrl"         => self::download_link( $plugin ),
			"dateModified"        => get_post_modified_time( 'c', false, $plugin ),
			"aggregateRating"     => [
				"@type"       => "AggregateRating",
				"worstRating" => 1,
				"bestRating"  => 5,
				"ratingValue" => $rating,
				"ratingCount" => $num_ratings,
				"reviewCount" => $num_ratings,
			],
			"interactionStatistic" => [
				"@type"                => "InteractionCounter",
				"interactionType"      => "http://schema.org/DownloadAction",
				"userInteractionCount" => self::get_downloads_count( $plugin ),
			],
			"image" => $images,
			"offers" => [
				"@type"         => "Offer",
				"url"           => get_permalink( $plugin ),
				"price"         => "0.00",
				"priceCurrency" => "USD",
				"seller"        => [
					"@type" => "Organization",
					"name"  => "WordPress.org",
					"url"   => "https://wordpress.org"
				]
			]
		];

		// Remove the aggregateRating node if there's no reviews.
		if ( ! $software_application['aggregateRating']['ratingCount'] ) {
			unset( $software_application['aggregateRating'] );
		}

		// Remove the images property if no images exist.
		if ( ! $software_application['image'] ) {
			unset( $software_application['image'] );
		}

		$schema[] = $software_application;

		return $schema;
	}

	/**
	 * Prints meta tags in the head of a page.
	 *
	 * @static
	 */
	public static function output_meta() {
		global $wp_query;

		$metas = [];

		if ( is_singular( 'plugin' ) ) {
			$metas[] = sprintf(
				'<meta name="description" value="%s" />',
				esc_attr( get_the_excerpt() )
			);
		}

		echo implode( "\n", $metas );
	}

	/**
	 * Whether the current request should be noindexed.
	 */
	public static function should_noindex_request( $noindex ) {
		if ( get_query_var( 'plugin_advanced' ) ) {
			$noindex = true;
		} elseif ( get_query_var( 'plugin_business_model' ) && get_query_var( 'browse' ) ) {
			$noindex = true;
		} elseif ( 'preview' == get_query_var( 'browse' ) ) {
			$noindex = true;
		} elseif ( is_singular( 'plugin' ) && self::is_plugin_outdated() ) {
			$noindex = true;
		}

		return $noindex;
	}

	/**
	 * Gets current major WP version to check against "Tested up to" value.
	 *
	 * @static
	 * @global string $wp_version WordPress version.
	 *
	 * @return float Current major WP version.
	 */
	public static function get_current_major_wp_version() {
		$current_version = '';

		// Assume the value stored in a constant (which is set on WP.org), if defined.
		if ( defined( 'WP_CORE_LATEST_RELEASE' ) && WP_CORE_LATEST_RELEASE ) {
			$current_version = substr( WP_CORE_LATEST_RELEASE, 0, 3 );
		}

		// Otherwise, use the version of the running WP instance.
		if ( empty( $current_version ) ) {
			global $wp_version;

			$current_version = substr( $wp_version, 0, 3 );

			// However, if the running WP instance appears to not be a release version, assume the latest stable version.
			if ( false !== strpos( $wp_version, '-' ) ) {
				$current_version = (float) $current_version - 0.1;
			}
		}

		return (float) $current_version;
	}

	/**
	 * Checks if the plugin was tested with the latest 3 major releases of WordPress.
	 *
	 * @static
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return bool True if the plugin is marked as tested, false otherwise.
	 */
	public static function is_plugin_outdated( $post = null ) {
		$tested_up_to             = (string) get_post_meta( get_post( $post )->ID, 'tested', true );
		$version_to_check_against = (string) ( self::get_current_major_wp_version() - 0.2 );

		return version_compare( $version_to_check_against, $tested_up_to, '>' );
	}

	/**
	 * Returns a string representing the number of active installations for an item.
	 *
	 * @static
	 *
	 * @param bool              $full Optional. Whether to include "active installations" suffix. Default: true.
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return string "1+ million" or "1+ million active installations" depending on $full.
	 */
	public static function active_installs( $full = true, $post = null ) {
		$post  = get_post( $post );
		$count = get_post_meta( $post->ID, 'active_installs', true ) ?: 0;

		if ( 'closed' === $post->post_status ) {
			$text = __( 'N/A', 'wporg-plugins' );
		} elseif ( $count < 10 ) {
			$text = __( 'Fewer than 10', 'wporg-plugins' );
		} elseif ( $count >= 1000000 ) {
			$million_count = intdiv( $count, 1000000 );
			/* translators: %d: The integer number of million active installs */
			$text = sprintf( _n( '%d+ million', '%d+ million', $million_count, 'wporg-plugins' ), $million_count );
		} else {
			$text = number_format_i18n( $count ) . '+';
		}

		return $full ? sprintf( __( '%s active installations', 'wporg-plugins' ), $text ) : $text;
	}

	/**
	 * Returns the number of downloads for a plugin.
	 *
	 * @static
	 *
	 * @param int|\WP_Post|null $post Optional.
	 * @return int
	 */
	public static function get_downloads_count( $post = null ) {
		$post = get_post( $post );

		return (int)get_post_meta( $post->ID, 'downloads', true );
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

			$count = $wpdb->get_var( 'SELECT SUM(downloads) FROM `' . PLUGINS_TABLE_PREFIX . 'stats`' );
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
	public static function get_star_rating( $post = null, $linked = true ) {
		$post = get_post( $post );

		if ( class_exists( '\WPORG_Ratings' ) ) {
			$rating  = \WPORG_Ratings::get_avg_rating( 'plugin', $post->post_name ) ?: 0;
			$ratings = \WPORG_Ratings::get_rating_counts( 'plugin', $post->post_name ) ?: array();
		} else {
			$rating  = get_post_meta( $post->ID, 'rating', true ) ?: 0;
			$ratings = get_post_meta( $post->ID, 'ratings', true ) ?: array();
		}

		$num_ratings = array_sum( $ratings );

		return '<div class="plugin-rating">' .
				Template::dashicons_stars( $rating ) .
				'<span class="rating-count">(' .
					( $linked ? '<a href="https://wordpress.org/support/plugin/' . $post->post_name . '/reviews/">' : '' ) .
					sprintf(
						/* translators: 1: number of ratings */
						__( '%1$s<span class="screen-reader-text"> total ratings</span>', 'wporg-plugins' ),
						number_format_i18n( $num_ratings )
					) .
				( $linked ? '</a>' : '' ) .
				')</span>' .
			'</div>';
	}

	/**
	 * Returns the section names for plugins.
	 *
	 * @static
	 * @return array
	 */
	public static function get_plugin_section_titles() {
		return [
			'description'  => _x( 'Description', 'plugin tab title', 'wporg-plugins' ),
			'installation' => _x( 'Installation', 'plugin tab title', 'wporg-plugins' ),
			'faq'          => _x( 'FAQ', 'plugin tab title', 'wporg-plugins' ),
			'screenshots'  => _x( 'Screenshots', 'plugin tab title', 'wporg-plugins' ),
			'changelog'    => _x( 'Changelog', 'plugin tab title', 'wporg-plugins' ),
			'stats'        => _x( 'Stats', 'plugin tab title', 'wporg-plugins' ),
			'support'      => _x( 'Support', 'plugin tab title', 'wporg-plugins' ),
			'reviews'      => _x( 'Reviews', 'plugin tab title', 'wporg-plugins' ),
			'developers'   => _x( 'Contributors &amp; Developers', 'plugin tab title', 'wporg-plugins' ),
			'other_notes'  => _x( 'Other Notes', 'plugin tab title', 'wporg-plugins' ),
			'blocks'       => _x( 'Blocks', 'plugin tab title', 'wporg-plugins' ),
		];
	}

	/**
	 * Retrieve the Plugin icon details for a plugin.
	 *
	 * @static
	 *
	 * @param int|\WP_Post|null $post   Optional. Post ID or post object. Defaults to global $post.
	 * @param string            $output Optional. Output type. 'html' or 'raw'. Default: 'raw'.
	 * @param string            $locale Optional. Locale to use. Default: current locale.
	 * @return mixed
	 */
	public static function get_plugin_icon( $post = null, $output = 'raw', $locale = null ) {
		$plugin = get_post( $post );
		$locale = $locale ?: get_locale();

		$all_icons = get_post_meta( $plugin->ID, 'assets_icons', true ) ?: [];
		$icon      = $icon_1x = $icon_2x = $svg = $generated = false;
		$svg       = self::find_best_asset( $plugin, $all_icons, false, $locale );

		// SVG has priority.
		if ( $svg && 'icon.svg' === $svg['filename'] ) {
			$icon   = $svg;
		} else {
			// Look for the non-SVGs.
			$svg     = false;
			$icon_1x = self::find_best_asset( $plugin, $all_icons, '128x128', $locale );
			$icon_2x = self::find_best_asset( $plugin, $all_icons, '256x256', $locale );

			$icon = ( $icon_1x ?: $icon_2x ) ?: false;
		}

		// Resolve to URLs
		$svg     = $svg     ? self::get_asset_url( $plugin, $svg )     : false;
		$icon    = $icon    ? self::get_asset_url( $plugin, $icon )    : false;
		$icon_2x = $icon_2x ? self::get_asset_url( $plugin, $icon_2x ) : false;

		if ( ! $icon || 'publish' !== $plugin->post_status ) {
			$generated = true;
			$icon_2x   = false; // For the ! publish branch.
			$icon      = self::get_geopattern_icon_url( $plugin );
		}

		switch ( $output ) {
			case 'html':

				if ( $icon_2x && $icon_2x !== $icon ) {
					return "<img class='plugin-icon' srcset='{$icon}, {$icon_2x} 2x' src='{$icon_2x}'>";
				} else {
					return "<img class='plugin-icon' src='{$icon}'>";
				}
				break;

			case 'raw':
			default:
				return compact( 'svg', 'icon', 'icon_2x', 'generated' );
		}
	}

	/**
	 * Retrieve the Geopattern SVG URL for a given plugin.
	 */
	public static function get_geopattern_icon_url( $post = null, $color = null ) {
		$plugin = get_post( $post );

		if ( is_null( $color ) ) {
			$color = get_post_meta( $plugin->ID, 'assets_banners_color', true );
		}

		if ( strlen( $color ) === 6 && strspn( $color, 'abcdef0123456789' ) === 6 ) {
			$color = "_{$color}";
		} else {
			$color = '';
		}

		// This is a cached resource. The slug + color combine to form the cache buster.
		$url = "https://s.w.org/plugins/geopattern-icon/{$plugin->post_name}{$color}.svg";

		return $url;
	}

	/**
	 * Retrieve the Plugin banner details for a plugin.
	 *
	 * @static
	 *
	 * @param int|\WP_Post|null $post   Optional. Post ID or post object. Defaults to global $post.
	 * @param string            $output Optional. Output type. 'html', 'raw', or 'raw_with_rtl'. Default: 'raw'.
	 * @param string            $locale Optional. Locale to use. Defaults to current locale.
	 * @return mixed
	 */
	public static function get_plugin_banner( $post = null, $output = 'raw', $locale = null ) {
		$plugin = get_post( $post );
		$locale = $locale ?: get_locale();

		if ( in_array( $plugin->post_status, [ 'disabled', 'closed' ], true ) ) {
			return false;
		}

		$banner      = $banner_2x = $banner_rtl = $banner_2x_rtl = false;
		$all_banners = get_post_meta( $plugin->ID, 'assets_banners', true ) ?: [];

		$banner    = self::find_best_asset( $plugin, $all_banners, '772x250', $locale );
		$banner_2x = self::find_best_asset( $plugin, $all_banners, '1544x500', $locale );

		if ( ! $banner ) {
			return false;
		}

		/*
		 * If we need both LTR and a RTL banners, fetch both..
		 * This doesn't use find_best_asset() as it's too complex to add the "RTL only" functionality to it.
		 */
		if ( 'raw_with_rtl' === $output ) {
			$banner_rtl = array_filter(
				wp_list_filter( $all_banners, array( 'resolution' => '772x250' ) ),
				function( $info ) {
					return (bool) stristr( $info['filename'], '-rtl' );
				}
			);

			$banner_2x_rtl = array_filter(
				wp_list_filter( $all_banners, array( 'resolution' => '1544x500' ) ),
				function( $info ) {
					return (bool) stristr( $info['filename'], '-rtl' );
				}
			);

			$banner_rtl    = $banner_rtl    ? array_shift( $banner_rtl )    : false;
			$banner_2x_rtl = $banner_2x_rtl ? array_shift( $banner_2x_rtl ) : false;
		}

		// Resolve the URLs.
		$banner        = $banner        ? self::get_asset_url( $plugin, $banner )        : false;
		$banner_2x     = $banner_2x     ? self::get_asset_url( $plugin, $banner_2x )     : false;
		$banner_rtl    = $banner_rtl    ? self::get_asset_url( $plugin, $banner_rtl )    : false;
		$banner_2x_rtl = $banner_2x_rtl ? self::get_asset_url( $plugin, $banner_2x_rtl ) : false;

		switch ( $output ) {
			case 'html':
				$id    = "plugin-banner-{$plugin->post_name}";
				$html  = "<style type='text/css'>";
				$html .= "#{$id} { background-image: url('{$banner}'); }";
				if ( ! empty( $banner_2x ) ) {
					$html .= "@media only screen and (-webkit-min-device-pixel-ratio: 1.5), only screen and (min-resolution: 120dpi) { #{$id} { background-image: url('{$banner_2x}'); } }";
				}
				$html .= '</style>';
				$html .= "<div class='plugin-banner' id='{$id}'></div>";

				return $html;
				break;

			case 'raw':
			case 'raw_with_rtl':
			default:
				return compact( 'banner', 'banner_2x', 'banner_rtl', 'banner_2x_rtl' );
		}
	}

	/**
	 * Retrieve the Plugin asset that matches the requested resolution, locale, and RTL.
	 *
	 * @static
	 *
	 * @param \WP_Post $plugin     The plugin.
	 * @param array    $assets     The assets.
	 * @param string   $resolution The resolution.
	 * @param string   $locale     The locale.
	 * @return string|false
	 */
	public static function find_best_asset( $plugin, $assets, $resolution, $locale ) {
		// Asset matches resolution.
		$assets = wp_list_filter( $assets, [ 'resolution' => $resolution ] );

		/*
		 * Filter the matching assets by locale.
		 * NOTE: en_US/'' must also go through this branch, to remove localised assets from the list.
		 * This also handles plugins which have specific english assets.
		 */
		if ( count( $assets ) > 1 ) {
			// Locales, match [ `de_DE_formal`, `de_DE`, `de` ], prioritising the full locale before falling back to the partial match.
			$locale_parts = explode( '_', $locale );
			foreach ( range( count( $locale_parts ), 1 ) as $length ) {
				$locale       = implode( '_', array_slice( $locale_parts, 0, $length ) );
				$locale_asset = wp_list_filter( $assets, [ 'locale' => $locale ] );
				if ( $locale_asset ) {
					break;
				}
			}
			if ( ! $locale_asset ) {
				// No locale match, filter to no-locale only.
				$locale_asset = wp_list_filter( $assets, [ 'locale' => '' ] );
			}

			$assets = $locale_asset ?: $assets;
		}

		// Fetch RTL asset, if needed. This is only needed if there isn't a locale match.
		if ( count( $assets ) > 1 ) {
			$direction_assets = array_filter(
				$assets,
				function( $info ) {
					// If we're on a RTL locale, we filter to RTL items else we remove them.
					$is_rtl_image = (bool) stristr( $info['filename'], '-rtl' );

					if ( is_rtl() ) {
						return $is_rtl_image;
					} else {
						return ! $is_rtl_image;
					}
				}
			);
			$assets           = $direction_assets ?: $assets;
		}

		if ( ! $assets ) {
			return false;
		}

		// Sort them if needed, svg > png > jpg > gif.
		if ( count( $assets ) > 1 ) {
			uasort( $assets, function( $a, $b ) {
				// Thankfully the extensions are alphabetical, so let's just sort by that.
				$a_ext = strtolower( pathinfo( $a['filename'], PATHINFO_EXTENSION ) );
				$b_ext = strtolower( pathinfo( $b['filename'], PATHINFO_EXTENSION ) );

				return $b_ext <=> $a_ext;
			} );
		}

		return array_shift( $assets );
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
			$url = 'https://wordpress.org/support/plugin/' . $post->post_name . '/';
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
		$args = is_numeric( $args ) ? array( 'rating' => $args ) : $args;
		$args = wp_parse_args( $args, array(
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
			return sprintf( 'https://downloads.wordpress.org/plugin/%s.%s.zip', $post->post_name, $version );
		} else {
			return sprintf( 'https://downloads.wordpress.org/plugin/%s.zip', $post->post_name );
		}
	}

	/**
	 * Is a live preview available for the plugin, and allowed for the current user to view?
	 *
	 * @param int|\WP_Post|null $post    Optional. Post ID or post object. Defaults to global $post.
	 * @param 'view'|'edit'     $context Optional. 'view' to check if preview is available for public viewing. 'edit' to also check if available for current user to test. Default: view.
	 * @return bool	True if a preview is available and the current user is permitted to see it.
	 */
	public static function is_preview_available( $post = null, $context = 'view' ) {

		if ( self::preview_link( $post ) ) {
			// Plugin committers can use the plugin preview button to test if a blueprint exists.
			if ( 'edit' === $context && current_user_can( 'plugin_admin_edit', $post ) ) {
				return true;
			}

			// Other users can only use the preview button if plugin committers have enabled it.
			$post = get_post( $post );
			if ( get_post_meta( $post->ID, '_public_preview', true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Generate a live preview (playground) link for a given plugin.
	 *
	 * @param int|\WP_Post|null $post    Optional. Post ID or post object. Defaults to global $post.
	 * @return false|string The preview url. False if no preview is configured.
	 */
	public static function preview_link( $post = null ) {
		$post = get_post( $post );

		$blueprints = self::get_blueprints( $post );
		// Note: for now, only use a file called `blueprint.json`.
		if ( !isset( $blueprints['blueprint.json'] ) ) {
			return false;
		}
		$blueprint = $blueprints['blueprint.json'];

		return sprintf( 'https://playground.wordpress.net/?plugin=%s&blueprint-url=%s', esc_attr($post->post_name), esc_attr($blueprint['url'] ) );
	}

	/**
	 * Generate a live preview (playground) link for a zip attachment. Needed for newly uploaded plugins that have not yet been published.
	 *
	 * @param string $slug            The slug of the plugin post.
	 * @param int $attachment_id      The ID of the attachment post corresponding to a plugin zip file. Must be attached to the post identified by $slug.
	 * @return false|string           The preview URL.
	 */
	public static function preview_link_zip( $slug, $attachment_id, $type = null ) {

		$file = get_attached_file( $attachment_id );
		$zip_hash = self::preview_link_hash( $file );
		if ( !$zip_hash ) {
			return false;
		}
		$zip_blueprint = sprintf( 'https://wordpress.org/plugins/wp-json/plugins/v1/plugin/%s/blueprint.json?zip_hash=%s', esc_attr( $slug ), esc_attr( $zip_hash ) );
		if ( is_string( $type ) ) {
			$zip_blueprint = add_query_arg( 'type', strval( $type ), $zip_blueprint );
		}
		$zip_preview = add_query_arg( 'blueprint-url', urlencode($zip_blueprint), 'https://playground.wordpress.net/' );

		return $zip_preview;
	}

	/**
	 * Generate a live preview (playground) link for a published plugin that does not yet have a custom blueprint. Needed for developer testing.
	 *
	 * @param string $slug            The slug of the plugin post.
	 * @param int $download_link      The URL of the zip download for the plugin.
	 * @param bool $blueprint_only    False will return a full preview URL. True will return only a blueprint URL.
	 * @return false|string           The preview or blueprint URL.
	 */
	public static function preview_link_developer( $slug, $download_link, $blueprint_only = false ) {

		$url_hash = self::preview_link_hash( $download_link );
		if ( !$url_hash ) {
			return false;
		}
		$dev_blueprint = sprintf( 'https://wordpress.org/plugins/wp-json/plugins/v1/plugin/%s/blueprint.json?url_hash=%s', esc_attr( $slug ), esc_attr( $url_hash ) );
		if ( $blueprint_only ) {
			return $dev_blueprint;
		}
		$url_preview = add_query_arg( 'blueprint-url', urlencode($dev_blueprint), 'https://playground.wordpress.net/' );

		return $url_preview;
	}

	/**
	 * Return a time-dependent variable for zip preview links.
	 *
	 * @param int $lifespan           The life span of the nonce, in seconds. Default is one week.
	 * @return float                  The tick value.
	 */
	public static function preview_link_tick( $lifespan = WEEK_IN_SECONDS ) {
		return ceil( time() / ( $lifespan / 2 ) );
	}

	/**
	 * Return a nonce-style hash for zip preview links.
	 *
	 * @param string $zip_file        The filesystem path or URL of the zip file.
	 * @param int $tick_offest        Number to subtract from the nonce tick. Use both 0 and -1 to verify older nonces.
	 * @return false|string           The hash as a hex string; or false if the attachment ID is invalid.
	 */
	public static function preview_link_hash( $zip_file, $tick_offset = 0 ) {
		if ( !$zip_file ) {
			return false;
		}
		$tick = self::preview_link_tick() - $tick_offset;
		return wp_hash( $tick . '|' . $zip_file, 'nonce' );
	}

	/**
	 * Return a list of blueprints for the given plugin.
	 *
	 * @param int|\WP_Post|null $post    Optional. Post ID or post object. Defaults to global $post.
	 * @return array An array of blueprints.
	 */
	public static function get_blueprints( $post = null ) {
		$post = get_post( $post );

		$out = array();

		$blueprints = get_post_meta( $post->ID, 'assets_blueprints', true );
		if ( $blueprints ) {
			foreach ( $blueprints as $filename => $item ) {
				if ( isset( $item['contents'] ) ) {
					$out[ $filename ] = array(
						'filename' => $filename,
						'url' => sprintf( 'https://wordpress.org/plugins/wp-json/plugins/v1/plugin/%s/blueprint.json?rev=%d', $post->post_name, $item['revision'] )
					);
				}
			}
		}

		return $out;
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
			'_wpnonce'                                 => wp_create_nonce( 'wp_rest' ),
			( $favorited ? 'unfavorite' : 'favorite' ) => '1',
		), home_url( 'wp-json/plugins/v1/plugin/' . $post->post_name . '/favorite' ) );
	}

	/**
	 * Generates a link to self-close a plugin..
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return string URL to toggle status.
	 */
	public static function get_self_close_link( $post = null ) {
		$post = get_post( $post );

		return add_query_arg(
			array( '_wpnonce' => wp_create_nonce( 'wp_rest' ) ),
			home_url( 'wp-json/plugins/v1/plugin/' . $post->post_name . '/self-close' )
		);
	}

	/**
	 * Generates a link to self-transfer a plugin.
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return string URL to toggle status.
	 */
	public static function get_self_transfer_link( $post = null ) {
		$post = get_post( $post );

		return add_query_arg(
			array( '_wpnonce' => wp_create_nonce( 'wp_rest' ) ),
			home_url( 'wp-json/plugins/v1/plugin/' . $post->post_name . '/self-transfer' )
		);
	}

	/**
	 * Generates a link to toggle the Live Preview button.
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return string URL to toggle status.
	 */
	public static function get_self_toggle_preview_link( $post = null ) {
		$post = get_post( $post );

		return add_query_arg(
			array( '_wpnonce' => wp_create_nonce( 'wp_rest' ) ),
			home_url( 'wp-json/plugins/v1/plugin/' . $post->post_name . '/self-toggle-preview' )
		);
	}

	/**
	 * Generates a link to dismiss a missing blueprint notice.
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return string URL to toggle status.
	 */
	public static function get_self_dismiss_blueprint_notice_link( $post = null ) {
		$post = get_post( $post );

		return add_query_arg(
			array( '_wpnonce' => wp_create_nonce( 'wp_rest' ), 'dismiss' => 1 ),
			home_url( 'wp-json/plugins/v1/plugin/' . $post->post_name . '/self-toggle-preview' )
		);
	}

	/**
	 * Generates a link to enable Release Confirmations.
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return string URL to enable confirmations.
	 */
	public static function get_enable_release_confirmation_link( $post = null ) {
		$post = get_post( $post );

		return add_query_arg(
			array( '_wpnonce' => wp_create_nonce( 'wp_rest' ) ),
			home_url( 'wp-json/plugins/v1/plugin/' . $post->post_name . '/release-confirmation' )
		);
	}

	/**
	 * Generates a link to confirm a release.
	 *
	 * @param string            $tag  The tag to confirm.
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @param string            $what Optional. What operation to perform. Default: approve.
	 * @return string URL to enable confirmations.
	 */
	public static function get_release_confirmation_link( $tag, $post = null, $what = 'approve' ) {
		$post = get_post( $post );

		if ( 'approve' === $what ) {
			$endpoint = 'plugin/%s/release-confirmation/%s';
		} elseif ( 'discard' === $what ) {
			$endpoint = 'plugin/%s/release-confirmation/%s/discard';
		} else {
			return '';
		}

		$url = home_url( 'wp-json/plugins/v1/' . sprintf( $endpoint, urlencode( $post->post_name ), urlencode( $tag ) ) );

		return add_query_arg(
			array( '_wpnonce' => wp_create_nonce( 'wp_rest' ) ),
			$url
		);
	}

	/**
	 * Generates a link to email the release confirmation link.
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return string URL to enable confirmations.
	 */
	public static function get_release_confirmation_access_link() {
		return add_query_arg(
			array( '_wpnonce' => wp_create_nonce( 'wp_rest' ) ),
			home_url( 'wp-json/plugins/v1/release-confirmation-access' )
		);
	}

	/**
	 * Returns the reasons for closing or disabling a plugin.
	 *
	 * @return array Close/disable reason labels.
	 */
	public static function get_close_reasons() {
		return array(
			'security-issue'                => __( 'Security Issue', 'wporg-plugins' ),
			'author-request'                => __( 'Author Request', 'wporg-plugins' ),
			'guideline-violation'           => __( 'Guideline Violation', 'wporg-plugins' ),
			'licensing-trademark-violation' => __( 'Licensing/Trademark Violation', 'wporg-plugins' ),
			'merged-into-core'              => __( 'Merged into Core', 'wporg-plugins' ),
			'unused'                        => __( 'Unused', 'wporg-plugins' ),
		);
	}

	/**
	 * Returns the reasons for rejecting a plugin.
	 *
	 * @return array Rejection reason labels.
	 */
	public static function get_rejection_reasons() {
		return array(
			'3-month'              => '3 months without completion',
			'core-supports'        => 'Code is already in core',
			'duplicate-copy'       => 'Duplicate (copy) of another Plugin',
			'library-or-framework' => 'Framework or Library Plugin',
			'generic'              => "Something we're just not hosting",
			'duplicate'            => 'New/renamed version of their own plugin',
			'wp-cli'               => 'WP-CLI Only Plugins',
			'storefront'           => 'Storefront',
			'not-owner'            => 'Not the submitters plugin',
			'script-insertion'     => 'Script Insertion Plugins are Dangerous',
			'demo'                 => 'Test/Demo plugin (non functional)',
			'translation'          => 'Translation of existing plugin',
			'banned'               => 'Banned developer trying to sneak back in',
			'author-request'       => 'Author requested not to continue',
			'security'             => 'Security concerns',
			'other'                => 'OTHER: See notes',
		);
	}

	/**
	 * Returns the close/disable reason for a plugin.
	 *
	 * @param int|\WP_Post|null $post Optional. Post ID or post object. Defaults to global $post.
	 * @return string Close/disable reason.
	 */
	public static function get_close_reason( $post = null ) {
		$post = get_post( $post );

		$close_reasons = self::get_close_reasons();
		$close_reason  = (string) get_post_meta( $post->ID, '_close_reason', true );

		if ( isset( $close_reasons[ $close_reason ] ) ) {
			$reason_label = $close_reasons[ $close_reason ];
		} else {
			$reason_label = _x( 'Unknown', 'unknown close reason', 'wporg-plugins' );
		}

		return $reason_label;
	}

	/**
	 * Adds hreflang link attributes to WordPress.org pages.
	 *
	 * @link https://support.google.com/webmasters/answer/189077?hl=en Use hreflang for language and regional URLs.
	 * @link https://sites.google.com/site/webmasterhelpforum/en/faq-internationalisation FAQ: Internationalisation.
	 */
	public static function hreflang_link_attributes() {
		global $wpdb;

		$post = false;
		if ( is_singular( 'plugin' ) ) {
			$post = get_post();
		}

		$path = self::get_current_url( $path_only = true );
		if ( ! $path ) {
			return;
		}

		wp_cache_add_global_groups( array( 'locale-associations' ) );

		// Google doesn't have support for a whole lot of languages and throws errors about it,
		// so we exclude them, as we're otherwise outputting data that isn't used at all.
		$unsupported_languages = array(
			'arq',
			'art',
			'art-xemoji',
			'art-pirate', 'pirate',
			'ary',
			'ast',
			'az-ir',
			'azb',
			'bal',
			'bcc',
			'bho',
			'brx',
			'dsb',
			'ff-sn',
			'fon',
			'frp',
			'fuc',
			'fur',
			'gax',
			'haz',
			'hsb',
			'ido',
			'io',
			'kaa',
			'kab',
			'li',
			'li-nl',
			'lij',
			'lmo',
			'mai',
			'me',
			'me-me',
			'mfe',
			'nqo',
			'pap-aw',
			'pap-cw',
			'pcd',
			'pcm',
			'rhg',
			'rup',
			'sah',
			'sc-it',
			'scn',
			'skr',
			'srd',
			'syr',
			'szl',
			'tah',
			'twd',
			'ty-tj',
			'tzm',
			'vec',
			'zgh',
		);

		// WARNING: for any changes below, check other uses of the `locale-assosciations` group as there's shared cache keys in use.
		$cache_key = $post ? 'local-sites-' . $post->post_name : 'local-sites';
		if ( false === ( $sites = wp_cache_get( $cache_key, 'locale-associations' ) ) ) {

			// get subdomain/locale associations
			$subdomains = $wpdb->get_results( 'SELECT locale, subdomain FROM wporg_locales', OBJECT_K );

			require_once GLOTPRESS_LOCALES_PATH;

			if ( $post ) {
				$sites = Plugin_I18n::instance()->get_locales();

				// Always include the current locale, regardless of translation status. #5614
				if ( 'en_US' !== get_locale() ) {
					if ( ! wp_list_filter( $sites, [ 'wp_locale' => get_locale() ] ) ) {
						$sites[] = (object) array(
							'wp_locale' => get_locale(),
						);
					}
				}

			} else {
				$sites = array();
				foreach ( array_keys( $subdomains ) as $locale ) {
					$sites[] = (object) array(
						'wp_locale' => $locale,
					);
				}
			}

			foreach ( $sites as $key => $site ) {
				$gp_locale = \GP_Locales::by_field( 'wp_locale', $site->wp_locale );
				if ( empty( $gp_locale ) || ! isset( $subdomains[ $site->wp_locale ] ) ) {
					unset( $sites[ $key ] );
					continue;
				}

				// Skip unsupported locales.
				if ( in_array( $gp_locale->slug, $unsupported_languages ) ) {
					unset( $sites[ $key ] );
					continue;
				}

				$sites[ $key ]->subdomain = $subdomains[ $site->wp_locale ]->subdomain;

				// Skip non-existing subdomains, e.g. 'de_CH_informal'.
				if ( false !== strpos( $site->subdomain, '_' ) ) {
					unset( $sites[ $key ] );
					continue;
				}

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
				'subdomain' => '',
			);

			// Add x-default to the list of sites.
			$sites['x-default'] = (object) array(
				'locale'    => 'x-default',
				'hreflang'  => 'x-default',
				'subdomain' => '',
			);

			uasort( $sites, function( $a, $b ) {
				return strcasecmp( $a->hreflang, $b->hreflang );
			} );

			wp_cache_set( $cache_key, $sites, 'locale-associations', DAY_IN_SECONDS );
		}

		foreach ( $sites as $site ) {
			$url = sprintf(
				'https://%swordpress.org%s',
				$site->subdomain ? "{$site->subdomain}." : '',
				$path
			);

			printf(
				'<link rel="alternate" href="%s" hreflang="%s" />' . "\n",
				esc_url( $url ),
				esc_attr( $site->hreflang )
			);
		}
	}

	/**
	 * Get the current front-end requested URL.
	 */
	public static function get_current_url( $path_only = false ) {

		$link = \WordPressdotorg\SEO\Canonical\get_canonical_url();

		if ( $path_only && $link ) {
			$path = parse_url( $link, PHP_URL_PATH );
			if ( $query = parse_url( $link, PHP_URL_QUERY ) ) {
				$path .= '?' . $query;
			}

			return $path;
		}

		return $link;
	}

	/**
	 * Filter the WordPress.org Canonical URL to understand the Plugin Directory.
	 */
	public static function wporg_canonical_url( $link ) {
		if ( is_singular( 'plugin' ) && get_query_var( 'plugin_advanced' ) ) {
			$link = get_permalink( get_queried_object() ) . 'advanced/';
		}

		return $link;
	}

	/**
	 * Fetch plugin Screenshots, accounting for localised screenshots.
	 *
	 * @static
	 *
	 * @param object|int $plugin The plugin to fetch screenshots for. Optional.
	 * @param string     $locale The locale requested. Optional.
	 * @return array Screenshots for the plugin, localised if possible.
	 */
	public static function get_screenshots( $plugin = null, $locale = null ) {
		$plugin = get_post( $plugin );
		$locale = $locale ?: get_locale();

		// All indexed from 1. The Image 'number' is stored in the 'resolution' key
		$all_screenshots = get_post_meta( $plugin->ID, 'assets_screenshots', true ) ?: [];
		$descriptions    = get_post_meta( $plugin->ID, 'screenshots', true )        ?: [];

		if ( empty( $all_screenshots ) ) {
			return [];
		}

		$screenshot_nums = array_unique( wp_list_pluck( $all_screenshots, 'resolution') );
		sort( $screenshot_nums, SORT_NATURAL );

		foreach ( $screenshot_nums as $screenshot_num ) {
			$caption = $descriptions[ (int) $screenshot_num ] ?? '';

			$caption = Plugin_I18n::instance()->translate(
				'screenshot-' . $screenshot_num,
				$caption,
				[ 'post_id' => $plugin->ID ]
			);

			$asset = self::find_best_asset( $plugin, $all_screenshots, $screenshot_num, $locale );
			if ( ! $asset ) {
				continue;
			}

			$asset['caption'] = $caption;
			$asset['src']     = self::get_asset_url( $plugin, $asset );

			$sorted[ $screenshot_num ] = $asset;
		}

		return $sorted;
	}
}
