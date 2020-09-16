<?php
/**
 * Plugin Name: Allow more HTML in comments on P2 and o2 blogs
 * Description: Forces comments to go through the more liberal post HTML filters, rather than the restrictive comment filters.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * @package WordPressdotorg\AllowMoreHtmlInComments
 */

namespace WordPressdotorg\AllowMoreHtmlInComments;

/**
 * Registers actions if p2 or p2-breathe is active.
 */
function init() {
	$template = get_template();
	if ( 'p2' !== $template && 'p2-breathe' !== $template ) {
		return;
	}

	// kses_init() runs just prior, on the same priority.
	add_action( 'init', __NAMESPACE__ . '\p2_kses_init' );
	add_action( 'set_current_user', __NAMESPACE__ . '\p2_kses_init' );

	add_filter( 'force_filtered_html_on_import', __NAMESPACE__ . '\force_filtered_html_on_import', 10000 );

	add_filter( 'wp_kses_allowed_html', __NAMESPACE__ . '\wp_kses_allowed_html' );
}
add_action( 'setup_theme', __NAMESPACE__ . '\init' );

/**
 * Initializes custom kses filters if current user can't post unfiltered HTML.
 */
function p2_kses_init() {
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		p2_kses_init_filters();
	}
}

/**
 * Replaces kses filter for comment content.
 */
function p2_kses_init_filters() {
	remove_filter( 'pre_comment_content', 'wp_filter_kses' );
	add_filter( 'pre_comment_content', 'wp_filter_post_kses' );
}

/**
 * Sets custom kses filters for imported data.
 *
 * @param bool $force Whether to force data to be filtered through kses.
 * @return bool
 */
function force_filtered_html_on_import( $force ) {
	if ( $force ) {
		kses_init_filters();
		p2_kses_init();

		// Don't have core fire kses_init_filters(), we already did.
		return false;
	}

	return $force;
}

/**
 * Remove <title> as a valid post tag, this should never actually be used and breaks o2.
 */
function wp_kses_allowed_html( $tags ) {
	if ( is_array( $tags ) ) {
		unset( $tags['title'] );
	}

	return $tags;
}