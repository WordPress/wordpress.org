<?php
/**
 * Watch Trac reviews and queue up jobs to update themes with review results.
 *
 * @package WordPressdotorg\Theme_Directory\Jobs
 */

namespace WordPressdotorg\Theme_Directory\Jobs;

/**
 * Class Trac_Sync
 *
 * @package WordPressdotorg\Theme_Directory\Jobs
 */
class Trac_Sync {

	/**
	 * Trac statuses.
	 *
	 * @var array
	 */
	protected static $stati = [
		'new'  => [
			'status' => 'reopened',
		],
		'live' => [
			'status'     => 'closed',
			'resolution' => 'live',
		],
		'old'  => [
			'status'     => 'closed',
			'resolution' => 'not-approved',
		],
	];

	/**
	 * The cron trigger for the svn import job.
	 */
	public static function cron_trigger() {
		if ( ! defined( 'THEME_TRACBOT_PASSWORD' ) || ! THEME_TRACBOT_PASSWORD ) {
			return;
		}

		if ( ! class_exists( 'Trac' ) ) {
			require_once ABSPATH . WPINC . '/class-IXR.php';
			require_once ABSPATH . WPINC . '/class-wp-http-ixr-client.php';
			require_once dirname( __DIR__ ) . '/lib/class-trac.php';
		}

		$trac         = new \Trac( 'themetracbot', THEME_TRACBOT_PASSWORD, 'https://themes.trac.wordpress.org/login/xmlrpc' );
		$last_request = get_option( 'wporg-themes-last-trac-sync', strtotime( '-2 days' ) );
		update_option( 'wporg-themes-last-trac-sync', time() );

		foreach ( self::$stati as $new_status => $args ) {
			// Get array of tickets.
			$tickets = (array) $trac->ticket_query( add_query_arg( wp_parse_args( $args, [
				'order'      => 'changetime',
				'changetime' => date( 'c', $last_request ),
				'desc'       => 1,
			] ) ) );

			foreach ( $tickets as $ticket_id ) {
				// Get the theme associated with that ticket.
				$theme_id = self::get_theme_id( $ticket_id );
				if ( ! $theme_id ) {
					continue;
				}

				// If there was a newer-version-uploaded, we have more than one version per ticket.
				$versions = array_keys( (array) get_post_meta( $theme_id, '_ticket_id', true ), $ticket_id, true );
				usort( $versions, 'version_compare' );
				$version = end( $versions );

				// There should always be a version associated with a ticket.
				if ( ! $version ) {
					continue;
				}

				/*
				 * Bail if the the theme has the wrong status.
				 *
				 * For approved and rejected themes, we bail if the current status is not
				 * 'new' That can happen when there are additional ticket updates (like
				 * comments) after the ticket was closed.
				 *
				 * For reopened tickets we bail if the version is already marked as 'new'.
				 * This should only be the case if the ticket was closed and reopened before
				 * this script was able to sync the closed status.
				 */
				$current_status = wporg_themes_get_version_status( $theme_id, $version );
				if ( ( 'new' !== $new_status && 'new' !== $current_status ) || ( 'new' === $new_status && 'new' === $current_status ) ) {
					continue;
				}

				// We don't need to set an already approved live version to live again.
				if ( 'live' === $current_status && 'live' === $new_status ) {
					continue;
				}

				wporg_themes_update_version_status( $theme_id, $version, $new_status );
			}
		}
	}

	/**
	 * Returns the ID of a theme associated with the passed ticket number.
	 *
	 * @param string $ticket_id Trac ticket number.
	 * @return int The post ID, or 0 if none can be found.
	 */
	public static function get_theme_id( $ticket_id ) {
		$post_id = 0;

		$post_ids = get_posts( [
			'fields'         => 'ids',
			'post_status'    => 'any',
			'post_type'      => 'repopackage',
			'posts_per_page' => - 1,
			'meta_query'     => [
				'trac_sync_ticket_id' => [
					'value'   => $ticket_id,
					'compare' => 'IN',
				],
			],
		] );

		if ( ! empty( $post_ids ) ) {
			$post_id = current( $post_ids );
		}

		return $post_id;
	}
}
