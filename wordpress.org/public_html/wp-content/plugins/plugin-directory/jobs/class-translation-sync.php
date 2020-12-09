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
				$directory->sync_all_translations_to_meta( $post );
			}

			// Make sure the cache doesn't exhaust memory
			Manager::clear_memory_heavy_variables();

			$args['offset'] += $args['posts_per_page'];
		}
	}
}
