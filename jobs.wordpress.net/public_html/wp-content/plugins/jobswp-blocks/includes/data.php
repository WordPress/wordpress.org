<?php
/**
 * Job data helpers shared by the bindings, filters and blocks.
 *
 * @package jobswp-blocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public meta keys a binding may read. Poster names, email and token are
 * never exposed.
 *
 * @return string[]
 */
function jobswp_blocks_public_fields() {
	return array( 'company', 'jobtype', 'location', 'budget', 'howtoapply', 'howtoapply_method' );
}

/**
 * Plain-text value of a job field, labelled the way the classic theme
 * printed it.
 *
 * @param int    $post_id Job id.
 * @param string $key     Field key, or one of the derived keys.
 * @return string Plain text, or an empty string when there is nothing to show.
 */
function jobswp_blocks_job_field( $post_id, $key ) {
	$post_id = (int) $post_id;
	if ( ! $post_id || 'job' !== get_post_type( $post_id ) ) {
		return '';
	}

	$raw = static function ( $meta_key ) use ( $post_id ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		return is_string( $value ) ? trim( $value ) : '';
	};

	switch ( $key ) {
		case 'company':
		case 'budget':
			return $raw( $key );

		case 'location':
			$location = $raw( 'location' );
			return '' === $location ? __( 'N/A', 'jobswp-blocks' ) : $location;

		case 'location_label':
			$location = $raw( 'location' );
			if ( '' === $location || 'N/A' === $location ) {
				return '';
			}
			$remote = false !== stripos( $location, 'remote' ) || false !== stripos( $location, 'anywhere' );
			return ( $remote ? "\u{1F30E} " : "\u{1F4CD} " ) . $location;

		case 'jobtype':
			$labels = array(
				'ppt' => __( 'Project', 'jobswp-blocks' ),
				'ft'  => __( 'Full Time', 'jobswp-blocks' ),
				'pt'  => __( 'Part Time', 'jobswp-blocks' ),
			);
			$type   = $raw( 'jobtype' );
			return isset( $labels[ $type ] ) ? $labels[ $type ] : __( 'N/A', 'jobswp-blocks' );

		case 'jobtype_label':
			$icons = array(
				'ppt' => "\u{1F4C4}",
				'ft'  => "\u{1F4BC}",
				'pt'  => "\u{23F1}",
			);
			$type  = $raw( 'jobtype' );
			return isset( $icons[ $type ] ) ? $icons[ $type ] . ' ' . jobswp_blocks_job_field( $post_id, 'jobtype' ) : '';

		case 'howtoapply_method':
			$method = $raw( 'howtoapply_method' );
			if ( '' === $method ) {
				$value  = $raw( 'howtoapply' );
				$method = false !== strpos( $value, '@' ) ? 'email' : ( 0 === strpos( $value, 'http' ) ? 'web' : 'phone' );
			}
			return $method;

		case 'apply_url':
			$value  = $raw( 'howtoapply' );
			$method = jobswp_blocks_job_field( $post_id, 'howtoapply_method' );
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
			return 'email' === jobswp_blocks_job_field( $post_id, 'howtoapply_method' )
				? __( 'Apply via Email', 'jobswp-blocks' )
				: __( 'Apply Now', 'jobswp-blocks' );

		case 'views':
			$views = jobswp_blocks_job_views( $post_id );
			return null === $views ? '' : number_format_i18n( $views );
	}

	return '';
}

/**
 * All-time view count from Jetpack Stats, cached for an hour. Null when no
 * Stats source is available, so callers can print nothing.
 *
 * @param int $post_id Job id.
 * @return int|null
 */
function jobswp_blocks_job_views( $post_id ) {
	$cache_key = 'jobswp_blocks_views_' . (int) $post_id;
	$cached    = get_transient( $cache_key );
	if ( false !== $cached ) {
		return is_numeric( $cached ) ? (int) $cached : null;
	}

	$views = null;
	if ( class_exists( '\Automattic\Jetpack\Stats\WPCOM_Stats' ) ) {
		$stats = new \Automattic\Jetpack\Stats\WPCOM_Stats();
		$data  = $stats->get_post_views( (int) $post_id );
		if ( is_array( $data ) && isset( $data['views'] ) ) {
			$views = (int) $data['views'];
		}
	}

	set_transient( $cache_key, null === $views ? '' : (string) $views, HOUR_IN_SECONDS );

	return $views;
}

/**
 * Homepage snapshot: open positions, categories with jobs, remote share and
 * per-category counts. Memoised per request.
 *
 * @return array{total:int,categories:int,remote_pct:int,counts:array<string,int>}
 */
function jobswp_blocks_snapshot() {
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

	$seen   = array();
	$remote = 0;
	foreach ( (array) Jobs_Dot_WP::get_job_categories() as $category ) {
		$jobs                               = (array) Jobs_Dot_WP::get_jobs_for_category( $category );
		$cache['counts'][ $category->slug ] = count( $jobs );
		if ( $jobs ) {
			++$cache['categories'];
		}
		foreach ( $jobs as $job ) {
			if ( isset( $seen[ $job->ID ] ) ) {
				continue;
			}
			$seen[ $job->ID ] = true;
			if ( '' === jobswp_blocks_job_field( $job->ID, 'location_label' ) || false !== strpos( jobswp_blocks_job_field( $job->ID, 'location_label' ), "\u{1F30E}" ) ) {
				++$remote;
			}
		}
	}

	$cache['total']      = count( $seen );
	$cache['remote_pct'] = $cache['total'] ? (int) round( $remote / $cache['total'] * 100 ) : 0;

	return $cache;
}
