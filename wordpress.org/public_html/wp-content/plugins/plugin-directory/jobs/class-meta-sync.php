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
		$class = new Meta_Sync();
		$class->sync();
	}

	/**
	 * Process all sync actions.
	 */
	function sync() {
		$this->sync_downloads();
		$this->sync_ratings();
		$this->update_tested_up_to();
	}

	/**
	 * Sync any changed download counts to plugin meta.
	 */
	function sync_downloads() {
		global $wpdb;

		$download_count_table = PLUGINS_TABLE_PREFIX . 'download_counts';

		$changed_download_counts = $wpdb->get_results(
			"SELECT p.id as post_id, downloads
			FROM `{$wpdb->posts}` p
				JOIN `{$download_count_table}` c on p.post_name = c.plugin_slug
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
		$last_review_time    = get_option( 'plugin_last_review_sync' );
		$current_review_time = $wpdb->get_var( 'SELECT MAX(`date`) FROM `ratings`' );

		if ( strtotime( $last_review_time ) >= strtotime( $current_review_time ) ) {
			return;
		}

		// Get the plugin slugs for whom extra reviews have been made, or ratings changed.
		$slugs = $wpdb->get_col( $wpdb->prepare(
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

			$author_block_query = new \WP_Query( array(
				'author' => $post->post_author,
				'post_status' => 'publish',
				'tax_query' => array(
					array(
						'taxonomy' => 'plugin_section',
						'field' => 'slug',
						'terms' => 'block',
					)
				),
				'posts_per_page' => 10,
			) );

			$author_block_count = $author_block_query->found_posts;

			$author_block_slugs = wp_list_pluck( $author_block_query->posts, 'post_name' );

			update_post_meta(
				$post->ID,
				'author_block_count',
				$author_block_count
			);

			if ( count( $author_block_slugs ) > 0 ) {

				// Grab the ratings and counts for each separate block plugin, and use that to calculate the author's average rating
				$author_rating_total = $author_rating_count = 0;
				foreach ( $author_block_slugs as $block_slug ) {
					$block_ratings = \WPORG_Ratings::get_rating_counts( 'plugin', $block_slug );
					if ( $block_ratings ) {
						foreach ( $block_ratings as $rating => $rating_count ) {
							$author_rating_total += ( $rating * $rating_count );
							$author_rating_count += $rating_count;
						}
					};
				}

				if ( $author_rating_count ) {
					$avg_block_rating = $author_rating_total / $author_rating_count;
					update_post_meta(
						$post->ID,
						'author_block_rating',
						$avg_block_rating
					);
				}

			}
		}

		update_option( 'plugin_last_review_sync', $current_review_time, 'no' );
	}

	/**
	 * After WordPress is released, update the 'tested' meta keys to the latest version as
	 * specified by `wporg_get_version_equivalents()`.
	 */
	function update_tested_up_to() {
		global $wpdb;
		if ( ! function_exists( 'wporg_get_version_equivalents' ) ) {
			return;
		}

		$equivs = wporg_get_version_equivalents();

		$latest_equiv = array();
		foreach ( $equivs as $latest_compatible_version => $compatible_with ) {
			foreach ( $compatible_with as $version ) {
				$latest_equiv[ $version ] = $latest_compatible_version;
			}
		}

		$tested_meta_value_esc_sql = '"' . implode( '", "', array_map( 'esc_sql', array_keys( $latest_equiv ) ) ) . '"';
		$tested_values             = $wpdb->get_results( "SELECT post_id, post_name, meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE meta_key = 'tested' AND meta_value IN( {$tested_meta_value_esc_sql} )" );

		foreach ( $tested_values as $row ) {
			update_post_meta(
				$row->post_id,
				'tested',
				$latest_equiv[ $row->meta_value ]
			);

			// Update the API endpoints with the new data
			API_Update_Updater::update_single_plugin( $row->post_name );
		}
	}
}
