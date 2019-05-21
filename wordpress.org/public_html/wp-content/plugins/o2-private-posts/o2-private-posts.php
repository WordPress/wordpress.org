<?php
/**
 * Plugin Name: O2 Private Posts
 * Description: Allows you to publish private posts to an O2/P2.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 *
 * @package WordPressdotorg\O2\PrivatePosts
 */

namespace WordPressdotorg\O2\PrivatePosts;

use o2_Tags;
use o2_ToDos;

/**
 * Initialize.
 */
function init() {
	if ( ! current_theme_supports( 'o2' ) || ! current_user_can( 'edit_private_posts' ) ) {
		return;
	}

	add_filter( 'o2_post_form_extras', __NAMESPACE__ . '\add_checkbox' );
	add_filter( 'wp_enqueue_scripts', __NAMESPACE__ . '\scripts', 11 );
	add_filter( 'o2_create_post', __NAMESPACE__ . '\set_post_status', 10, 2 );
	add_action( 'o2_writeapi_post_created', __NAMESPACE__ . '\handle_meta_data', 10, 2 );
	add_action( 'transition_post_status', __NAMESPACE__ . '\handle_admin_meta_data', 12, 3 );
}
add_filter( 'init', __NAMESPACE__ . '\init' );

/**
 * Add checkbox to o2 post form.
 *
 * @param string $post_form_extras Post form extras. Default: Empty string.
 * @return string
 */
function add_checkbox( $post_form_extras = '' ) {
	$label = esc_html__( 'Privately publish this post.', 'wporg' );

	$post_form_extras .= '<p class="comment-subscription-form" style="float: left; margin: 0 !important;">';
	$post_form_extras .= '<input type="checkbox" name="post_visibility" id="post_visibility" value="post_visibility" style="margin-left: 0.5em;"/>';
	$post_form_extras .= '<label style="font-size: 1.2em; margin: 0 0 0.5em 0.5em;" id="post_visibility_label" for="post_visibility"><small>' . $label . '</small></label>';
	$post_form_extras .= '</p>';

	return $post_form_extras;
}

/**
 * Adds additional JavaScript to editor scripts.
 */
function scripts() {
	$script = <<<'JS'
/* global o2 */
o2.Events.addFilter( 'post-save-data.o2', function( data, postForm ) {
	data._post_privately = postForm.$el.find( '#post_visibility' ).prop( 'checked' );
	return data;
}, null, 10, 2 );
JS;

	wp_add_inline_script( 'o2-editor', $script );
}

/**
 * Mark post as private.
 *
 * @param \WP_Post $post    The post to be created.
 * @param object   $message The message which was sent to o2's write API.
 */
function set_post_status( $post, $message ) {
	if ( ! empty( $message->_post_privately ) && $message->_post_privately ) {
		$post->post_status = 'private';
	}

	return $post;
}

/**
 * Sets O2_ToDo state and post tags.
 *
 * Since the post is not public, ToDos and post tags are not being set automatically.
 *
 * @param int    $post_id The post ID.
 * @param object $message The message which was sent to o2's write API.
 */
function handle_meta_data( $post_id, $message ) {
	if ( empty( $message->_post_privately ) ) {
		return;
	}

	if ( class_exists( 'o2_ToDos' ) ) {
		$o2_options = get_option( 'o2_options' );

		if ( isset( $o2_options['mark_posts_unresolved'] ) && $o2_options['mark_posts_unresolved'] ) {
			$new_state_slug = o2_ToDos::get_next_state_slug( o2_ToDos::get_first_state_slug() );
			o2_ToDos::set_post_state( $post_id, $new_state_slug );
		}
	}

	if ( class_exists( 'o2_Tags' ) ) {
		$post = get_post( $post_id );

		if ( ! empty( $GLOBALS['o2'] ) ) {
			$post_tags = $GLOBALS['o2']->tags->gather_all_tags( $post );
		} else {
			$post_tags = o2_Tags::find_tags( $post->post_content, true );
			$post_tags = array_unique( $post_tags );
		}

		if ( ! empty( $post_tags ) ) {
			wp_set_post_tags( $post_id, $post_tags, true );
		}
	}
}

/**
 * Sets O2_ToDo state and post tags when a private post is published from wp-admin.
 *
 * @param string   $new  Status being switched to.
 * @param string   $old  Status being switched from.
 * @param \WP_Post $post The full Post object.
 */
function handle_admin_meta_data( $new, $old, $post ) {
	if ( 'private' === $new && ( 'new' === $old || 'draft' === $old ) ) {
		handle_meta_data( $post->ID, (object) [ '_post_privately' => true ] );
	}
}
