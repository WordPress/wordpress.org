<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Tools;

/**
 * Perform various automatic actions on posts, daily.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class Daily_Post_Checks {

	/**
	 * A static method for the cron trigger to fire.
	 */
	public static function cron_trigger() {
		$class = new Daily_Post_Checks();
		$class->check_all();
	}

	/**
	 * Process all actions.
	 */
	function check_all() {
		$this->close_disabled_plugins_after_12months();
	}

	/**
	 * Close any disabled plugins that have been in that state for 12 months.
	 *
	 * @see https://meta.trac.wordpress.org/ticket/7087
	 */
	function close_disabled_plugins_after_12months() {
		$disabled_plugins = get_posts( [
			'post_type'   => 'plugin',
			'post_status' => 'disabled',
			'posts_per_page' => -1,
			'meta_query' => [
				[
					'key'     => 'plugin_closed_date',
					'compare' => 'NOT EXISTS',
				],
				'relation' => 'OR',
				[
					'key'     => 'plugin_closed_date',
					'value'   => gmdate( 'Y-m-d H:i:s', strtotime( '-12 months' ) ),
					'compare' => '<',
					'type'    => 'DATE',
				]
			],
			'fields' => 'ids',
		] );

		foreach ( $disabled_plugins as $post_id ) {
			wp_update_post( [
				'ID' => $post_id,
				'post_status' => 'closed'
			] );

			Tools::audit_log(
				sprintf(
					'Plugin closed. Reason: %s',
					'Disabled for 12+ months, moving to closed.'
				),
				$post_id
			);
		}
	}
}
