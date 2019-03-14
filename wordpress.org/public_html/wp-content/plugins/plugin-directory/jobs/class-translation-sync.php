<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory;

/**
 * Sync plugin translations from glotpress to each plugin's postmeta. This is
 * intended to be run every day.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Translation_Sync {
	/**
	 * Cron trigger that syncs plugin translations from glotpress to each plugin's
	 * postmeta.
	 */
	public static function cron_trigger() {
		$args = array(
			'post_type' => 'plugin',
			'posts_per_page' => 100,
			'offset' => 0,
		);

		$directory = Plugin_Directory\Plugin_Directory::instance();

		while ( $posts = get_posts( $args ) ) {
			foreach ( $posts as $post ) {
				$directory->sync_all_translations_to_meta( $post->ID );
			}

			// Make sure the cache doesn't exhaust memory
			global $wp_object_cache;
			if ( is_object( $wp_object_cache ) ) {
				$wp_object_cache->cache = array();
				$wp_object_cache->stats = array( 'add' => 0, 'get' => 0, 'get_multi' => 0, 'delete' => 0);
				$wp_object_cache->group_ops = array();
			}

			$args['offset'] += $args['posts_per_page'];
		}
	}
}
