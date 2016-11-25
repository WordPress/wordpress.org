<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Sync various meta elements from other locations on WordPress.org to the plugin directory meta.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Meta_Sync {

	/**
	 * A static method for the cron trigger to fire.
	 */
	public static function cron_trigger() {
		$class = new Meta_Sync;
		$class->sync();
	}

	/**
	 * Process all sync actions.
	 */
	function sync() {
		$this->sync_downloads();
		$this->sync_ratings();
	}

	/**
	 * Sync any changed download counts to plugin meta.
	 */
	function sync_downloads() {
		global $wpdb;

		$download_count_table = PLUGINS_TABLE_PREFIX . 'download_counts';
		$bbpress_topic_slug_table = PLUGINS_TABLE_PREFIX . 'topics';

		$changed_download_counts = $wpdb->get_results(
			"SELECT p.id as post_id, downloads
			FROM `{$wpdb->posts}` p
				JOIN `{$bbpress_topic_slug_table}` t ON p.post_name = t.topic_slug
				LEFT JOIN `{$download_count_table}` c on t.topic_id = c.topic_id
				LEFT JOIN `{$wpdb->postmeta}` pm ON p.id = pm.post_id AND pm.meta_key = 'downloads'

			WHERE
				downloads != pm.meta_value OR
				pm.meta_id IS NULL"
		);

		foreach ( $changed_download_counts as $row ) {
			update_post_meta( $row->post_id, 'downloads', $row->downloads );
		}
	}

	/**
	 * Sync new/updated ratings to postmeta.
	 */
	function sync_ratings() {
		global $wpdb;
		if ( ! class_exists( '\WPORG_Ratings' ) ) {
			return;
		}

		// Sync new (and updated) ratings to postmeta 
		$last_review_time = get_option( 'plugin_last_review_sync' );
		$current_review_time = $wpdb->get_var( "SELECT MAX(`date`) FROM `ratings`" );

		if ( strtotime( $last_review_time ) >= strtotime( $current_review_time ) ) {
			return;
		}

		// Get the plugin slugs for whom extra reviews have been made, or ratings changed.
		$slugs = $wpdb->get_col( $sql = $wpdb->prepare(
			"SELECT distinct object_slug FROM `ratings` WHERE object_type = 'plugin' AND `date` >= %s AND `date` < %s",
			$last_review_time,
			$current_review_time
		) );

		foreach ( $slugs as $plugin_slug ) {
			$post = Plugin_Directory::get_plugin_post( $plugin_slug );
			if ( ! $post ) {
				continue;
			}

			update_post_meta(
				$post->ID,
				'rating',
				\WPORG_Ratings::get_avg_rating( 'plugin', $post->post_name )
			);
			update_post_meta(
				$post->ID,
				'ratings',
				\WPORG_Ratings::get_rating_counts( 'plugin', $post->post_name )
			);
		}

		update_option( 'plugin_last_review_sync', $current_review_time, 'no' );
	}
}
