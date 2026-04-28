<?php
/**
 * Contribution metrics for the team-pledges directory (Page 1 of the
 * Five for the Future redesign).
 *
 * Returns per-user-id real counts of high-weight, medium-weight and
 * low-weight contributions in a rolling window, aggregated from:
 *
 *   - trac_<team>_props      (release credits — high weight)
 *   - trac_<team>_revisions  (committed work — medium weight, when author maps)
 *   - wporg_github_activity  (PRs, pushes, issues — tiered by category)
 *
 * Weight tiers per spec:
 *   weighted_volume = 3 * high_count + 1 * medium_count + 0 * low_count
 *
 * Bulk-queries the database (one query per signal source, GROUP BY user_id)
 * and caches the result for an hour.
 *
 * @package WordPressdotorg\Make\Breathe_2024
 */

namespace WordPressdotorg\Make\Breathe_2024\ContributionMetrics;

defined( 'WPINC' ) || die();

const WINDOW_DAYS_DEFAULT = 30;
const WINDOW_DAYS_ALLOWED = array( 30, 90, 180 );
const CACHE_GROUP         = '5ftf';
const CACHE_PREFIX        = '5ftf_contrib_metrics_v1_';

/**
 * Resolve a user-provided window-days value to one of the allowed buckets.
 * Falls back to the default (90) on anything else.
 */
function resolve_window_days( $raw ): int {
	$n = (int) $raw;
	return in_array( $n, WINDOW_DAYS_ALLOWED, true ) ? $n : WINDOW_DAYS_DEFAULT;
}

/**
 * Map a team slug (post_name of the make_site) to the internal "trac" key
 * used by the trac_*_props / trac_*_revisions tables.
 *
 * Returns an empty string when the team has no Trac data source.
 */
function team_to_trac( string $slug ): string {
	$map = array(
		'core'       => 'core',
		'meta'       => 'meta',
		'bbpress'    => 'bbpress',
		'buddypress' => 'buddypress',
	);
	return isset( $map[ $slug ] ) ? $map[ $slug ] : '';
}

/**
 * Categorise a wporg_github_activity row's `category` field into a weight tier.
 *
 *   high   — merged PRs (pr_merge / pr_merged)
 *   medium — pushes (commits), opened/closed PRs, closed issues (triage action)
 *   low    — opened issues, reopened PRs, anything else
 *
 * @return string 'high' | 'medium' | 'low'
 */
function github_category_to_tier( string $category ): string {
	switch ( $category ) {
		case 'pr_merge':
		case 'pr_merged':
			return 'high';

		case 'push':
		case 'pr_opened':
		case 'pr_closed':
		case 'issue_closed':
			return 'medium';

		default:
			return 'low';
	}
}

/**
 * Default empty metrics shape for a single user.
 */
function empty_metrics(): array {
	return array(
		'high_count'      => 0,
		'medium_count'    => 0,
		'low_count'       => 0,
		'weighted_volume' => 0,
		'last_activity'   => null,
		'top_repos'       => array(),
	);
}

/**
 * Fetch contribution metrics for a list of contributor user_ids on a given team.
 *
 * @param int[]  $user_ids  WP.org user IDs.
 * @param string $team_slug The team's post_name (e.g. "core", "meta").
 *
 * @return array<int, array> Keyed by user_id. Each entry contains:
 *   - high_count
 *   - medium_count
 *   - low_count
 *   - weighted_volume (3 * high + 1 * medium)
 *   - last_activity (unix ts or null)
 *   - top_repos (array of [repo => count] sorted desc)
 */
function get_team_contribution_metrics( array $user_ids, string $team_slug, int $window_days = WINDOW_DAYS_DEFAULT ): array {
	$user_ids    = array_values( array_unique( array_map( 'absint', $user_ids ) ) );
	$window_days = resolve_window_days( $window_days );
	if ( empty( $user_ids ) ) {
		return array();
	}

	$cache_key = CACHE_PREFIX . $team_slug . '_w' . $window_days . '_' . md5( implode( ',', $user_ids ) );
	$cached    = wp_cache_get( $cache_key, CACHE_GROUP );
	if ( false !== $cached ) {
		return $cached;
	}

	global $wpdb;

	$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );
	$args         = array_merge( $user_ids, array( $window_days ) );

	$metrics = array();
	foreach ( $user_ids as $uid ) {
		$metrics[ $uid ] = empty_metrics();
	}

	// ------------------------------------------------------------------
	// 1. Trac props (release credits) — high weight.
	// ------------------------------------------------------------------
	$trac = team_to_trac( $team_slug );
	if ( $trac ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT p.user_id, COUNT(*) AS n, MAX(r.date) AS last_ts
			 FROM trac_{$trac}_props p
			 INNER JOIN trac_{$trac}_revisions r ON r.id = p.revision
			 WHERE p.user_id IN ($placeholders)
			   AND r.date >= DATE_SUB(NOW(), INTERVAL %d DAY)
			 GROUP BY p.user_id",
			$args
		) );
		// phpcs:enable

		foreach ( $rows as $r ) {
			$uid = (int) $r->user_id;
			if ( ! isset( $metrics[ $uid ] ) ) {
				continue;
			}
			$metrics[ $uid ]['high_count'] += (int) $r->n;
			$ts = $r->last_ts ? strtotime( $r->last_ts ) : 0;
			if ( $ts > (int) $metrics[ $uid ]['last_activity'] ) {
				$metrics[ $uid ]['last_activity'] = $ts;
			}
		}
	}

	// ------------------------------------------------------------------
	// 2. GitHub activity — tiered by category.
	// ------------------------------------------------------------------
	// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT user_id, category, repo, COUNT(*) AS n, MAX(ts) AS last_ts
		 FROM wporg_github_activity
		 WHERE user_id IN ($placeholders)
		   AND ts >= DATE_SUB(NOW(), INTERVAL %d DAY)
		 GROUP BY user_id, category, repo",
		$args
	) );
	// phpcs:enable

	foreach ( $rows as $r ) {
		$uid = (int) $r->user_id;
		if ( ! isset( $metrics[ $uid ] ) ) {
			continue;
		}
		$n    = (int) $r->n;
		$tier = github_category_to_tier( (string) $r->category );

		$metrics[ $uid ][ $tier . '_count' ] += $n;

		if ( ! empty( $r->repo ) ) {
			if ( ! isset( $metrics[ $uid ]['top_repos'][ $r->repo ] ) ) {
				$metrics[ $uid ]['top_repos'][ $r->repo ] = 0;
			}
			$metrics[ $uid ]['top_repos'][ $r->repo ] += $n;
		}

		$ts = $r->last_ts ? strtotime( $r->last_ts ) : 0;
		if ( $ts > (int) $metrics[ $uid ]['last_activity'] ) {
			$metrics[ $uid ]['last_activity'] = $ts;
		}
	}

	// ------------------------------------------------------------------
	// Final shape: weighted_volume + sorted top_repos.
	// ------------------------------------------------------------------
	foreach ( $metrics as $uid => &$m ) {
		$m['weighted_volume'] = ( 3 * $m['high_count'] ) + ( 1 * $m['medium_count'] );
		arsort( $m['top_repos'] );
		$m['top_repos'] = array_slice( $m['top_repos'], 0, 2, true );
	}
	unset( $m );

	wp_cache_set( $cache_key, $metrics, CACHE_GROUP, HOUR_IN_SECONDS );
	return $metrics;
}

/**
 * Convert a unix timestamp to a coarse "active X ago" bucket and CSS class.
 *
 * Buckets keyed off non-low contribution recency, per spec:
 *   today      — < 24h
 *   N days     — within last 7d
 *   N weeks    — 8d to 30d
 *   N months   — 31d to 180d
 *   over 6 months ago — older / unknown
 *
 * @return array { bucket: string, class: string }
 */
function format_active_bucket( $last_activity_ts ): array {
	if ( ! $last_activity_ts ) {
		return array(
			'bucket' => __( 'over 6 months ago', 'wporg-5ftf' ),
			'class'  => 'active-stale',
		);
	}

	$days_since = max( 0, (int) floor( ( time() - (int) $last_activity_ts ) / DAY_IN_SECONDS ) );

	if ( $days_since <= 1 ) {
		return array(
			'bucket' => __( 'today', 'wporg-5ftf' ),
			'class'  => 'active-recent',
		);
	}

	if ( $days_since <= 7 ) {
		// translators: %d: number of days
		$bucket = sprintf( _n( '%d day ago', '%d days ago', $days_since, 'wporg-5ftf' ), $days_since );
		return array( 'bucket' => $bucket, 'class' => 'active-recent' );
	}

	if ( $days_since <= 30 ) {
		$weeks = max( 1, (int) round( $days_since / 7 ) );
		// translators: %d: number of weeks
		$bucket = sprintf( _n( '%d week ago', '%d weeks ago', $weeks, 'wporg-5ftf' ), $weeks );
		return array( 'bucket' => $bucket, 'class' => 'active-month' );
	}

	if ( $days_since <= 180 ) {
		$months = max( 1, (int) round( $days_since / 30 ) );
		// translators: %d: number of months
		$bucket = sprintf( _n( '%d month ago', '%d months ago', $months, 'wporg-5ftf' ), $months );
		return array( 'bucket' => $bucket, 'class' => 'active-quarter' );
	}

	return array(
		'bucket' => __( 'over 6 months ago', 'wporg-5ftf' ),
		'class'  => 'active-stale',
	);
}
