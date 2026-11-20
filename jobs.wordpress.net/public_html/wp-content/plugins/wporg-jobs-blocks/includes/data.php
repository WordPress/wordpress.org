<?php
/**
 * Job data helpers shared by the bindings, filters and blocks.
 *
 * @package wporg-jobs-blocks
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Plain-text value of a job field.
 *
 * @param int    $post_id Job id.
 * @param string $key     Field key, or one of the derived keys.
 * @return string Plain text, or an empty string when there is nothing to show.
 */
function wporg_jobs_blocks_job_field( int $post_id, string $key ): string {
	if ( ! $post_id || 'job' !== get_post_type( $post_id ) ) {
		return '';
	}

	$raw = static function ( string $meta_key ) use ( $post_id ): string {
		$value = get_post_meta( $post_id, $meta_key, true );
		return is_string( $value ) ? trim( $value ) : '';
	};

	switch ( $key ) {
		case 'company':
		case 'budget':
			return $raw( $key );

		case 'location':
			$location = $raw( 'location' );
			return '' === $location ? __( 'N/A', 'wporg-jobs-blocks' ) : $location;

		case 'location_label':
			$location = $raw( 'location' );
			if ( '' === $location || 'N/A' === $location ) {
				return "\u{1F30E} " . __( 'Remote', 'wporg-jobs-blocks' );
			}
			$remote = false !== stripos( $location, 'remote' ) || false !== stripos( $location, 'anywhere' );
			return ( $remote ? "\u{1F30E} " : "\u{1F4CD} " ) . $location;

		case 'jobtype':
			$labels = array(
				'ppt' => __( 'Project', 'wporg-jobs-blocks' ),
				'ft'  => __( 'Full Time', 'wporg-jobs-blocks' ),
				'pt'  => __( 'Part Time', 'wporg-jobs-blocks' ),
			);
			$type   = $raw( 'jobtype' );
			return isset( $labels[ $type ] ) ? $labels[ $type ] : __( 'N/A', 'wporg-jobs-blocks' );

		case 'jobtype_label':
			$icons = array(
				'ppt' => "\u{1F4C4}",
				'ft'  => "\u{1F4BC}",
				'pt'  => "\u{23F1}",
			);
			$type  = $raw( 'jobtype' );
			return isset( $icons[ $type ] ) ? $icons[ $type ] . ' ' . wporg_jobs_blocks_job_field( $post_id, 'jobtype' ) : '';

		case 'howtoapply_method':
			$method = $raw( 'howtoapply_method' );
			if ( '' === $method ) {
				$value  = $raw( 'howtoapply' );
				$method = false !== strpos( $value, '@' ) ? 'email' : ( 0 === strpos( $value, 'http' ) ? 'web' : 'phone' );
			}
			return $method;

		case 'apply_url':
			$value  = $raw( 'howtoapply' );
			$method = wporg_jobs_blocks_job_field( $post_id, 'howtoapply_method' );
			if ( 'email' === $method ) {
				$email = sanitize_email( $value );
				return $email ? 'mailto:' . $email : '';
			}
			if ( 'web' === $method ) {
				if ( '' !== $value && 0 !== strpos( $value, 'http' ) ) {
					$value = 'http://' . $value;
				}
				return esc_url_raw( $value );
			}
			return '';

		case 'apply_label':
			return 'email' === wporg_jobs_blocks_job_field( $post_id, 'howtoapply_method' )
				? __( 'Apply via Email', 'wporg-jobs-blocks' )
				: __( 'Apply Now', 'wporg-jobs-blocks' );

		case 'views':
			$views = wporg_jobs_blocks_job_views( $post_id );
			return null === $views ? '' : number_format_i18n( $views );

		case 'views_label':
			$views = wporg_jobs_blocks_job_views( $post_id );
			if ( null === $views ) {
				return '';
			}
			/* translators: %s: number of views. */
			return sprintf( _n( 'Seen by %s candidate', 'Seen by %s candidates', $views, 'wporg-jobs-blocks' ), number_format_i18n( $views ) );
	}

	return '';
}

/**
 * All-time view count from Jetpack Stats, cached for an hour.
 *
 * @param int $post_id Job id.
 * @return int|null Null when Stats is unavailable.
 */
function wporg_jobs_blocks_job_views( int $post_id ): ?int {
	$cache_key = 'wporg_jobs_blocks_views_' . $post_id;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return is_numeric( $cached ) ? (int) $cached : null;
	}

	$views = null;
	if ( class_exists( '\Automattic\Jetpack\Stats\WPCOM_Stats' ) ) {
		$stats = new \Automattic\Jetpack\Stats\WPCOM_Stats();
		$data  = $stats->get_post_views( $post_id );
		if ( is_array( $data ) && isset( $data['views'] ) ) {
			$views = (int) $data['views'];
		}
	}

	set_transient( $cache_key, null === $views ? '' : (string) $views, HOUR_IN_SECONDS );

	return $views;
}

/**
 * Homepage stats: open positions, categories with jobs, remote share and per-category counts.
 *
 * @return array{total:int,categories:int,remote_pct:int,counts:array<string,int>}
 */
function wporg_jobs_blocks_snapshot(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$cache = array(
		'total'      => 0,
		'categories' => 0,
		'remote_pct' => 0,
		'counts'     => array(),
	);

	if ( ! class_exists( 'Jobs_Dot_WP' ) ) {
		return $cache;
	}

	foreach ( (array) Jobs_Dot_WP::get_job_categories() as $category ) {
		$cache['counts'][ $category->slug ] = (int) $category->count;
		if ( $category->count ) {
			++$cache['categories'];
		}
	}

	$job_ids = get_posts(
		array(
			'post_type'      => 'job',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);
	_prime_post_caches( $job_ids, false, true );

	$remote = 0;
	foreach ( $job_ids as $job_id ) {
		$location = wporg_jobs_blocks_job_field( $job_id, 'location_label' );
		if ( '' === $location || false !== strpos( $location, "\u{1F30E}" ) ) {
			++$remote;
		}
	}

	$cache['total']      = count( $job_ids );
	$cache['remote_pct'] = $cache['total'] ? (int) round( $remote / $cache['total'] * 100 ) : 0;

	return $cache;
}
