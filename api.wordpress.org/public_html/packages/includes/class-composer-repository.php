<?php
/**
 * Core Composer repository handler.
 *
 * @package WordPressdotorg\API\Composer
 */

declare( strict_types = 1 );

// phpcs:disable WordPress.WP.CapitalPDangit -- Package slugs and URLs use lowercase "wordpress".

namespace WordPressdotorg\API\Composer;

/**
 * Fetches plugin/theme metadata from the WordPress.org database and
 * transforms it into Composer v2 package JSON responses.
 */
class Composer_Repository {

	const CACHE_GROUP  = 'composer';
	const CACHE_EXPIRY = 900;  // 15 minutes.
	const CACHE_404    = 3600; // 1 hour for unknown packages.

	/**
	 * Parse the package type and slug from a REQUEST_URI.
	 *
	 * Expected format: /packages/p2/{type}/{slug}.json
	 *
	 * @param string $uri The request URI.
	 * @return array|false Associative array with 'type' and 'slug' keys, or false.
	 */
	public static function parse_request( string $uri ): array|false {
		// Strip query string.
		$path = strtok( $uri, '?' );

		// Composer v2 requests both {slug}.json and {slug}~dev.json for dev versions.
		if ( ! preg_match( '#/packages/p2/wp-(plugin|theme|core)/([a-z0-9_-]+?)(?:~dev)?\.json$#', $path, $matches ) ) {
			return false;
		}

		return array(
			'type' => $matches[1],
			'slug' => $matches[2],
		);
	}

	/**
	 * Serve a package metadata response.
	 *
	 * @param string $type The package type: plugin, theme, or core.
	 * @param string $slug The package slug.
	 */
	public static function serve( string $type, string $slug ): void {
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Cache-Control: public, max-age=300' );

		$cache_key = "composer:{$type}:{$slug}";
		$response  = wp_cache_get( $cache_key, self::CACHE_GROUP );

		if ( false === $response ) {
			switch ( $type ) {
				case 'plugin':
					$response = self::get_plugin_package( $slug );
					break;
				case 'theme':
					$response = self::get_theme_package( $slug );
					break;
				case 'core':
					$response = self::get_core_package( $slug );
					break;
				default:
					$response = null;
			}

			if ( null === $response ) {
				wp_cache_set( $cache_key, 'not_found', self::CACHE_GROUP, self::CACHE_404 );
			} else {
				wp_cache_set( $cache_key, $response, self::CACHE_GROUP, self::CACHE_EXPIRY );
			}
		}

		if ( 'not_found' === $response || null === $response ) {
			/*
			 * A 404 leaves the name unresolved, so Composer keeps searching every repository after
			 * this one, ending at Packagist where these vendors are unclaimed. Naming it stops that.
			 */
			$package_name = 'wp-' . $type . '/' . $slug;

			// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
			echo json_encode( array( 'packages' => array( $package_name => array() ) ), JSON_UNESCAPED_SLASHES );
			exit;
		}

		// Send Last-Modified and handle If-Modified-Since.
		if ( ! empty( $response['last_modified'] ) ) {
			$last_modified = (int) $response['last_modified'];
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s', $last_modified ) . ' GMT' );

			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$if_modified_since = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
			if ( $if_modified_since && strtotime( $if_modified_since ) >= $last_modified ) {
				header( ( $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.0' ) . ' 304 Not Modified', true, 304 ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				exit;
			}
		}

		// Strip internal metadata before output.
		unset( $response['last_modified'] );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		echo json_encode( $response, JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Get Composer package data for a plugin.
	 *
	 * Uses the existing standalone Plugins_Info_API to fetch metadata,
	 * reusing the same cache layer as the plugin info API.
	 *
	 * @param string $slug The plugin slug.
	 * @return array|null The Composer packages response, or null if not found.
	 */
	private static function get_plugin_package( string $slug ): ?array {
		require_once WPORGPATH . 'wp-content/plugins/plugin-directory/standalone/class-plugins-info-api-request.php';
		require_once WPORGPATH . 'wp-content/plugins/plugin-directory/standalone/class-plugins-info-api.php';

		$api     = new \WordPressdotorg\Plugin_Directory\Standalone\Plugins_Info_API( 'json' );
		$request = new \WordPressdotorg\Plugin_Directory\Standalone\Plugins_Info_API_Request(
			(object) array(
				'slug'   => $slug,
				'fields' => array(
					'versions'          => true,
					'stable_tag'        => true,
					'download_link'     => true,
					'last_updated'      => true,
					'short_description' => true,
					'requires_php'      => true,
					'contributors'      => true,
					'homepage'          => true,
					'donate_link'       => true,
					// Minimize response size — we don't need these.
					'sections'          => false,
					'screenshots'       => false,
					'compatibility'     => false,
					'ratings'           => false,
					'banners'           => false,
					'tags'              => false,
				),
			)
		);

		$plugin_data = $api->plugin_information( $request, true );

		if ( ! $plugin_data || ! empty( $plugin_data['error'] ) ) {
			return null;
		}

		return self::build_plugin_response( $slug, $plugin_data );
	}

	/**
	 * Build the Composer response for a plugin.
	 *
	 * @param string $slug        The plugin slug.
	 * @param array  $plugin_data The plugin metadata.
	 * @return array|null The packages response, or null if no valid versions.
	 */
	private static function build_plugin_response( string $slug, array $plugin_data ): ?array {
		$tagged       = ( ! empty( $plugin_data['versions'] ) && is_array( $plugin_data['versions'] ) ) ? $plugin_data['versions'] : array();
		$fallback_url = $plugin_data['download_link'] ?? sprintf( 'https://downloads.wordpress.org/plugin/%s.zip', $slug );

		$versions = array();
		foreach ( self::select_plugin_versions( $tagged, (string) ( $plugin_data['stable_tag'] ?? 'trunk' ), (string) ( $plugin_data['version'] ?? '' ), $fallback_url ) as $entry ) {
			$versions[] = Package_Builder::build_plugin( $slug, $entry['version'], $plugin_data, $entry['url'] );
		}

		if ( empty( $versions ) ) {
			return null;
		}

		$package_name = 'wp-plugin/' . $slug;

		$response = array( 'packages' => array( $package_name => $versions ) );

		if ( ! empty( $plugin_data['last_updated'] ) ) {
			$response['last_modified'] = strtotime( $plugin_data['last_updated'] );
		}

		return $response;
	}

	/**
	 * Get Composer package data for a theme.
	 *
	 * Uses the existing Themes API to fetch metadata,
	 * reusing the same cache layer as the theme info API.
	 *
	 * @param string $slug The theme slug.
	 * @return array|null The Composer packages response, or null if not found.
	 */
	private static function get_theme_package( string $slug ): ?array {
		// Load WordPress for the Themes API.
		if ( ! function_exists( 'wporg_themes_query_api' ) ) {
			\WordPressdotorg\API\load_wordpress( 'https://wordpress.org/themes/' );
		}

		$request = (object) array(
			'slug'   => $slug,
			'fields' => array(
				'downloadlink'    => true,
				'description'     => true,
				'requires_php'    => true,
				'last_updated'    => true,
				'extended_author' => true,
				'versions'        => false,
				'sections'        => false,
				'rating'          => false,
				'tags'            => false,
				'screenshots'     => false,
			),
		);

		$theme_data = wporg_themes_query_api( 'theme_information', $request, 'raw' );

		if ( ! $theme_data || ! empty( $theme_data->error ) ) {
			return null;
		}

		return self::build_theme_response( $slug, $theme_data );
	}

	/**
	 * Build the Composer response for a theme.
	 *
	 * @param string $slug       The theme slug.
	 * @param object $theme_data Theme metadata from the Themes API.
	 * @return array|null The packages response, or null if no valid versions.
	 */
	private static function build_theme_response( string $slug, object $theme_data ): ?array {
		// Serve the directory's current version only; lacking a live version it may be the newest, unreviewed upload (known residual).
		$live_version = $theme_data->version ?? '';
		if ( '' === $live_version ) {
			return null;
		}

		$normalized = Version_Normalizer::normalize( (string) $live_version );
		if ( false === $normalized ) {
			return null;
		}

		$download_url = (string) ( $theme_data->download_link ?? '' );
		if ( '' === $download_url ) {
			return null;
		}

		$response = array(
			'packages' => array(
				'wp-theme/' . $slug => array( Package_Builder::build_theme( $slug, $normalized, $theme_data, $download_url ) ),
			),
		);

		if ( ! empty( $theme_data->last_updated_time ) ) {
			$response['last_modified'] = strtotime( $theme_data->last_updated_time );
		}

		return $response;
	}

	/**
	 * Select the plugin versions eligible for the Composer response, newest first.
	 *
	 * Serves the release history up to the stable release, dropping any tag above it. The ceiling is
	 * the stable tag, or the current version when the plugin is trunk-stable; a degenerate current
	 * version ('0.0' or 'trunk') is rejected and nothing is served. Equivalent tags collapse (the
	 * stable tag wins its key), and the served release is always offered. Comparisons use canonical keys.
	 *
	 * @param array  $tagged       Map of raw SVN tag => download URL.
	 * @param string $stable_tag   The plugin's stable tag ('trunk' or a version).
	 * @param string $version      The plugin's current version, from its readme header.
	 * @param string $fallback_url Download URL to use for the current version.
	 * @return array List of [ 'version' => normalized, 'url' => download URL ], newest first.
	 */
	public static function select_plugin_versions( array $tagged, string $stable_tag, string $version, string $fallback_url ): array {
		$normalized_stable  = Version_Normalizer::normalize( $stable_tag );
		$normalized_current = '' !== $version ? Version_Normalizer::normalize( $version ) : false;

		// The served release: the stable tag, or (trunk-stable) the current version; a degenerate '0.0'/'trunk' serves nothing.
		$usable = static fn ( string|false $normalized ): bool => false !== $normalized && 'dev-trunk' !== $normalized && '0.0.0.0' !== Version_Normalizer::dedupe_key( $normalized );

		if ( $usable( $normalized_stable ) ) {
			$served = $normalized_stable;
		} elseif ( $usable( $normalized_current ) ) {
			$served = $normalized_current;
		} else {
			return array();
		}
		$ceiling = Version_Normalizer::dedupe_key( $served );

		$seen = array();
		foreach ( $tagged as $tag => $url ) {
			$tag        = (string) $tag;
			$normalized = Version_Normalizer::normalize( $tag );
			if ( false === $normalized ) {
				continue;
			}

			// Keep trunk (the dev branch); drop tags canonically newer than the ceiling.
			$key = Version_Normalizer::dedupe_key( $normalized );
			if ( 'dev-trunk' !== $normalized && version_compare( $key, $ceiling, '>' ) ) {
				continue;
			}

			// Dedupe equivalent tags: the stable tag wins its key, else the canonical spelling.
			$is_stable = ( $tag === $stable_tag );
			if ( isset( $seen[ $key ] ) && ( $seen[ $key ]['stable'] || ( ! $is_stable && ! self::is_preferred_spelling( $normalized, $tag, $seen[ $key ]['normalized'], $seen[ $key ]['tag'] ) ) ) ) {
				continue;
			}

			$seen[ $key ] = array(
				'normalized' => $normalized,
				'tag'        => $tag,
				'stable'     => $is_stable,
				'url'        => (string) $url,
			);
		}

		// Always offer the served release itself, unless a tag already produced it above.
		if ( ! isset( $seen[ $ceiling ] ) ) {
			$seen[ $ceiling ] = array(
				'normalized' => $served,
				'tag'        => '',
				'stable'     => false,
				'url'        => $fallback_url,
			);
		}

		$entries = array();
		foreach ( $seen as $entry ) {
			$entries[] = array(
				'version' => $entry['normalized'],
				'url'     => $entry['url'],
			);
		}

		usort(
			$entries,
			static function ( $a, $b ) {
				return version_compare( $b['version'], $a['version'] );
			}
		);

		return $entries;
	}

	/**
	 * Decide whether one spelling of a version should be kept over another Composer treats as equal.
	 *
	 * The point is a single deterministic winner, never dependent on the order tags arrive in. It
	 * orders by normalized length (usually, but not always, the fewer-zeros spelling), then the
	 * normalized bytes, then the raw tag, so even tags that normalize identically ("1.0"/"v1.0")
	 * resolve to one.
	 *
	 * @param string $candidate     Normalized version being considered.
	 * @param string $candidate_tag Its raw tag.
	 * @param string $current       Normalized version already kept for this dedupe key.
	 * @param string $current_tag   Its raw tag.
	 * @return bool True if $candidate should replace the kept version.
	 */
	private static function is_preferred_spelling( string $candidate, string $candidate_tag, string $current, string $current_tag ): bool {
		$order = ( strlen( $candidate ) <=> strlen( $current ) )
			?: strcmp( $candidate, $current )
			?: strcmp( $candidate_tag, $current_tag );

		return $order < 0;
	}

	/**
	 * Get Composer package data for WordPress core.
	 *
	 * Supports two package slugs:
	 * - "wordpress": Full distribution including bundled themes and plugins.
	 * - "wordpress-no-content": Core only, without bundled themes or plugins.
	 *
	 * Both include stable releases and beta/RC pre-releases when available.
	 * No-content zips are only available for stable releases.
	 *
	 * @param string $slug The core package slug.
	 * @return array|null The Composer packages response, or null if invalid slug.
	 */
	private static function get_core_package( string $slug ): ?array {
		$valid_slugs = array( 'wordpress', 'wordpress-no-content' );
		if ( ! in_array( $slug, $valid_slugs, true ) ) {
			return null;
		}

		$no_content = 'wordpress-no-content' === $slug;

		if ( ! function_exists( 'wporg_packages_get_secure_versions' ) ) {
			return null;
		}

		$package_name = 'wp-core/' . $slug;
		$versions     = array();

		// Add stable releases.
		$core_versions = wporg_packages_get_secure_versions();
		foreach ( $core_versions as $release ) {
			$normalized = Version_Normalizer::normalize( $release );
			if ( false === $normalized ) {
				continue;
			}

			$zip_suffix = $no_content ? '-no-content' : '';

			// Only include versions where the zip actually exists.
			if ( $no_content && function_exists( 'wporg_packages_core_zip_exists' ) && ! wporg_packages_core_zip_exists( $release, $zip_suffix ) ) {
				continue;
			}

			$download_url = sprintf( 'https://downloads.wordpress.org/release/wordpress-%s%s.zip', $release, $zip_suffix );
			$php_version  = wporg_packages_get_required_php_version( $release );

			$versions[] = self::build_core_entry( $package_name, $normalized, $download_url, $php_version );
		}

		// Add beta/RC releases (full package only, no-content zips don't exist for pre-releases).
		if ( ! $no_content ) {
			$beta_versions = function_exists( 'wporg_packages_get_beta_releases' ) ? wporg_packages_get_beta_releases() : array();
			foreach ( $beta_versions as $beta_version ) {
				$normalized = Version_Normalizer::normalize( $beta_version );
				if ( false === $normalized ) {
					continue;
				}

				$download_url = sprintf( 'https://downloads.wordpress.org/release/wordpress-%s.zip', $beta_version );
				$php_version  = wporg_packages_get_required_php_version( $beta_version );

				$versions[] = self::build_core_entry( $package_name, $normalized, $download_url, $php_version );
			}
		}

		if ( empty( $versions ) ) {
			return null;
		}

		$response = array( 'packages' => array( $package_name => $versions ) );

		if ( function_exists( 'wporg_packages_core_last_modified' ) ) {
			$last_modified = wporg_packages_core_last_modified();
			if ( $last_modified ) {
				$response['last_modified'] = $last_modified;
			}
		}

		return $response;
	}

	/**
	 * Build a single core version entry.
	 *
	 * @param string $package_name The Composer package name.
	 * @param string $version      The normalized version string.
	 * @param string $download_url The download URL for this version.
	 * @param string $php_version  The minimum required PHP version.
	 * @return array Composer package entry.
	 */
	private static function build_core_entry( string $package_name, string $version, string $download_url, string $php_version ): array {
		return array(
			'name'        => $package_name,
			'version'     => $version,
			'type'        => 'wordpress-core',
			'license'     => array( 'GPL-2.0-or-later' ),
			'dist'        => array(
				'url'  => $download_url,
				'type' => 'zip',
			),
			'require'     => array(
				'php'                            => '>=' . $php_version,
				'roots/wordpress-core-installer' => '^3.0',
			),
			'suggest'     => array(
				'ext-curl'      => 'Performs remote request operations.',
				'ext-dom'       => 'Used to validate Text Widget content and to automatically configure IIS7+.',
				'ext-exif'      => 'Works with metadata stored in images.',
				'ext-fileinfo'  => 'Used to detect mimetype of file uploads.',
				'ext-hash'      => 'Used for hashing, including passwords and update packages.',
				'ext-imagick'   => 'Provides better image quality for media uploads.',
				'ext-json'      => 'Used for communications with other servers.',
				'ext-libsodium' => 'Validates Signatures and provides securely random bytes.',
				'ext-mbstring'  => 'Used to properly handle UTF8 text.',
				'ext-mysqli'    => 'Connects to MySQL for database interactions.',
				'ext-openssl'   => 'Permits SSL-based connections to other hosts.',
				'ext-pcre'      => 'Increases performance of pattern matching in code searches.',
				'ext-xml'       => 'Used for XML parsing, such as from a third-party site.',
				'ext-zip'       => 'Used for decompressing Plugins, Themes, and WordPress update packages.',
			),
			'provide'     => array(
				'wordpress/core-implementation' => $version,
			),
			'description' => 'WordPress is open source software you can use to create a beautiful website, blog, or app.',
			'homepage'    => 'https://wordpress.org/',
			'authors'     => array(
				array(
					'name'     => 'WordPress Community',
					'homepage' => 'https://wordpress.org/about/',
				),
			),
			'source'      => array(
				'url'       => 'https://github.com/WordPress/WordPress.git',
				'type'      => 'git',
				'reference' => str_contains( $version, '-' ) ? 'trunk' : $version,
			),
			'support'     => array(
				'docs'   => 'https://developer.wordpress.org/',
				'forum'  => 'https://wordpress.org/support/',
				'issues' => 'https://core.trac.wordpress.org/',
				'source' => 'https://core.trac.wordpress.org/browser',
			),
			'funding'     => array(
				array(
					'url'  => 'https://wordpressfoundation.org/donate/',
					'type' => 'other',
				),
			),
		);
	}
}
