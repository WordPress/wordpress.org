<?php

defined( 'WP_CLI' ) or die();

/**
 * WordCamp.org: Manage rewrite rules.
 */
class WordCamp_CLI_Rewrite_Rules extends WP_CLI_Command {
	/**
	 * Flush rewrite rules on all sites.
	 *
	 * Periodically they break for various reasons and need to be reset on all sites. If we
	 * just called flush_rewrite_rules() inside a switch_to_blog() loop then each site's
	 * plugins wouldn't be loaded and the rewrite rules wouldn't be correct.
	 *
	 * So instead, this issues an HTTP request to wcorg_flush_rewrite_rules() on each site so
	 * that flush_rewrite_rules() will run in the context of the loaded site.
	 */
	public function flush() {
		$start_timestamp = microtime( true );
		$error           = '';
		$sites           = wp_get_sites( array( 'limit' => false ) );
		$notify          = new \cli\progress\Bar( sprintf( 'Processing %d sites', count( $sites ) ), count( $sites ) );

		WP_CLI::line();

		foreach ( $sites as $site ) {
			$nonce       = wp_create_nonce( 'flush-rewrite-rules-everywhere-' . $site['blog_id'] );
			$display_url = $site['domain'] . rtrim( $site['path'], '/' );
			$ajax_url    = sprintf( 'http://%s%swp-admin/admin-ajax.php', $site['domain'], $site['path'] );
			$ajax_url    = add_query_arg( array(
				'action' => 'wcorg_flush_rewrite_rules_everywhere',
				'nonce'  => $nonce,
				),
				$ajax_url
			);

			// todo use wcorg_redundant_remote_get
			$response = wp_remote_get( esc_url_raw( $ajax_url ) );

			if ( is_wp_error( $response ) ) {
				$success = false;
				$error   = $response->get_error_message();
			} else {
				$response = json_decode( wp_remote_retrieve_body( $response ) );

				if ( isset( $response->success ) && $response->success ) {
					$success = true;
				} else {
					$success = false;
					$error   = isset( $response->data ) ? $response->data : 'Unknown error';

				}
			}

			if ( ! $success ) {
				WP_CLI::warning( sprintf( '%s: Failed with error: %s', $display_url, $error ) );
			}

			$notify->tick();
		}

		$notify->finish();

		$execution_time = microtime( true ) - $start_timestamp;

		WP_CLI::line();
		WP_CLI::line( sprintf(
			'Flushed all rewrite rules in %d minute(s) and %d second(s).',
			floor( $execution_time / 60 ),
			$execution_time % 60
		) );
	}
}
