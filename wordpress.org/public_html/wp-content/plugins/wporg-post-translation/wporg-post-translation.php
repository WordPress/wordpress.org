<?php
/**
 * Plugin Name: Post Translation
 * Description: Translates WordPress post/page content via GlotPress.
 * Version: 1.0
 * Author: WordPress.org
 * License: GPLv2
 */

namespace WordPressdotorg\Post_Translation;

defined( 'ABSPATH' ) || exit;

const TEXTDOMAIN_PREFIX = 'dynamic-glotpress/';
const PROJECT_BASE      = 'post-content';
const META_KEY_ENABLED  = '_post_translation_enabled';

/**
 * Bootstrap the plugin.
 */
function bootstrap() {
	if ( ! class_exists( 'GlotPress_Translate_Bridge' ) ) {
		return;
	}

	require_once __DIR__ . '/inc/class-post-parser.php';
	require_once __DIR__ . '/inc/class-frontend.php';
	require_once __DIR__ . '/inc/class-admin.php';
	require_once __DIR__ . '/inc/class-editor.php';
	require_once __DIR__ . '/inc/class-cli.php';

	// Share the translation cache last_changed token across the multisite network,
	// so imports running on translate.w.org invalidate frontend caches on source blogs.
	wp_cache_add_global_groups( [ Frontend::CACHE_GROUP ] );

	Admin::init();
	Frontend::init();
	Editor::init();
	CLI::init();
}
add_action( 'init', __NAMESPACE__ . '\bootstrap', 9 );

/**
 * Check if translation is enabled for a post.
 */
function is_translation_enabled( $post ) {
	$post = get_post( $post );

	if ( ! $post ) {
		return false;
	}

	$enabled = (bool) get_post_meta( $post->ID, META_KEY_ENABLED, true );

	return apply_filters( 'post_translation_enabled', $enabled, $post );
}

/**
 * Get the GlotPress translation project path for a post.
 */
function get_translation_project( $post ) {
	$post = get_post( $post );

	if ( ! $post || ! is_translation_enabled( $post ) ) {
		return false;
	}

	return apply_filters( 'post_translation_project', get_site_project(), $post );
}

/**
 * Get the default GlotPress project path for the current site.
 */
function get_site_project() {
	$slug = sanitize_title_with_dashes(
		str_replace( [ 'https://', 'http://', '/' ], [ '', '', '-' ], home_url() )
	);

	$project = PROJECT_BASE . '/' . $slug;

	return apply_filters( 'post_translation_site_project', $project, PROJECT_BASE, $slug );
}
