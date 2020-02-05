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
		$rating      = get_post_meta( $plugin->ID, 'rating', true ) ?: 0;
		$ratings     = get_post_meta( $plugin->ID, 'ratings', true ) ?: [];
		$num_ratings = array_sum( $ratings );

		$schema = [];

		// Add the Plugin 'SoftwareApplication' node.
		$software_application = [
			"@context"            => "http://schema.org",
			"@type"               => "SoftwareApplication",
			"applicationCategory" => "http://schema.org/OtherApplication",
			"name"                => get_the_title( $plugin ),
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
			"offers" => [
				"@type"         => "Offer",
				"price"         => "0.00",
				"priceCurrency" => "USD",
				"seller"        => [
					"@type" => "Organization",
					"name"  => "WordPress.org",
					"url"   => "https://wordpress.org"
				]
			]
		];

		if ( ! $software_application['aggregateRating']['ratingCount'] ) {
			unset( $software_application['aggregateRating'] );
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
		$metas   = [];
		$noindex = false;

		// Prevent duplicate search engine results.
		if ( get_query_var( 'plugin_advanced' ) || is_search() ) {
			$noindex = true;
		} elseif ( is_singular( 'plugin' ) ) {
			$metas[] = sprintf(
				'<meta name="description" value="%s" />',
				esc_attr( get_the_excerpt() )
			);

			// Add noindex for closed or outdated plugins.
			if ( 'publish' !== get_post_status() || self::is_plugin_outdated() ) {
				$noindex = true;
			}
		}

		if ( $noindex ) {
			$metas[] = '<meta name="robots" content="noindex,follow" />' . "\n";
		}

		echo implode( "\n", $metas );
	}

	/**
	 * Prints <link rel="prev|next"> tags for archives.
	 *
	 * @static
	 */
	public static function archive_link_rel_prev_next() {
		global $paged, $wp_query, $wp_rewrite;
		if ( ! is_archive() && ! is_search() ) {
			return;
		}

		$max_page = $wp_query->max_num_pages;
		if ( ! $paged ) {
			$paged = 1;
		}

		$nextpage = intval( $paged ) + 1;
		$prevpage = intval( $paged ) - 1;

		// re-implement get_pagenum_link() using our canonical url.
		$current_url = Template::get_current_url();
		if ( ! $current_url ) {
			return;
		}

		$current_url = remove_query_arg( 'paged', $current_url );
		$current_url = preg_replace( "|{$wp_rewrite->pagination_base}/\d+/?$|", '', $current_url );

		// Just assume pretty permalinks everywhere.
		$next_url = $current_url . "{$wp_rewrite->pagination_base}/{$nextpage}/";
		$prev_url = $current_url . ( $prevpage > 1 ? "{$wp_rewrite->pagination_base}/{$prevpage}/" : '' );

		if ( $prevpage >= 1 ) {
			printf(
				'<link rel="prev" href="%s">' . "\n",
				esc_url( $prev_url )
			);
		}

		if ( $nextpage <= $max_page ) {
			printf(
				'<link rel="next" href="%s">' . "\n",
				esc_url( $next_url )
			);
		}
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
		} elseif ( $count <= 10 ) {
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
	public static function get_star_rating( $post = null ) {
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
					'<a href="https://wordpress.org/support/plugin/' . $post->post_name . '/reviews/">' .
					sprintf(
						/* translators: 1: number of ratings */
						__( '%1$s<span class="screen-reader-text"> total ratings</span>', 'wporg-plugins' ),
						number_format_i18n( $num_ratings )
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
			'blocks',
			'stats',
			'support',
			'reviews',
			'developers',
		);
		if ( ! get_post_meta( $plugin->ID, 'assets_screenshots', true ) ) {
			unset( $default_sections[ array_search( 'screenshots', $default_sections ) ] );
		}
		if ( ! get_post_meta( $plugin->ID, 'all_blocks' ) ) {
			unset( $default_sections[ array_search( 'blocks', $default_sections ) ] );
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
					$url   = 'https://wordpress.org/support/plugin/' . $plugin->post_name . '/';
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

				case 'blocks':
					$title = _x( 'Blocks', 'plugin tab title', 'wporg-plugins' );
					$url   = trailingslashit( $permalink ) . '/' . $section_slug . '/';
					break;

				default:
					// Skip ahead to the next section
					continue 2;
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
	 * Retrieve the Plugin icon details for a plugin.
	 *
	 * @static
	 *
	 * @param int|\WP_Post|null $post   Optional. Post ID or post object. Defaults to global $post.
	 * @param string            $output Optional. Output type. 'html' or 'raw'. Default: 'raw'.
	 * @return mixed
	 */
	public static function get_plugin_icon( $post = null, $output = 'raw' ) {
		$plugin = get_post( $post );

		$raw_icons = get_post_meta( $plugin->ID, 'assets_icons', true ) ?: array();
		$icon      = $icon_1x = $icon_2x = $svg = $generated = false;

		foreach ( $raw_icons as $file => $info ) {
			switch ( $info['resolution'] ) {
				case '256x256':
					$icon_2x = self::get_asset_url( $plugin, $info );
					break;

				case '128x128':
					$icon_1x = self::get_asset_url( $plugin, $info );
					break;

				/* false = the resolution of the icon, this is NOT disabled */
				case false && 'icon.svg' == $file:
					$icon = $svg = self::get_asset_url( $plugin, $info );
					break;
			}
		}

		// Fallback to 1x if it exists.
		if ( ! $icon && $icon_1x ) {
			$icon = $icon_1x;
		}

		// Fallback to 2x if it exists.
		if ( ! $icon && $icon_2x ) {
			$icon = $icon_2x;
		}

		if ( ! $icon || 'publish' !== $plugin->post_status ) {
			$generated = true;
			$icon_2x = false; // For the ! publish branch.
			$icon = self::get_geopattern_icon_url( $plugin );
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
	 * @return mixed
	 */
	public static function get_plugin_banner( $post = null, $output = 'raw' ) {
		$plugin = get_post( $post );

		if ( in_array( $plugin->post_status, [ 'disabled', 'closed' ], true ) ) {
			return false;
		}

		$banner      = $banner_2x = $banner_rtl = $banner_2x_rtl = false;
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

		if ( is_rtl() || 'raw_with_rtl' == $output ) {
			foreach ( $rtl_banners as $info ) {
				switch ( $info['resolution'] ) {
					case '1544x500':
						$field = 'raw_with_rtl' == $output ? 'banner_2x_rtl' : 'banner_2x';
						$$field = self::get_asset_url( $plugin, $info );
						break;

					case '772x250':
						$field = 'raw_with_rtl' == $output ? 'banner_rtl' : 'banner';
						$$field = self::get_asset_url( $plugin, $info );
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
			'ary',
			'ast',
			'az-ir',
			'azb',
			'bcc',
			'ff-sn',
			'frp',
			'fuc',
			'fur',
			'haz',
			'ido',
			'io',
			'kab',
			'li',
			'li-nl',
			'lmo',
			'me',
			'me-me',
			'rhg',
			'rup',
			'sah',
			'sc-it',
			'scn',
			'skr',
			'srd',
			'szl',
			'tah',
			'twd',
			'ty-tj',
			'tzm',
		);

		// WARNING: for any changes below, check other uses of the `locale-assosciations` group as there's shared cache keys in use.
		$cache_key = $post ? 'local-sites-' . $post->post_name : 'local-sites';
		if ( false === ( $sites = wp_cache_get( $cache_key, 'locale-associations' ) ) ) {

			// get subdomain/locale associations
			$subdomains = $wpdb->get_results( 'SELECT locale, subdomain FROM wporg_locales', OBJECT_K );

			require_once GLOTPRESS_LOCALES_PATH;

			if ( $post ) {
				$sites = Plugin_I18n::instance()->get_locales();
			} else {
				$sites = array();
				foreach ( array_keys( $subdomains ) as $locale ) {
					$sites[] = (object) array(
						'wp_locale'    => $locale
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
	 * Outputs a <link rel="canonical"> on archive pages.
	 */
	public static function archive_rel_canonical_link() {
		if ( $url = self::get_current_url() ) {
			printf(
				'<link rel="canonical" href="%s">' . "\n",
				esc_url( $url )
			);
		}
	}

	/**
	 * Get the current front-end requested URL.
	 */
	public static function get_current_url( $path_only = false ) {
		$queried_object = get_queried_object();
		$link = false;

		if ( is_tax() || is_tag() || is_category() ) {
			$link = get_term_link( $queried_object );
		} elseif ( is_singular() ) {
			$link = get_permalink( $queried_object );

			if ( is_singular( 'plugin' ) && get_query_var( 'plugin_advanced' ) ) {
				$link .= 'advanced/';
			}
		} elseif ( is_search() ) {
			$link = home_url( 'search/' . urlencode( get_query_var( 's' ) ) . '/' );
		} elseif ( is_front_page() ) {
			$link = home_url( '/' );
		}

		if ( $link && is_paged() ) {
			if ( false !== stripos( $link, '?' ) ) {
				$link = add_query_arg( 'paged', (int) get_query_var( 'paged' ), $link );
			} else {
				$link = rtrim( $link, '/' ) . '/page/' . (int) get_query_var( 'paged' ) . '/';
			}
		}

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

		if ( ! $locale ) {
			$locale = get_locale();
		}

		// All indexed from 1. The Image 'number' is stored in the 'resolution' key
		$screen_shots = get_post_meta( $plugin->ID, 'assets_screenshots', true ) ?: array();
		$descriptions = get_post_meta( $plugin->ID, 'screenshots', true ) ?: array();

		if ( empty( $screen_shots ) ) {
			return array();
		}

		$sorted = array();
		foreach ( $screen_shots as $image ) {
			if ( ! isset( $sorted[ $image['resolution'] ] ) ) {
				$sorted[ $image['resolution'] ] = array();
			}

			if ( empty( $image['locale'] ) ) {
				// if the image has no locale, always insert to the last element (lowerst priority).
				$sorted[ $image['resolution'] ][] = $image;
			} elseif ( $locale === $image['locale'] ) {
				// if the locale is a full match, always insert to the first element (highest priority).
				array_unshift( $sorted[ $image['resolution'] ], $image );
			} else {
				// TODO: de_DE_informal should probably fall back to de_DE before de_CH. Maybe this can wait until Core properly supports locale hierarchy.

				$image_locale_parts = explode( '_', $image['locale'] );
				$locale_parts       = explode( '_', $locale );
				// if only the language matches.
				if ( $image_locale_parts[0] === $locale_parts[0] ) {
					// image with locale has a higher priority than image without locale.
					$last_image = end( $sorted[ $image['resolution'] ] );
					if ( empty( $last_image['locale'] ) ) {
						array_splice( $sorted[ $image['resolution'] ], count( $sorted[ $image['resolution'] ] ), 0, array( $image ) );
					} else {
						$sorted[ $image['resolution'] ][] = $image;
					}
				}
			}
		}

		// Sort
		ksort( $sorted, SORT_NATURAL );

		// Reduce images to singulars and attach metadata
		foreach ( $sorted as $index => $items ) {
			// The highest priority image is the first.
			$image = $items[0];

			// Attach caption data
			$image['caption'] = false;
			if ( isset( $descriptions[ (int) $index ] ) ) {
				$image['caption'] = $descriptions[ (int) $index ];
				$image['caption'] = Plugin_I18n::instance()->translate(
					'screenshot-' . $image['resolution'],
					$image['caption'],
					[ 'post_id' => $plugin->ID ]
				);
			}

			// Attach URL information for the asset
			$image['src'] = Template::get_asset_url( $plugin, $image );

			$sorted[ $index ] = $image;
		}

		return $sorted;
	}
}
