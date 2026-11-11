<?php
/**
 * Render filters that adjust core blocks' output to the job board's wording.
 *
 * @package wporg-jobs-blocks
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * The search heading reads "Search Results for: <term> (N)", as the classic
 * theme printed it, where core prints 'Search results for: "term"'.
 *
 * @param string $block_content Rendered heading.
 * @param array  $block         Parsed block.
 * @return string
 */
function wporg_jobs_blocks_search_title( string $block_content, array $block ): string {
	if ( ! is_search() || empty( $block['attrs']['type'] ) || 'search' !== $block['attrs']['type'] ) {
		return $block_content;
	}

	global $wp_query;
	$found = isset( $wp_query->found_posts ) ? (int) $wp_query->found_posts : 0;

	$text = sprintf(
		/* translators: 1: search term, 2: number of results. */
		esc_html__( 'Search Results for: %1$s %2$s', 'wporg-jobs-blocks' ),
		'<span class="wporg-jobs-search-term">' . esc_html( get_search_query() ) . '</span>',
		'<span class="wporg-jobs-search-count">(' . esc_html( number_format_i18n( $found ) ) . ')</span>'
	);

	return (string) preg_replace_callback(
		'#(<h[1-6][^>]*>).*?(</h[1-6]>)#s',
		static function ( array $matches ) use ( $text ): string {
			return $matches[1] . $text . $matches[2];
		},
		$block_content,
		1
	);
}
add_filter( 'render_block_core/query-title', 'wporg_jobs_blocks_search_title', 10, 2 );

/**
 * A button bound to a job's web application form opens it in a new tab as
 * user-supplied, unendorsed link; an email address opens the mail client.
 *
 * @param string   $block_content  Rendered button.
 * @param array    $block          Parsed block.
 * @param WP_Block $block_instance Block instance.
 * @return string
 */
function wporg_jobs_blocks_apply_button( string $block_content, array $block, WP_Block $block_instance ): string {
	$binding = $block['attrs']['metadata']['bindings']['url'] ?? null;
	if ( ! is_array( $binding ) || 'wporg-jobs/job-field' !== ( $binding['source'] ?? '' ) || 'apply_url' !== ( $binding['args']['key'] ?? '' ) ) {
		return $block_content;
	}

	$post_id = (int) ( $block_instance->context['postId'] ?? get_the_ID() );
	if ( '' === wporg_jobs_blocks_job_field( $post_id, 'apply_url' ) ) {
		return '';
	}
	if ( 'web' !== wporg_jobs_blocks_job_field( $post_id, 'howtoapply_method' ) ) {
		return $block_content;
	}

	$tags = new WP_HTML_Tag_Processor( $block_content );
	if ( $tags->next_tag( 'a' ) ) {
		$tags->set_attribute( 'target', '_blank' );
		$tags->set_attribute( 'rel', 'nofollow ugc noopener' );
	}
	return $tags->get_updated_html();
}
add_filter( 'render_block_core/button', 'wporg_jobs_blocks_apply_button', 10, 3 );
