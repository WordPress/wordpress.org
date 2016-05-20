<?php

namespace WordCamp\RemoteCSS;

defined( 'WPINC' ) or die();

add_action( 'wp_ajax_'        . AJAX_ACTION, __NAMESPACE__ . '\webhook_handler'        ); // This is useless in production, but useful for manual testing
add_action( 'wp_ajax_nopriv_' . AJAX_ACTION, __NAMESPACE__ . '\webhook_handler'        );
add_action( SYNCHRONIZE_ACTION,              __NAMESPACE__ . '\synchronize_remote_css' );

/*
 * todo nginx on production fails with a 502 bad gateway if OPTION_REMOTE_CSS_URL is empty, even though dev handles it with an exception
 * see https://wordpress.slack.com/archives/meta-wordcamp/p1453889720000024
 */

/**
 * Trigger a synchronization when a push notification is received
 *
 * Because the client can't modify the remote URL that's being used, it's safe to allow anonymous access to this,
 * provided that requests are rate-limited to prevent a malicious party from making us flood remote servers, which
 * would result in them blocking us. The worst they could do would be to force us to unnecessarily refresh the cache.
 *
 * Avoiding authentication makes the process simpler because there's less to do, and also more flexible, because
 * we don't have to handle notification formats from various platforms.
 */
function webhook_handler() {
	$time_since_last_sync = time() - get_option( OPTION_LAST_UPDATE, 0 );

	if ( $time_since_last_sync < WEBHOOK_RATE_LIMIT ) {
		$time_limit_remaining = WEBHOOK_RATE_LIMIT - $time_since_last_sync;

		/*
		 * We only want one event scheduled to prevent abuse and unnecessary requests, but
		 * wp_schedule_single_event() does that for us if the period is under 10 minutes.
		 */
		wp_schedule_single_event(
			time() + $time_limit_remaining,
			SYNCHRONIZE_ACTION,
			array( get_option( OPTION_REMOTE_CSS_URL ) )
		);

		wp_send_json_error( sprintf(
			__( 'The request could not be executed immediately because of the rate limit. Instead, it has been queued and will run in %d seconds.',
			'wordcamporg' ),
			$time_limit_remaining
		) );
	} else {
		try {
			do_action( SYNCHRONIZE_ACTION, get_option( OPTION_REMOTE_CSS_URL ) );
			wp_send_json_success( __( 'The remote CSS file was successfully synchronized.', 'wordcamporg' ) );
		} catch ( \Exception $exception ) {
			wp_send_json_error( strip_tags( $exception->getMessage() ) );   // strip_tags() instead of wp_strip_tags() because we want to preserve the inner content
		}
	}
}
