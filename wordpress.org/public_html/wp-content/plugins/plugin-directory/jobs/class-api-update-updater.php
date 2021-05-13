<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * Handles interfacing with the api.WordPress.org/plugin/update-check/ API.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class API_Update_Updater {

	/**
	 * The cron job to ensure all plugins in the `update_source` table are up-to-date.
	 * This cron is a backup in the event that the import doesn't trigger it correctly.
	 */
	public static function cron_trigger() {
		global $wpdb;

		// Note: `left( pm.meta_value, 128 )` is due to the short `version` field length and some plugins with absurdly long version strings.
		$out_of_date_plugins = $wpdb->get_col(
			"SELECT p.post_name
			FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->prefix}update_source u ON p.ID = u.plugin_id
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'version'
			WHERE
				p.post_type = 'plugin'
				AND (
					p.post_status IN( 'publish', 'disabled', 'closed' ) OR
					u.plugin_id IS NOT NULL
				)
				AND (
					u.plugin_id IS NULL OR
					u.last_updated != p.post_modified OR
					( u.version != pm.meta_value AND u.version != left( pm.meta_value, 128 ) ) OR
					( u.available = 1 AND (p.post_status != 'publish' AND p.post_status != 'disabled' ) ) OR
					( u.available = 0 AND (p.post_status = 'publish' OR p.post_status = 'disabled' ) )
				)"
		);

		if ( ! $out_of_date_plugins ) {
			return;
		}

		foreach ( $out_of_date_plugins as $plugin_slug ) {
			if ( ! self::update_single_plugin( $plugin_slug ) ) {
				// If the update failed, but yet we know the DB data differs, clear cached data and try again.
				$post = Plugin_Directory::get_plugin_post( $plugin_slug );
				clean_post_cache( $post->ID );
				self::update_single_plugin( $plugin_slug );
			}
		}
	}

	/**
	 * Updates a single plugins `update_source` data.
	 */
	public static function update_single_plugin( $plugin_slug, $self_loop = false ) {
		global $wpdb;
		$post = Plugin_Directory::get_plugin_post( $plugin_slug );

		if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'disabled', 'closed' ) ) ) {
			$wpdb->delete( $wpdb->prefix . 'update_source', compact( 'plugin_slug' ) );
			return true;
		}

		$data = array(
			'plugin_id'       => $post->ID,
			'plugin_slug'     => $post->post_name,
			'available'       => 'publish' === $post->post_status || 'disabled' === $post->post_status,
			'version'         => get_post_meta( $post->ID, 'version', true ),
			'stable_tag'      => get_post_meta( $post->ID, 'stable_tag', true ),
			'plugin_name'     => strip_tags( get_post_meta( $post->ID, 'header_name', true ) ),
			'plugin_name_san' => sanitize_title_with_dashes( strip_tags( get_post_meta( $post->ID, 'header_name', true ) ) ),
			'plugin_author'   => strip_tags( get_post_meta( $post->ID, 'header_author', true ) ),
			'tested'          => get_post_meta( $post->ID, 'tested', true ),
			'requires'        => get_post_meta( $post->ID, 'requires', true ),
			'requires_php'    => get_post_meta( $post->ID, 'requires_php', true ),
			'upgrade_notice'  => '',
			'assets'          => serialize( self::get_plugin_assets( $post ) ),
			'last_updated'    => $post->post_modified,
		);
		$upgrade_notice = get_post_meta( $post->ID, 'upgrade_notice', true );
		if ( isset( $upgrade_notice[ $data['version'] ] ) ) {
			$data['upgrade_notice'] = $upgrade_notice[ $data['version'] ];
		}

		if (
			! $wpdb->update( $wpdb->prefix . 'update_source', $data, array( 'plugin_slug' => $post->post_name ) ) &&
			! $wpdb->get_var( $wpdb->prepare( "SELECT `plugin_slug` FROM `{$wpdb->prefix}update_source` WHERE `plugin_slug` = %s", $post->post_name ) )
		) {
			if ( ! $wpdb->insert( $wpdb->prefix . 'update_source', $data ) ) {
				return false;
			}
		}

		// ~34char prefix, Memcache limit of 255char per key.
		$plugin_details_cache_key = 'plugin_details:' . ( strlen( $plugin_slug ) > 200 ? 'md5:' . md5( $plugin_slug ) : $plugin_slug );
		wp_cache_delete( $plugin_details_cache_key, 'update-check-3' );

		// Clear plugin info caches also
		if ( defined( 'GLOTPRESS_LOCALES_PATH' ) && GLOTPRESS_LOCALES_PATH ) {
			require_once GLOTPRESS_LOCALES_PATH;

			$locales = array_filter( array_values( wp_list_pluck( \GP_Locales::locales(), 'wp_locale' ) ) );

			foreach ( $locales as $locale ) {
				$cache_key = 'plugin_information:'
					. ( strlen( $plugin_slug ) > 200 ? 'md5:' . md5( $plugin_slug ) : $plugin_slug )
					. ":{$locale}";
				wp_cache_delete( $cache_key, 'plugin_api_info' );
			}
		}

		return true;
	}

	static function get_plugin_assets( $post ) {
		$icons = $banners = $banners_rtl = array();

		$raw_icons   = Template::get_plugin_icon( $post, 'raw' );
		$raw_banners = Template::get_plugin_banner( $post, 'raw_with_rtl' );

		// Banners
		if ( !empty( $raw_banners['banner_2x'] ) ) {
			$banners['2x'] = $raw_banners['banner_2x'];
		}
		if ( !empty( $raw_banners['banner'] ) ) {
			$banners['1x'] = $raw_banners['banner'];
		}

		// RTL Banners (get_plugin_banner 'raw_with_rtl' returns these)
		if ( !empty( $raw_banners['banner_2x_rtl'] ) ) {
			$banners_rtl['2x'] = $raw_banners['banner_2x_rtl'];
		}
		if ( !empty( $raw_banners['banner_rtl'] ) ) {
			$banners_rtl['1x'] = $raw_banners['banner_rtl'];
		}

		// Icons.
		if ( !empty( $raw_icons['icon_2x'] ) ) {
			$icons['2x'] = $raw_icons['icon_2x'];
		}
		if ( !empty( $raw_icons['icon'] ) ) {
			$icons['1x'] = $raw_icons['icon'];
		}
		if ( !empty( $raw_icons['svg'] ) ) {
			$icons['svg'] = $raw_icons['svg'];
		}
		if ( !empty( $raw_icons['generated'] ) ) {
			// Geopattern SVG will be in 'icon':
			$icons['default'] = $raw_icons['icon'];

			// Don't set the `1x` field when it's a geopattern icon
			unset( $icons['1x'] );
		}

		return (object) compact( 'icons', 'banners', 'banners_rtl' );
	}

}

/*
CREATE TABLE `{$prefix}_update_source` (
  `plugin_id` bigint(20) unsigned NOT NULL,
  `plugin_slug` varchar(255) NOT NULL DEFAULT '',
  `available` tinyint(4) NOT NULL,
  `version` varchar(128) NOT NULL DEFAULT '0.0',
  `stable_tag` varchar(128) NOT NULL DEFAULT 'trunk',
  `plugin_name` varchar(255) NOT NULL DEFAULT '',
  `plugin_name_san` varchar(255) NOT NULL DEFAULT '',
  `plugin_author` varchar(255) NOT NULL DEFAULT '',
  `tested` varchar(128) NOT NULL DEFAULT '',
  `requires` varchar(128) NOT NULL DEFAULT '',
  `requires_php` varchar(128) NOT NULL DEFAULT '',
  `upgrade_notice` text,
  `assets` text NOT NULL DEFAULT '',
  `last_updated` datetime NOT NULL,
  PRIMARY KEY (`plugin_id`),
  UNIQUE KEY `plugin_slug` (`plugin_slug`),
  KEY `plugin_name` (`plugin_name`),
  KEY `plugin_name_san` (`plugin_name_san`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
*/
