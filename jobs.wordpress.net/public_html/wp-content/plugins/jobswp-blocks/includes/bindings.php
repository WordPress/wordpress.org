<?php
/**
 * Block Bindings sources for job data, so templates stay core blocks.
 *
 * Every source takes a `key` argument and an optional `format`, in which %s
 * stands for the value ("Posted %s", "Seen by %s candidates"). An empty value
 * returns an empty string; the block then prints empty and the theme hides it.
 *
 * @package jobswp-blocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the sources.
 */
function jobswp_blocks_register_bindings() {
	register_block_bindings_source(
		'jobswp/job-field',
		array(
			'label'              => __( 'Job field', 'jobswp-blocks' ),
			'get_value_callback' => 'jobswp_blocks_binding_job_field',
			'uses_context'       => array( 'postId', 'postType' ),
		)
	);

	register_block_bindings_source(
		'jobswp/stats',
		array(
			'label'              => __( 'Job board stats', 'jobswp-blocks' ),
			'get_value_callback' => 'jobswp_blocks_binding_stats',
		)
	);

	register_block_bindings_source(
		'jobswp/archive',
		array(
			'label'              => __( 'Job archive', 'jobswp-blocks' ),
			'get_value_callback' => 'jobswp_blocks_binding_archive',
		)
	);
}
add_action( 'init', 'jobswp_blocks_register_bindings' );

/**
 * Applies a binding's optional format to a value.
 *
 * @param string $value       Value.
 * @param array  $source_args Binding arguments.
 * @return string
 */
function jobswp_blocks_format_binding( $value, $source_args ) {
	if ( '' === $value ) {
		return '';
	}
	if ( isset( $source_args['format'] ) && is_string( $source_args['format'] ) && false !== strpos( $source_args['format'], '%s' ) ) {
		return sprintf( $source_args['format'], $value );
	}
	return $value;
}

/**
 * The jobswp/job-field source.
 *
 * Keys: company, budget, location, location_label, jobtype, jobtype_label,
 * apply_url, apply_label, views.
 *
 * @param array    $source_args    Binding arguments.
 * @param WP_Block $block_instance Bound block.
 * @return string
 */
function jobswp_blocks_binding_job_field( $source_args, $block_instance ) {
	$key     = isset( $source_args['key'] ) && is_string( $source_args['key'] ) ? $source_args['key'] : '';
	$post_id = isset( $block_instance->context['postId'] ) ? (int) $block_instance->context['postId'] : get_the_ID();

	return jobswp_blocks_format_binding( jobswp_blocks_job_field( $post_id, $key ), $source_args );
}

/**
 * The jobswp/stats source. Keys: total, categories, remote_pct, candidates.
 *
 * @param array $source_args Binding arguments.
 * @return string
 */
function jobswp_blocks_binding_stats( $source_args ) {
	$key      = isset( $source_args['key'] ) && is_string( $source_args['key'] ) ? $source_args['key'] : '';
	$snapshot = jobswp_blocks_snapshot();

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
				$value = $total ? sprintf( _n( '%s person', '%s people', $total, 'jobswp-blocks' ), number_format_i18n( $total ) ) : '';
			}
			break;
		default:
			$value = '';
	}

	return jobswp_blocks_format_binding( $value, $source_args );
}

/**
 * The jobswp/archive source, for the archive and search headers.
 *
 * Keys: feed_url (the feed of the queried term or search), found (number of
 * jobs found by the main query), jobs_label ("8 jobs").
 *
 * @param array $source_args Binding arguments.
 * @return string
 */
function jobswp_blocks_binding_archive( $source_args ) {
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
			$value = sprintf( _n( '%s job', '%s jobs', $found, 'jobswp-blocks' ), number_format_i18n( $found ) );
			break;
		default:
			$value = '';
	}

	return jobswp_blocks_format_binding( (string) $value, $source_args );
}
