<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

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

		$out_of_date_plugins = $wpdb->get_col(
			"SELECT p.post_name
			FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->prefix}update_source u ON p.ID = u.plugin_id
			WHERE
				p.post_type = 'plugin'
				AND (
					p.post_status IN( 'publish', 'approved', 'disabled', 'closed' ) OR
					u.plugin_id IS NOT NULL
				)
				AND (
					u.plugin_id IS NULL OR
					u.last_updated != p.post_modified OR
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

		if ( ! $post ) {
			$wpdb->delete(  $wpdb->prefix . 'update_source', compact( 'plugin_slug' ) );
			return true;
		}

		$data = array(
			'plugin_id'       => $post->ID,
			'plugin_slug'     => $post->post_name,
			'available'       => 'publish' === $post->post_status || 'disabled' === $post->post_status,
			'version'         => get_post_meta( $post->ID, 'version', true ),
			'stable_tag'      => get_post_meta( $post->ID, 'stable_tag', true ),
			'plugin_name'     => get_post_meta( $post->ID, 'header_name', true ),
			'plugin_name_san' => sanitize_title_with_dashes( get_post_meta( $post->ID, 'header_name', true ) ),
			'tested'          => get_post_meta( $post->ID, 'tested', true ),
			'requires'        => get_post_meta( $post->ID, 'requires', true ),
			'upgrade_notice'  => '',
			'last_updated'    => $post->post_modified,
		);
		$upgrade_notice = get_post_meta( $post->ID, 'upgrade_notice', true );
		if ( isset( $upgrade_notice[ $data['version'] ] ) ) {
			$data['upgrade_notice'] = $upgrade_notice[ $data['version'] ];
		}

		if (
			$wpdb->update( $wpdb->prefix . 'update_source', $data, array( 'plugin_slug' => $post->post_name ) ) &&
			! $wpdb->get_var( $wpdb->prepare( "SELECT `plugin_slug` FROM `{$wpdb->prefix}update_source` WHERE `plugin_slug` = %s", $post->post_name ) )
		) {
			if ( ! $wpdb->insert( $wpdb->prefix . 'update_source', $data ) ) {
				return false;
			}
		}

		// ~34char prefix, Memcache limit of 255char per key.
		$plugin_details_cache_key = 'plugin_details:' . ( strlen( $plugin_slug ) > 200 ? 'md5:' . md5( $plugin_slug ) : $plugin_slug );
		wp_cache_delete( $plugin_details_cache_key, 'update-check-3' );

		return true;
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
  `tested` varchar(128) NOT NULL DEFAULT '',
  `requires` varchar(128) NOT NULL DEFAULT '',
  `upgrade_notice` text,
  `last_updated` datetime NOT NULL,
  PRIMARY KEY (`plugin_id`),
  UNIQUE KEY `plugin_slug` (`plugin_slug`),
  KEY `plugin_name` (`plugin_name`),
  KEY `plugin_name_san` (`plugin_name_san`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
*/
