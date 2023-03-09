<?php

namespace WordPressdotorg\PatternPreview\PageIntercept;

/**
 * Returns ID of pattern page post.
 * 
 * @return int;
 */
function get_pattern_page_id() {
	return 256;
}

/**
 * Returns whether we should intercept the page content.
 *
 * @return boolean
 */
function should_filter() {
	return is_page() && ! empty( get_pattern_name_from_url() );
}

/**
 * Return the name of the pattern from the $_GET request.
 *
 * @return string
 */
function get_pattern_name_from_url() {
	if ( ! isset( $_GET['pattern_name'] ) ) {
		return '';
	}

	return sanitize_text_field( urldecode( $_GET['pattern_name'] ) );
}

/**
 * Updates the content to render the pattern.
 *
 * @param string $content
 * @return string
 */
function handle_content_filter( $content ) {
	/*
	 * Handle the_content being run within `render_block()`, we don't want to override those recursive calls,
	 * but we still need to filter `the_content` if it's called multiple times.
	 */
	static $running = false;

	if ( should_filter() && ! $running ) {
		$running = true;

		$block_string = '<!-- wp:wporg/patterns-preview { "pattern-name": "' . get_pattern_name_from_url() . '" } /-->';
		$block        = parse_blocks( $block_string );

		$content = render_block( array_shift( $block ) );
		$running = false;
	}

	return $content;
}
add_filter( 'the_content', __NAMESPACE__ . '\handle_content_filter', 5 );

/**
 * Updates the title to be the pattern title.
 *
 * @param string      $title
 * @param int|WP_Post $post_id A post_id on the_title filter, WP_Post on single_post_title.
 * @return string
 */
function handle_title_filter( $title, $post_id = 0 ) {
	$post_id = is_object( $post_id ) ? $post_id->ID : $post_id;

	static $running = false;
	if ( should_filter() && ! $running && ( ! $post_id || get_pattern_page_id() == $post_id )  ) {
		$running = true;

		$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( get_pattern_name_from_url() );

		$title  = sprintf(
			// translators: %s Name of the pattern
			__( 'Pattern: %s', 'wporg' ),
			esc_html( $pattern['title'] )
		);
		$running = false;
	}

	return $title;
}
add_filter( 'the_title', __NAMESPACE__ . '\handle_title_filter', 5, 2 );
add_filter( 'single_post_title', __NAMESPACE__ . '\handle_title_filter', 5, 2 );

/**
 * Turns off comments when intercepts.
 *
 * @return boolean
 */
function handle_comments_filter() {
	return ! should_filter();
}
add_filter( 'comments_open', __NAMESPACE__ . '\handle_comments_filter', 5 );

/**
 * Changes the pattern page dummy page status to publish to render the default template.
 * We don't want that post to be published because it will show up in all the menus.
 *
 * @return WP_POST[]
 */
function modify_pattern_page( $posts ) {
	if ( is_admin() || empty( $posts ) ) {
		return $posts;
	}

	if ( get_pattern_page_id() == $posts[0]->ID ) {
		$posts[0]->post_status = 'publish';
	}

	return $posts;
}

add_action( 'posts_results', __NAMESPACE__ . '\modify_pattern_page', 5 );

/**
 * Updates the page_id to the pattern page id when pre-defined page id is found in query.
 *
 * @param WP_QUERY $query
 * @return void
 */
function modify_pattern_page_query( $query ) {
	$pre_defined_page_id = 9999;

	if ( $query->is_main_query() && $pre_defined_page_id == $query->query_vars['page_id'] ) {
		$query->set( 'page_id', get_pattern_page_id() );
	}
}
add_action( 'pre_get_posts', __NAMESPACE__ . '\modify_pattern_page_query', 5 );

/**
 * Updates query block 'inherit' parameter to false to fix post loading.
 * See: https://meta.trac.wordpress.org/ticket/6676
 *
 * @param array         $context
 * @param array         $parsed_block
 * @param WP_Block|null $parent_block
 * @return array
 */
function modify_query_block_context( $context, $parsed_block, $parent_block ) {

	if ( $parent_block && 'core/query' === $parent_block->parsed_block['blockName'] ) {
		$context['query']['inherit'] = false;
	}

	return $context;
}

add_filter( 'render_block_context', __NAMESPACE__ . '\modify_query_block_context', 10, 3 );
