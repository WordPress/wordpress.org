<?php
/**
 * Updates stats on resolved and unresolved support requests.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */

namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Class Plugin_Support_Resolved
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Plugin_Support_Resolved {

	/**
	 * The cron trigger for the update job.
	 *
	 * @static
	 * @global wpdb $wpdb WordPress database class.
	 */
	public static function cron_trigger() {
		global $wpdb;

		// Support resolutions are on a 2 month rolling period.
		$time_limit   = date( 'Y-m-d 00:00:00', strtotime( '-2 months' ) );
		$plugin_stats = [];

		$wpdb->set_blog_id( WPORG_SUPPORT_FORUMS_BLOGID );
		// phpcs:ignore WordPress.VIP.DirectDatabaseQuery
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.slug as plugin_slug, meta_value as resolved, count(*) as topic_count, max(p.post_date) AS most_recent_thread
			FROM {$wpdb->posts} p
				JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = 'topic_resolved')
				JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
				JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'topic-plugin')
				JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
			WHERE
				post_type = 'topic' AND
				post_status = 'publish' AND
				post_parent = (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'forum' AND post_name = 'plugins-and-hacks') AND
				post_date >= %s
			
			GROUP BY t.slug, meta_value",
			$time_limit
		) );
		$wpdb->set_blog_id( WPORG_PLUGIN_DIRECTORY_BLOGID );

		foreach ( $results as $result ) {
			$plugin_stats[ $result->plugin_slug ] ??= [
				'yes'                => 0,
				'no'                 => 0,
				'mu'                 => 0,
				'most_recent_thread' => 0,
			];

			$plugin_stats[ $result->plugin_slug ][ $result->resolved ]  = (int) $result->topic_count;
			$plugin_stats[ $result->plugin_slug ]['most_recent_thread'] = max(
				strtotime( $result->most_recent_thread ),
				$plugin_stats[ $result->plugin_slug ]['most_recent_thread']
			);
		}

		foreach ( array_chunk( $plugin_stats, 1000, true ) as $plugin_stats_chunk ) {
			foreach ( $plugin_stats_chunk as $plugin_slug => $stats ) {
				$plugin = Plugin_Directory::get_plugin_post( $plugin_slug );
				if ( ! $plugin || 'publish' !== $plugin->post_status ) {
					continue;
				}

				update_post_meta( $plugin->ID, 'support_threads', wp_slash( $stats['yes'] + $stats['no'] ) );
				update_post_meta( $plugin->ID, 'support_threads_resolved', wp_slash( $stats['yes'] ) );
				update_post_meta( $plugin->ID, '_last_support_thread', wp_slash( $stats['most_recent_thread'] ) );
			}

			Manager::clear_memory_heavy_variables();
		}

		// Find any plugin whose last support thread was before the above time cutoff and mark it as having no threads.
		$plugins = get_posts( [
			'post_type'      => 'plugin',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => [
				[
					[
						'key'     => '_last_support_thread',
						'compare' => '<',
						'value'   => strtotime( $time_limit ),
					],
					'relation' => 'OR',
					[
						'key'     => '_last_support_thread',
						'compare' => 'NOT EXISTS',
					],
				],
				[
					'key'     => 'support_threads',
					'compare' => '>',
					'value'   => 0,
				],
			],
		] );

		foreach ( $plugins as $plugin_id ) {
			update_post_meta( $plugin_id, 'support_threads', 0 );
			update_post_meta( $plugin_id, 'support_threads_resolved', 0 );
		}
	}

}
