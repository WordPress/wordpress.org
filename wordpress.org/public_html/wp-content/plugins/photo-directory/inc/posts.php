<?php
/**
 * Post handling customizations.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Posts {

	/**
	 * Initializer.
	 */
	public static function init() {
		$post_type = Registrations::get_post_type();

		add_action( "publish_{$post_type}", [ __CLASS__, 'make_photo_attachment_available' ] );
		add_action( 'before_delete_post', [ __CLASS__, 'delete_attachments' ], 10, 2 );
		add_action( 'delete_attachment',  [ __CLASS__, 'delete_photo_post' ], 10, 2 );
		add_action( 'template_redirect',  [ __CLASS__, 'redirect_attachment_page_to_photo' ] );
		add_filter( 'attachment_link',    [ __CLASS__, 'use_photo_url_instead_of_media_permalink_url' ], 10, 2 );

		// Sync photo post content to photo media on update.
		add_action( 'post_updated',       [ __CLASS__, 'sync_photo_post_to_photo_media_on_update' ], 5, 3 );

		// Offset subsequent paginations of front page by number of posts on front page.
		add_action( 'pre_get_posts',      [ __CLASS__, 'offset_front_page_paginations' ], 11 );
		// Fix pages count for front page paginations.
		add_filter( 'the_posts',          [ __CLASS__, 'fix_front_page_pagination_count' ], 10, 2 );

		// Modify REST response to include URL to small photo (used by Profiles).
		add_filter( "rest_prepare_{$post_type}", [ __CLASS__, 'rest_prepare_add_photo_url' ] );
	}

	/**
	 * Amends REST response for photos to include URL to small version of photo.
	 *
	 * @param WP_REST_Response $response
	 * @return WP_REST_Response
	 */
	public static function rest_prepare_add_photo_url( $response ) {
		$data = $response->get_data();

		$data['photo-thumbnail-url'] = wp_get_attachment_image_src( get_post_thumbnail_id( $data['id'] ), 'medium' )[0] ?? '';

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Changes the post status for photo post's associated photo media to
	 * 'inherit' so it becomes available.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function make_photo_attachment_available( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return;
		}

		$attachments = get_attached_media( '', $post );

		foreach ( $attachments as $attachment ) {
			if ( get_post_status( $attachment ) === 'private' ) {
				$attachment->post_status = 'inherit';
				wp_update_post( $attachment );
			}
		}
	}

	/**
	 * Deletes attachments when a photo post is deleted.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function delete_attachments( $post_id, $post ) {
		// Ensure the post is appropriate post type.
		if ( get_post_type( $post ) !== Registrations::get_post_type() ) {
			return;
		}

		$attachments = get_attached_media( '', $post );

		// Unhook automatic deletion of associated photo post.
		remove_action( 'delete_attachment', [ __CLASS__, 'delete_photo_post' ], 10 );

		foreach ( $attachments as $attachment ) {
			wp_delete_attachment( $attachment->ID, true );
		}

		// Rehook automatic deletion of associated photo post.
		add_action( 'delete_attachment', [ __CLASS__, 'delete_photo_post' ], 10, 2 );
	}

	/**
	 * Deletes associated photo post when a photo is deleted.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function delete_photo_post( $post_id, $post ) {
		// Must be deletion of an attachment.
		if ( 'attachment' !== get_post_type( $post ) ) {
			return;
		}

		// Must be attached to a post.
		$parent_id = wp_get_post_parent_id( $post->ID );
		if ( ! $parent_id ) {
			return;
		}

		// Attached post must exist.
		$parent = get_post( $parent_id );
		if ( ! $parent ) {
			return;
		}

		// Attached post must be a photo post.
		if ( get_post_type( $parent ) !== Registrations::get_post_type() ) {
			return;
		}

		// Unhook automatic deletion of attachments.
		remove_action( 'before_delete_post', [ __CLASS__, 'delete_attachments' ], 10 );

		// Delete post.
		wp_delete_post( $parent_id );

		// Rehook automatic deletion of attachments.
		add_action( 'before_delete_post', [ __CLASS__, 'delete_attachments' ], 10, 2 );
	}

	/**
	 * Redirects attachment permalink pages to the associated photo post page.
	 */
	public static function redirect_attachment_page_to_photo() {
		// Must be request for attachment.
		if ( ! is_attachment() ) {
			return;
		}

		// Must relate to a post.
		$post = get_post();
		if ( ! $post ) {
			return;
		}

		// Must be attached to a post.
		$parent_id = wp_get_post_parent_id( $post->ID );
		if ( ! $parent_id ) {
			return;
		}

		// Attached post must exist.
		$parent = get_post( $parent_id );
		if ( ! $parent ) {
			return;
		}

		// Attached post must be a photo post.
		if ( get_post_type( $parent ) !== Registrations::get_post_type() ) {
			return;
		}

		wp_redirect( wp_get_attachment_url( $post->ID ) );
		exit;
	}

	/**
	 * Returns the URL for an image instead of the image's permalink page URL.
	 *
	 * @param string $url     The attachment's permalink.
	 * @param int    $post_id Attachment ID.
	 * @return string
	 */
	public static function use_photo_url_instead_of_media_permalink_url( $url, $post_id ) {
		// Must be attached to a post.
		$parent_id = wp_get_post_parent_id( $post_id );
		if ( ! $parent_id ) {
			return $url;
		}

		// Attached post must be a photo post.
		if ( get_post_type( $parent_id ) !== Registrations::get_post_type() ) {
			return $url;
		}

		return wp_get_attachment_url( $post_id );
	}

	/**
	 * Syncs the photo post content to the caption for the associated photo media.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object following the update
	 * @param WP_Post $post_before Post object before the update.
	 */
	public static function sync_photo_post_to_photo_media_on_update( $post_id, $post_after, $post_before ) {
		// Bail if not a photo post.
		if ( get_post_type( $post_id ) !== Registrations::get_post_type() ) {
			return;
		}

		$old_content = $post_before->post_content;
		$new_content = $post_after->post_content;

		// Bail if content is not changing.
		if ( $old_content === $new_content ) {
			return;
		}

		// Bail if no associated photo media.
		$image_id = get_post_thumbnail_id( $post_id );
		if (! $image_id ) {
			return;
		}

		// Bail if unable to get photo media post.
		$image = get_post( $image_id );
		if ( ! is_object( $image ) ) {
			return;
		}

		// Sync excerpt and content to photo_media.
		$image->post_excerpt = $new_content;
		$image->post_content = $new_content;
		wp_update_post( $image );
	}

	/**
	 * Changes front page non-first paginated queries to include an offset of
	 * the number of posts shown on the front page.
	 *
	 * The front page shows fewer photos than subsequent pages.
	 *
	 * @param WP_Query Query object.
	 */
	public static function offset_front_page_paginations( $query ) {
		$front_page_count = get_option( 'posts_per_page' );

		if ( $query->is_home() && $query->is_paged() && $query->is_main_query() ) {
			// Offset by number of posts on the front page and then number of posts on pages subsequent to the current one.
			$offset = $front_page_count + ( ( $query->query_vars['paged'] - 2 ) * $query->query_vars['posts_per_page'] );
			$query->set( 'offset', $offset );
		}
	}

	/**
	 * Fixes the max_page_num value for a query when dealing with front page pagination.
	 *
	 * The front page shows fewer photos than subsequent pages. This can affect the
	 * pagination counts as shown in the pagination nav.
	 *
	 * @todo Fix the value for the first page. We don't shown the pagination nav
	 *       or use the count of results on the first page, so no need to do yet.
	 */
	public static function fix_front_page_pagination_count( $posts, $query ) {
		if ( $query->is_home() ) {
			$front_page_count  = get_option( 'posts_per_page' );
			$archives_per_page = 30;

			$count = 1;
			$total_posts = $query->found_posts - $front_page_count;
			if ( $total_posts > 0 ) {
				$count += ceil( $total_posts / $archives_per_page );
			}
			$query->max_num_pages = $count;
		}

		return $posts;
	}

	/**
	 * Returns the next post in the queue that the current user can moderate.
	 *
	 * @return WP_Post|false The next post, or false if there are no other posts
	 *                       available for the user to moderate.
	 */
	public static function get_next_post_in_queue() {
		$next = false;

		$posts = get_posts( [
			'author__not_in' => [ get_current_user_id() ],
			'order'          => 'ASC',
			'orderby'        => 'date',
			'posts_per_page' => 1,
			'post_status'    => 'pending',
			'post_type'      => Registrations::get_post_type(),
		] );

		if ( $posts ) {
			$next = $posts[0];
		}

		return $next;
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Posts', 'init' ] );
