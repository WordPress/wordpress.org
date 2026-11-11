<?php
/**
 * Block Bindings sources for job data.
 *
 * Every source takes a `key` and an optional `format`, in which %s stands for the value.
 *
 * @package wporg-jobs-blocks
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Registers the sources.
 */
function wporg_jobs_blocks_register_bindings(): void {
	register_block_bindings_source(
		'wporg-jobs/job-field',
		array(
			'label'              => __( 'Job field', 'wporg-jobs-blocks' ),
			'get_value_callback' => 'wporg_jobs_blocks_binding_job_field',
			'uses_context'       => array( 'postId', 'postType' ),
		)
	);

	register_block_bindings_source(
		'wporg-jobs/stats',
		array(
			'label'              => __( 'Job board stats', 'wporg-jobs-blocks' ),
			'get_value_callback' => 'wporg_jobs_blocks_binding_stats',
		)
	);

	register_block_bindings_source(
		'wporg-jobs/archive',
		array(
			'label'              => __( 'Job archive', 'wporg-jobs-blocks' ),
			'get_value_callback' => 'wporg_jobs_blocks_binding_archive',
		)
	);
}
add_action( 'init', 'wporg_jobs_blocks_register_bindings' );

/**
 * Applies a binding's optional format to a value.
 *
 * @param string $value       Value.
 * @param array  $source_args Binding arguments.
 * @return string
 */
function wporg_jobs_blocks_format_binding( string $value, array $source_args ): string {
	if ( '' === $value ) {
		return '';
	}
	if ( isset( $source_args['format'] ) && is_string( $source_args['format'] ) && false !== strpos( $source_args['format'], '%s' ) ) {
		return str_replace( '%s', $value, $source_args['format'] );
	}
	return $value;
}

/**
 * The wporg-jobs/job-field source.
 *
 * @param array    $source_args    Binding arguments.
 * @param WP_Block $block_instance Bound block.
 * @return string
 */
function wporg_jobs_blocks_binding_job_field( array $source_args, WP_Block $block_instance ): string {
	$key     = isset( $source_args['key'] ) && is_string( $source_args['key'] ) ? $source_args['key'] : '';
	$post_id = (int) ( $block_instance->context['postId'] ?? get_the_ID() );

	return wporg_jobs_blocks_format_binding( wporg_jobs_blocks_job_field( $post_id, $key ), $source_args );
}

/**
 * The wporg-jobs/stats source. Keys: total, categories, remote_pct, candidates.
 *
 * @param array $source_args Binding arguments.
 * @return string
 */
function wporg_jobs_blocks_binding_stats( array $source_args ): string {
	$key      = isset( $source_args['key'] ) && is_string( $source_args['key'] ) ? $source_args['key'] : '';
	$snapshot = wporg_jobs_blocks_snapshot();

	switch ( $key ) {
		case 'total':
		case 'categories':
			$value = number_format_i18n( $snapshot[ $key ] );
			break;
		case 'remote_pct':
			$value = number_format_i18n( $snapshot['remote_pct'] ) . '%';
			break;
		case 'candidates':
			$value = '';
			if ( function_exists( 'jobswp_get_open_to_work_candidates' ) ) {
				$result = jobswp_get_open_to_work_candidates( 1, 10 );
				$total  = (int) $result['total'];
				/* translators: %s: number of people open to work. */
				$value = $total ? sprintf( _n( '%s person', '%s people', $total, 'wporg-jobs-blocks' ), number_format_i18n( $total ) ) : '';
			}
			break;
		default:
			$value = '';
	}

	return wporg_jobs_blocks_format_binding( $value, $source_args );
}

/**
 * The wporg-jobs/archive source, for the archive and search headers.
 *
 * @param array $source_args Binding arguments.
 * @return string
 */
function wporg_jobs_blocks_binding_archive( array $source_args ): string {
	global $wp_query;

	$key   = isset( $source_args['key'] ) && is_string( $source_args['key'] ) ? $source_args['key'] : '';
	$found = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : 0;

	switch ( $key ) {
		case 'feed_url':
			if ( is_search() ) {
				$value = get_search_feed_link();
			} elseif ( is_tax( 'job_category' ) ) {
				$value = get_term_feed_link( get_queried_object_id(), 'job_category' );
			} else {
				$value = get_feed_link();
			}
			break;
		case 'found':
			$value = number_format_i18n( $found );
			break;
		case 'jobs_label':
			/* translators: %s: number of jobs. */
			$value = sprintf( _n( '%s job', '%s jobs', $found, 'wporg-jobs-blocks' ), number_format_i18n( $found ) );
			break;
		default:
			$value = '';
	}

	return wporg_jobs_blocks_format_binding( (string) $value, $source_args );
}
