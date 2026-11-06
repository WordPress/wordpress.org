<?php
/**
 * Single-video block rendering adjustments.
 *
 * @package WordPressTV
 */

namespace WordPressTV\Single;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps the complete description and slides link in the single-video excerpt.
 *
 * Core Post Excerpt trims even explicit excerpts and removes links. Reuse its
 * wrapper, but render the legacy full excerpt inside this theme-owned instance.
 * No excerpt filters or lengths change for feeds, archives or other blocks.
 *
 * @param string    $content Rendered markup.
 * @param array     $parsed  Parsed block.
 * @param \WP_Block $block   Block instance.
 * @return string
 */
function full_description( $content, $parsed, $block ) {
	$classes = preg_split( '/\s+/', $parsed['attrs']['className'] ?? '' );
	$post_id = (int) ( $block->context['postId'] ?? 0 );
	if ( ! in_array( 'wptv-single__excerpt', $classes, true ) || ! $post_id ) {
		return $content;
	}
	$excerpt = get_the_excerpt( $post_id );
	$excerpt = apply_filters( 'the_excerpt', $excerpt ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter.
	return preg_replace_callback(
		'/^(<div\b[^>]*>).*<\/div>$/s',
		static function ( $matches ) use ( $excerpt ) {
			return $matches[1] . $excerpt . '</div>';
		},
		$content
	);
}
add_filter( 'render_block_core/post-excerpt', __NAMESPACE__ . '\\full_description', 10, 3 );
