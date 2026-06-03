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

		// Migrate approved theme updates whose release delay has elapsed to live on Trac,
		// so the sync below imports them as it would any other newly-live ticket.
		self::release_to_live( $trac );

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
	 * Migrates auto-approved theme updates out of the release delay, on Trac.
	 *
	 * Finds `theme update` tickets that have been in the `approved` status for at least
	 * the theme's release cooldown delay (wporg_themes_get_release_cooldown_delay()) and
	 * closes them as resolution=live. That's the only change made here — cron_trigger()'s
	 * normal sync, which runs straight after, imports the now-live ticket into WordPress
	 * like any other.
	 *
	 * Scoped to the `theme update` priority on purpose: first-time theme submissions also
	 * pass through the `approved` status, but a trusted reviewer marks those live by hand,
	 * so they should not be promoted on a timer.
	 *
	 * @param \Trac $trac An authenticated Trac client.
	 */
	public static function release_to_live( $trac ) {
		/*
		 * Auto-approved theme updates currently in the `approved` status. We check each
		 * ticket's changetime in PHP rather than filtering server-side, so a quirk in
		 * Trac's date-range query syntax can't silently strand themes in the delay.
		 */
		$tickets = (array) $trac->ticket_query( add_query_arg( [
			'status'   => 'approved',
			'priority' => 'theme update',
			'order'    => 'changetime',
		] ) );

		foreach ( $tickets as $ticket_id ) {
			$ticket = $trac->ticket_get( $ticket_id );

			// Skip if the ticket was force-released or reopened since the query.
			if ( ! $ticket || 'approved' !== ( $ticket['status'] ?? '' ) ) {
				continue;
			}

			// Resolve the theme slug so the release delay can be filtered per-theme.
			$theme_slug = get_post_field( 'post_name', self::get_theme_id( $ticket_id ) );
			$cutoff     = time() - wporg_themes_get_release_cooldown_delay( $theme_slug );

			// Only once the release delay, measured from the ticket's changetime, has elapsed.
			$changed = $ticket[2] instanceof \IXR_Date ? $ticket[2]->getTimestamp() : strtotime( (string) $ticket[2] );
			if ( ! $changed || $changed > $cutoff ) {
				continue;
			}

			// Close as live. Pass the concurrency token we just read to avoid a second
			// ticket.get; cron_trigger()'s sync then imports it as a newly-live version.
			$trac->ticket_update(
				$ticket_id,
				'Marking live.',
				[
					'action' => 'new_no_review',
					'_ts'    => $ticket['_ts'],
				],
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
