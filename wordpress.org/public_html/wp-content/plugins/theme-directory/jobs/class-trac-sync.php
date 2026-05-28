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
		'new'      => [
			'status' => 'reopened',
		],
		'approved' => [
			// `approved` is an open Trac status (not a resolution): a reviewer has
			// approved the ticket, or themetracbot auto-approved an update, and it's
			// waiting out the release cooldown before being marked live.
			'status' => 'approved',
		],
		'live'     => [
			'status'     => 'closed',
			'resolution' => 'live',
		],
		'old'      => [
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

				$current_status = wporg_themes_get_version_status( $theme_id, $version );

				/*
				 * Skip if the version is already in the target status. This is the common
				 * case for additional ticket activity (e.g. comments) after a ticket has
				 * reached a resolved state.
				 */
				if ( $current_status === $new_status ) {
					continue;
				}

				/*
				 * Only act on transitions that make sense in the directory's lifecycle
				 * (new -> approved -> live, with branches to old or back to new). This
				 * guards against ticket activity that arrives out of order or after the
				 * version has already moved on.
				 */
				switch ( $new_status ) {
					case 'new':
						// Reopened: always sync back to 'new' from any resolved state.
						break;

					case 'approved':
						// Newly approved (reviewer or auto-approved update): only valid
						// coming from 'new'.
						if ( 'new' !== $current_status ) {
							continue 2;
						}
						break;

					case 'live':
						// Going live: straight from review (approve_and_live / new_no_review,
						// current 'new') or out of the release cooldown (current 'approved',
						// via the release-to-live cron or a reviewer force-release on Trac).
						if ( ! in_array( $current_status, [ 'new', 'approved' ], true ) ) {
							continue 2;
						}
						break;

					case 'old':
						// Rejected during review: only valid coming from 'new'.
						if ( 'new' !== $current_status ) {
							continue 2;
						}
						break;
				}

				wporg_themes_update_version_status( $theme_id, $version, $new_status );
			}
		}
	}

	/**
	 * Cron handler that promotes auto-approved theme updates out of the release cooldown.
	 *
	 * Finds `theme update` tickets that have been sitting in the `approved` status for at
	 * least WPORG_THEMES_RELEASE_COOL_DOWN_DELAY, marks the associated theme version live
	 * (firing the usual publish / wp-themes.com / GlotPress / author-email machinery), and
	 * closes the Trac ticket as resolution=live so it leaves the `approved` state.
	 *
	 * Scoped to the `theme update` priority on purpose: first-time theme submissions also
	 * pass through the `approved` status, but a trusted reviewer marks those live by hand,
	 * so they should not be promoted on a timer.
	 */
	public static function release_to_live() {
		if ( ! defined( 'THEME_TRACBOT_PASSWORD' ) || ! THEME_TRACBOT_PASSWORD ) {
			return;
		}

		if ( ! class_exists( 'Trac' ) ) {
			require_once ABSPATH . WPINC . '/class-IXR.php';
			require_once ABSPATH . WPINC . '/class-wp-http-ixr-client.php';
			require_once dirname( __DIR__ ) . '/lib/class-trac.php';
		}

		$trac   = new \Trac( 'themetracbot', THEME_TRACBOT_PASSWORD, 'https://themes.trac.wordpress.org/login/xmlrpc' );
		$delay  = (int) ( defined( 'WPORG_THEMES_RELEASE_COOL_DOWN_DELAY' ) ? WPORG_THEMES_RELEASE_COOL_DOWN_DELAY : 0 );
		$cutoff = time() - $delay;

		/*
		 * Auto-approved theme updates currently in the `approved` status. We check each
		 * ticket's changetime in PHP rather than filtering server-side, so a quirk in
		 * Trac's date-range query syntax can't silently strand themes in the cooldown.
		 */
		$tickets = (array) $trac->ticket_query( add_query_arg( [
			'status'   => 'approved',
			'priority' => 'theme update',
			'order'    => 'changetime',
		] ) );

		foreach ( $tickets as $ticket_id ) {
			$ticket = $trac->ticket_get( $ticket_id );
			if ( ! $ticket ) {
				continue;
			}

			// The ticket may have been force-released or reopened since the query.
			if ( 'approved' !== ( $ticket['status'] ?? '' ) ) {
				continue;
			}

			// The cooldown must have elapsed: the ticket has to have been sitting in
			// `approved` since at least $cutoff.
			$changed = $ticket[2] instanceof \IXR_Date ? $ticket[2]->getTimestamp() : strtotime( (string) $ticket[2] );
			if ( ! $changed || $changed > $cutoff ) {
				continue;
			}

			$theme_id = self::get_theme_id( $ticket_id );
			if ( ! $theme_id ) {
				continue;
			}

			// If there was a newer-version-uploaded, we have more than one version per ticket.
			$versions = array_keys( (array) get_post_meta( $theme_id, '_ticket_id', true ), $ticket_id, true );
			usort( $versions, 'version_compare' );
			$version = end( $versions );
			if ( ! $version ) {
				continue;
			}

			/*
			 * Mark the version live if it's still the approved one. A newer version
			 * uploaded mid-cooldown will have demoted this version to 'old'; we still
			 * close the ticket below so the cron stops revisiting it.
			 */
			if ( 'approved' === wporg_themes_get_version_status( $theme_id, $version ) ) {
				wporg_themes_update_version_status( $theme_id, $version, 'live' );
			}

			// Advance the ticket out of `approved` (closed, resolution=live). Pass the
			// concurrency token we just read to avoid a second ticket.get.
			$trac->ticket_update(
				$ticket_id,
				'Release cooldown elapsed — marking this theme version live.',
				[ 'action' => 'new_no_review', '_ts' => $ticket['_ts'] ],
				false
			);
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
