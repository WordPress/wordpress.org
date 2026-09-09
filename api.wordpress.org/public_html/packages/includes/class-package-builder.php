<?php
/**
 * Package builder for Composer package entries.
 *
 * @package WordPressdotorg\API\Composer
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Composer;

/**
 * Builds Composer package entries for individual plugins and themes.
 *
 * Each entry conforms to the Composer v2 package format.
 *
 * @link https://getcomposer.org/doc/05-repositories.md#package
 */
class Package_Builder {

	/**
	 * Build a Composer package entry for a single version of a plugin.
	 *
	 * @param string $slug         The plugin slug.
	 * @param string $version      The normalized Composer version string.
	 * @param array  $plugin_data  Plugin metadata from the API.
	 * @param string $download_url The download URL for this version.
	 * @return array Composer package entry.
	 */
	public static function build_plugin( string $slug, string $version, array $plugin_data, string $download_url ): array {
		$entry = array(
			'name'    => 'wp-plugin/' . $slug,
			'version' => $version,
			'type'    => 'wordpress-plugin',
			'dist'    => array(
				'url'  => $download_url,
				'type' => 'zip',
			),
			'require' => array(
				'composer/installers' => '~1.0 || ~2.0',
			),
		);

		if ( ! empty( $plugin_data['requires_php'] ) ) {
			$entry['require']['php'] = '>=' . $plugin_data['requires_php'];
		}

		if ( ! empty( $plugin_data['requires_plugins'] ) && is_array( $plugin_data['requires_plugins'] ) ) {
			foreach ( $plugin_data['requires_plugins'] as $required_slug ) {
				$entry['require'][ 'wp-plugin/' . $required_slug ] = '*';
			}
		}

		if ( ! empty( $plugin_data['short_description'] ) ) {
			$entry['description'] = $plugin_data['short_description'];
		} elseif ( ! empty( $plugin_data['name'] ) ) {
			$entry['description'] = $plugin_data['name'];
		}

		if ( ! empty( $plugin_data['homepage'] ) ) {
			$entry['homepage'] = $plugin_data['homepage'];
		} else {
			$entry['homepage'] = 'https://wordpress.org/plugins/' . $slug . '/';
		}

		$entry['authors'] = self::build_authors( $plugin_data );

		$entry['support'] = array(
			'issues'    => 'https://wordpress.org/support/plugin/' . $slug,
			'source'    => 'https://plugins.svn.wordpress.org/' . $slug,
			'changelog' => 'https://wordpress.org/plugins/' . $slug . '/#developers',
		);

		if ( ! empty( $plugin_data['donate_link'] ) ) {
			$entry['funding'] = array(
				array(
					'url'  => $plugin_data['donate_link'],
					'type' => 'other',
				),
			);
		}

		if ( ! empty( $plugin_data['last_updated'] ) ) {
			$entry['time'] = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $plugin_data['last_updated'] ) );
		}

		return $entry;
	}

	/**
	 * Build a Composer package entry for a single version of a theme.
	 *
	 * @param string $slug         The theme slug.
	 * @param string $version      The normalized Composer version string.
	 * @param object $theme_data   Theme metadata from the Themes API.
	 * @param string $download_url The download URL for this version.
	 * @return array Composer package entry.
	 */
	public static function build_theme( string $slug, string $version, object $theme_data, string $download_url ): array {
		$entry = array(
			'name'    => 'wp-theme/' . $slug,
			'version' => $version,
			'type'    => 'wordpress-theme',
			'dist'    => array(
				'url'  => $download_url,
				'type' => 'zip',
			),
			'require' => array(
				'composer/installers' => '~1.0 || ~2.0',
			),
		);

		if ( ! empty( $theme_data->requires_php ) ) {
			$entry['require']['php'] = '>=' . $theme_data->requires_php;
		}

		if ( ! empty( $theme_data->description ) ) {
			$entry['description'] = wp_strip_all_tags( $theme_data->description );
		} elseif ( ! empty( $theme_data->name ) ) {
			$entry['description'] = $theme_data->name;
		}

		$entry['homepage'] = 'https://wordpress.org/themes/' . $slug . '/';

		$entry['support'] = array(
			'issues' => 'https://wordpress.org/support/theme/' . $slug,
			'source' => 'https://themes.svn.wordpress.org/' . $slug,
		);

		if ( ! empty( $theme_data->last_updated_time ) ) {
			$entry['time'] = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $theme_data->last_updated_time ) );
		}

		if ( ! empty( $theme_data->author ) ) {
			if ( is_object( $theme_data->author ) ) {
				$author = array( 'name' => $theme_data->author->display_name ?? $theme_data->author->user_nicename ?? 'Unknown' );
				if ( ! empty( $theme_data->author->profile ) ) {
					$author['homepage'] = $theme_data->author->profile;
				}
				$entry['authors'] = array( $author );
			} elseif ( is_string( $theme_data->author ) ) {
				$entry['authors'] = array( array( 'name' => $theme_data->author ) );
			}
		}

		return $entry;
	}

	/**
	 * Build an authors array from plugin data.
	 *
	 * @param array $plugin_data Plugin metadata.
	 * @return array Authors array.
	 */
	private static function build_authors( array $plugin_data ): array {
		$authors = array();

		if ( ! empty( $plugin_data['contributors'] ) && is_array( $plugin_data['contributors'] ) ) {
			foreach ( $plugin_data['contributors'] as $username => $contributor ) {
				$author = array( 'name' => $username );
				if ( is_array( $contributor ) && ! empty( $contributor['display_name'] ) ) {
					$author['name'] = $contributor['display_name'];
				}
				if ( is_array( $contributor ) && ! empty( $contributor['profile'] ) ) {
					$author['homepage'] = $contributor['profile'];
				}
				$authors[] = $author;
				break; // Only include the first contributor to keep response small.
			}
		} elseif ( ! empty( $plugin_data['author'] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- May run without WP.
			$name = strip_tags( $plugin_data['author'] );
			if ( $name ) {
				$authors[] = array( 'name' => $name );
			}
		}

		if ( empty( $authors ) ) {
			$authors = array( array( 'name' => 'Unknown' ) );
		}

		return $authors;
	}
}
