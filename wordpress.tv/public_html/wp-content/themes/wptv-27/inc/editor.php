<?php
/**
 * Video editing screen: the block editor with exactly one VideoPress block.
 *
 * The classic editor and new shortcode authoring are retired for videos
 * (docs/decisions.md, 2026-10-21). Existing shortcode posts still render until
 * their content is migrated; assets/js/editor.js converts a shortcode-only post
 * to a block when it is opened. Taxonomies stay native; editor.js only
 * provides the video data panel.
 *
 * @package WordPressTV
 */

namespace WordPressTV\Editor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const VIDEO_BLOCK = 'videopress/video';

/**
 * Always edits videos with the block editor, never the classic editor.
 *
 * @param bool   $use_block_editor Whether to use the block editor.
 * @param string $post_type        Post type.
 * @return bool
 */
function use_block_editor( $use_block_editor, $post_type ) {
	return 'post' === $post_type ? true : $use_block_editor;
}
add_filter( 'use_block_editor_for_post_type', __NAMESPACE__ . '\\use_block_editor', 100, 2 );

/**
 * New videos start with one locked VideoPress block. Moderators can replace
 * the video, but not add, move or remove blocks.
 *
 * @param array  $args      Post type arguments.
 * @param string $post_type Post type.
 * @return array
 */
function post_type_args( $args, $post_type ) {
	if ( 'post' === $post_type ) {
		$args['template']      = array( array( VIDEO_BLOCK ) );
		$args['template_lock'] = 'all';
	}
	return $args;
}
add_filter( 'register_post_type_args', __NAMESPACE__ . '\\post_type_args', 10, 2 );

/**
 * Only the VideoPress block is available when editing a video.
 *
 * @param bool|string[]            $allowed Allowed block types.
 * @param \WP_Block_Editor_Context $context Editor context.
 * @return bool|string[]
 */
function allowed_blocks( $allowed, $context ) {
	return isset( $context->post ) && 'post' === $context->post->post_type ? array( VIDEO_BLOCK ) : $allowed;
}
add_filter( 'allowed_block_types_all', __NAMESPACE__ . '\\allowed_blocks', 10, 2 );

/**
 * Exposes the editable video data the block editor cannot reach today.
 * `producer-username` is the validated producer field (docs/backlog.md, B3);
 * `producer` and `flavor` are being retired and stay out of the editor.
 * Only REST exposure is added: rewrites, query vars and archives are unchanged.
 *
 * @param array  $args     Taxonomy arguments.
 * @param string $taxonomy Taxonomy.
 * @return array
 */
function taxonomy_args( $args, $taxonomy ) {
	if ( 'producer-username' === $taxonomy ) {
		$args['show_in_rest'] = true;
		$args['rest_base']    = 'producer-username';
	}
	return $args;
}
add_filter( 'register_taxonomy_args', __NAMESPACE__ . '\\taxonomy_args', 10, 2 );

/** Registers the slides URL (legacy Video Info box) for the block editor. */
function register_meta() {
	register_post_meta(
		'post',
		'_wptv_slides_url',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => static function ( $url ) {
				return esc_url_raw( (string) $url, array( 'http', 'https' ) );
			},
			'auth_callback'     => static function ( $allowed, $meta_key, $post_id ) {
				return current_user_can( 'edit_post', $post_id );
			},
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\\register_meta' );

/**
 * Keeps only the video block when a video post contains exactly one, removing
 * stray empty paragraphs and anything else. Other content is left untouched,
 * so unmigrated shortcode posts are never altered on save.
 *
 * @param array $data Slashed post data.
 * @return array
 */
function keep_only_video_block( $data ) {
	if ( 'post' !== ( $data['post_type'] ?? '' ) ) {
		return $data;
	}
	$content = wp_unslash( $data['post_content'] );
	if ( ! has_blocks( $content ) ) {
		return $data;
	}
	$videos = array_values(
		array_filter(
			parse_blocks( $content ),
			static function ( $block ) {
				return VIDEO_BLOCK === $block['blockName'];
			}
		)
	);
	if ( 1 === count( $videos ) ) {
		$data['post_content'] = wp_slash( serialize_block( $videos[0] ) );
	}
	return $data;
}
add_filter( 'wp_insert_post_data', __NAMESPACE__ . '\\keep_only_video_block' );

/**
 * Loads the video editing restrictions and data panel on the video editor only.
 * Enqueued early in the header so its block-support filter runs before the
 * VideoPress block registers.
 */
function enqueue_editor_assets() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'post' !== $screen->post_type || ! $screen->is_block_editor() ) {
		return;
	}
	wp_enqueue_script(
		'wptv-editor',
		get_theme_file_uri( 'assets/js/editor.js' ),
		array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-core-data', 'wp-data', 'wp-dom-ready', 'wp-editor', 'wp-element', 'wp-hooks', 'wp-html-entities', 'wp-i18n', 'wp-notices', 'wp-plugins' ),
		wp_get_theme()->get( 'Version' ),
		false
	);
	wp_set_script_translations( 'wptv-editor', 'wptv' );
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_editor_assets', 5 );
