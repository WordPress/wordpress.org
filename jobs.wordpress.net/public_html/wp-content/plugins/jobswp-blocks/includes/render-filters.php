<?php
/**
 * Render filters that adjust core blocks' output to the job board's wording.
 *
 * @package jobswp-blocks
 */

defined( 'ABSPATH' ) || exit;

/**
 * The search heading reads "Search Results for: <term> (N)", as the classic
 * theme printed it, where core prints 'Search results for: "term"'.
 *
 * @param string $block_content Rendered heading.
 * @param array  $block         Parsed block.
 * @return string
 */
function jobswp_blocks_search_title( $block_content, $block ) {
	if ( ! is_search() || empty( $block['attrs']['type'] ) || 'search' !== $block['attrs']['type'] ) {
		return $block_content;
	}

	global $wp_query;
	$found = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : 0;

	$text = sprintf(
		/* translators: 1: search term, 2: number of results. */
		esc_html__( 'Search Results for: %1$s %2$s', 'jobswp-blocks' ),
		'<span class="jobswp-search-term">' . esc_html( get_search_query() ) . '</span>',
		'<span class="jobswp-search-count">(' . esc_html( number_format_i18n( $found ) ) . ')</span>'
	);

	return (string) preg_replace( '#(<h[1-6][^>]*>).*?(</h[1-6]>)#s', '$1' . $text . '$2', $block_content, 1 );
}
add_filter( 'render_block_core/query-title', 'jobswp_blocks_search_title', 10, 2 );

/**
 * A button bound to a job's web application form opens it in a new tab as
 * user-supplied, unendorsed link; an email address opens the mail client.
 *
 * @param string   $block_content  Rendered button.
 * @param array    $block          Parsed block.
 * @param WP_Block $block_instance Block instance.
 * @return string
 */
function jobswp_blocks_apply_button( $block_content, $block, $block_instance ) {
	$binding = $block['attrs']['metadata']['bindings']['url'] ?? null;
	if ( ! is_array( $binding ) || 'jobswp/job-field' !== ( $binding['source'] ?? '' ) || 'apply_url' !== ( $binding['args']['key'] ?? '' ) ) {
		return $block_content;
	}

	$post_id = isset( $block_instance->context['postId'] ) ? (int) $block_instance->context['postId'] : get_the_ID();
	if ( 'web' !== jobswp_blocks_job_field( $post_id, 'howtoapply_method' ) ) {
		return $block_content;
	}

	$tags = new WP_HTML_Tag_Processor( $block_content );
	if ( $tags->next_tag( 'a' ) ) {
		$tags->set_attribute( 'target', '_blank' );
		$tags->set_attribute( 'rel', 'nofollow ugc noopener' );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/button', 'jobswp_blocks_apply_button', 10, 3 );
