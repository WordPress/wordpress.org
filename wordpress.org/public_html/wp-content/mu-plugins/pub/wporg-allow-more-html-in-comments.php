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
	add_filter( 'pre_comment_content', __NAMESPACE__ . '\\filter_comment_content' );
}

/**
 * Applies the post HTML filters to comment content, then drops o2's own classes.
 *
 * Mirrors wp_filter_post_kses(), which is what this used to hook directly.
 *
 * @param string $data Slashed comment content.
 * @return string Slashed comment content.
 */
function filter_comment_content( $data ) {
	return addslashes( strip_o2_control_classes( wp_kses( stripslashes( $data ), 'post' ) ) );
}

/**
 * Removes o2's control classes from comment HTML.
 *
 * These classes are how o2 binds its post actions, and the lookup that picks an
 * editor to read, across the whole post article rather than to the controls it
 * rendered itself. Comments live in that article and their HTML comes from the
 * commenter, so it must not be able to present itself as one of those controls.
 *
 * @param string $html Unslashed comment HTML.
 * @return string
 */
function strip_o2_control_classes( $html ) {
	if ( false === stripos( $html, 'o2-' ) ) {
		return $html;
	}

	$tags = new \WP_HTML_Tag_Processor( $html );

	while ( $tags->next_tag() ) {
		$remove = array();

		foreach ( $tags->class_list() as $class ) {
			if ( str_starts_with( strtolower( $class ), 'o2-' ) ) {
				$remove[] = $class;
			}
		}

		foreach ( $remove as $class ) {
			$tags->remove_class( $class );
		}
	}

	return $tags->get_updated_html();
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