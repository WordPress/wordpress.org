<?php

/**
 * Enqueue Codex Stylesheet (Generated from LESS files)
 */
function bpcodex_register_stylesheet() {
	wp_enqueue_style( 'bp-codex-screen', get_stylesheet_directory_uri() . '/screen.css', false, '2.0.9', 'screen' );
	wp_enqueue_style( 'google-font-source', 'http://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,700', false, '1.0', 'all' );
}
add_action( 'wp_enqueue_scripts', 'bpcodex_register_stylesheet' );

/**
 * When a Page has been edited, record that to the activity stream.
 *
 * @global BuddyPress $bp BuddyPress global settings
 * @param int $post_id ID of Page that's just been saved
 * @param WP_Post $post Post object
 * @since 1.6
 */
function bpc_record_page_edits( $post_id, $post ) {
	global $bp;

	// Check Activity is active
	if ( ! bp_is_active( 'activity' ) )
		return;

	$post_id = (int) $post_id;
	$blog_id = get_current_blog_id();

	/**
	 * Stop infinite loops with WordPress MU Sitewide Tags.
	 * That plugin changed the way its settings were stored at some point. Thus the dual check.
	 */
	if ( ! empty( $bp->site_options['sitewide_tags_blog'] ) ) {
		$st_options   = maybe_unserialize( $bp->site_options['sitewide_tags_blog'] );
		$tags_blog_id = isset( $st_options['tags_blog_id'] ) ? $st_options['tags_blog_id'] : 0;
	} else {
		$tags_blog_id = isset( $bp->site_options['tags_blog_id'] ) ? $bp->site_options['tags_blog_id'] : 0;
	}

	if ( $blog_id == $tags_blog_id && apply_filters( 'bp_blogs_block_sitewide_tags_activity', true ) )
		return;

	// Don't record this if it's not a Page
	if ( ! in_array( $post->post_type, apply_filters( 'bpc_record_page_edit_post_types', array( 'page' ) ) ) )
		return;

	$is_blog_public = apply_filters( 'bp_is_blog_public', (int) get_blog_option( $blog_id, 'blog_public' ) );

	if ( 'publish' != $post->post_status || ! empty( $post->post_password ) )
		return;

	// If multisite, only record on public blogs.
	if ( $is_blog_public || ! is_multisite() ) {

		// Record this in activity streams
		$content        = $post->post_content;
		$post_permalink = get_permalink( $post_id );

		// Get post author from the last post revision
		$revision = wp_get_post_revisions( $post_id, array( 'numberposts' => 2 ) );

		if ( ! empty( $revision ) ) {
			$revision    = array_reverse( $revision );
			$revision    = array_pop( $revision );
			$post_author = (int) $revision->post_author;

			/**
			 * @todo Maybe conditionally filter KSES on input/output and add CSS to Codex template?
			 *
			 * wp_text_diff() outputs a table. This gets killed twice; to see it, comment out the
			 * "bp_get_activity_content_body" and "bp_activity_content_before_save" KSES filters.
			 *
			 * Also be aware of bp_create_excerpt() in the bp_blogs_record_activity() chain.
			 */
			// Get diff
			//$content = wp_text_diff( $revision->post_content, $post->post_content );

		// No revisions, so use original post author
		} else {
			$post_author = (int) $post->post_author;
		}

		// If there's no entry, this is a new post and thus will be handled by bp_blogs_record_post().
		if ( is_multisite() )
			$activity_action = sprintf( __( '%1$s updated the %2$s page, on the %3$s', 'buddypress' ), bp_core_get_userlink( $post_author ), '<a href="' . $post_permalink . '">' . $post->post_title . '</a>', '<a href="' . get_blog_option( $blog_id, 'home' ) . '">' . get_blog_option( $blog_id, 'blogname' ) . '</a>' );
		else
			$activity_action = sprintf( __( '%1$s updated the %2$s page', 'buddypress' ), bp_core_get_userlink( $post_author ), '<a href="' . $post_permalink . '">' . $post->post_title . '</a>' );

		// Record a new activity
		bp_blogs_record_activity( array(
			'action'            => apply_filters( 'bpc_record_page_edit_action',       $activity_action, $post, $post_permalink ),
			'content'           => apply_filters( 'bpc_record_page_edit_content',      $content, $post,  $post_permalink        ),
			'item_id'           => $blog_id,
			'primary_link'      => apply_filters( 'bpc_record_page_edit_primary_link', $post_permalink,  $post_id               ),
			'recorded_time'     => $post->post_modified_gmt,
			'secondary_item_id' => $post_id . '|' . time(), // Make the ID unique for each edit to generate a new activity item each time
			'type'              => 'bpc_page_edit',
			'user_id'           => $post_author,
		) );
	}

	// Update the blogs last activity
	bp_blogs_update_blogmeta( $blog_id, 'last_activity', bp_core_current_time() );

	do_action( 'bpc_record_page_edits', $post_id, $post, $post_author );
}
add_action( 'save_post', 'bpc_record_page_edits', 15, 2 );
