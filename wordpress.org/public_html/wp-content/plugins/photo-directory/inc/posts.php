<?php
/**
 * Post handling customizations.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Posts {

	const META_KEY_MISSING_TAXONOMIES = '_missing_taxonomies';

	/**
	 * The registered image size that should be used for photos included in feeds.
	 */
	const RSS_PHOTO_SIZE = 'medium_large';

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

		// Ensure all custom taxonomies have been assigned values before publication.
		// Note: The add_action and hook priority are duplicated in `require_taxonomies_before_publishing()`.
		add_action( 'transition_post_status', [ __CLASS__, 'require_taxonomies_before_publishing' ], 1, 3 );

		// Sync photo post content to photo media on update.
		add_action( 'post_updated',       [ __CLASS__, 'sync_photo_post_to_photo_media_on_update' ], 5, 3 );

		// Offset subsequent paginations of front page by number of posts on front page.
		add_action( 'pre_get_posts',      [ __CLASS__, 'offset_front_page_paginations' ], 11 );
		// Fix pages count for front page paginations.
		add_filter( 'the_posts',          [ __CLASS__, 'fix_front_page_pagination_count' ], 10, 2 );

		// Modify REST response to include URL to small photo (used by Profiles).
		add_filter( "rest_prepare_{$post_type}", [ __CLASS__, 'rest_prepare_add_photo_url' ] );

		// Dedicate primary feed to photos.
		add_action( 'request',            [ __CLASS__, 'make_primary_feed_all_photos' ] );
		add_filter( 'the_content_feed',   [ __CLASS__, 'add_photo_to_rss_feed' ] );
		add_action( 'rss2_item',          [ __CLASS__, 'add_photo_as_enclosure_to_rss_feed' ] );
		add_filter( 'wp_get_attachment_image_attributes', [ __CLASS__, 'feed_attachment_image_attributes' ], 10, 3 );
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
	 * Prevents publication of a photo if any custom taxonomy hasn't been assigned
	 * at least one value.
	 *
	 * @param string  $new_status The new post status.
	 * @param string  $old_status The old post status.
	 * @param WP_Post $post       The post object.
	 */
	public static function require_taxonomies_before_publishing( $new_status, $old_status, $post ) {
		// Bail if post is not being published.
		if ( 'publish' !== $new_status ) {
			return;
		}

		// Bail if not a photo post.
		if ( Registrations::get_post_type() !== $post->post_type ) {
			return;
		}

		// Assume all custom taxonomies are required.
		$required_taxonomies = Registrations::get_taxonomy( 'all' );
		$missing_taxonomies = [];

		// Check each required taxonomy.
		foreach ( $required_taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $post->ID, $taxonomy, [ 'fields' => 'ids' ] );
			if ( count( $terms ) == 0 ) {
				$missing_taxonomies[] = $taxonomy;
			}
		}

		if ( $missing_taxonomies ) {
			// Prevent publishing.
			remove_action( 'transition_post_status', [ __CLASS__, 'require_taxonomies_before_publishing' ], 1 );
			wp_update_post( [ 'ID' => $post->ID, 'post_status' => $old_status ] );
			add_action( 'transition_post_status', [ __CLASS__, 'require_taxonomies_before_publishing' ], 1, 3 );

			// Store the missing taxonomies to later display them in an admin notice.
			update_post_meta( $post->ID, self::META_KEY_MISSING_TAXONOMIES, $missing_taxonomies );
		}
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
		if ( $query->is_home() && $query->is_main_query() ) {
			$front_page_count  = get_option( 'posts_per_page' );
			$archives_per_page = 30;

			$count = 1;
			$total_posts = $query->found_posts - $front_page_count;
			if ( $total_posts > 0 ) {
				$count += ceil( $total_posts / $archives_per_page );
			}
			// Account for logged-out pagination limit on w.org.
			if ( defined( '\WPORG_Page_Limiter::MAX_PAGES' ) && ! is_user_logged_in() ) {
				$count = min( $count, \WPORG_Page_Limiter::MAX_PAGES );
			}
			$query->max_num_pages = $count;
		}

		return $posts;
	}

	/**
	 * Returns the next post in the queue that the current user can moderate.
	 *
	 * By default it chooses a random photo in the queue that wasn't submitted
	 * by the current user.
	 *
	 * @param string $orderby The field to order posts by when determining the
	 *                        next post in queue, e.g. 'date'. Default 'rand'.
	 * @param string $order   The sort order used when determining the next post
	 *                        in queue. Either 'ASC' or 'DESC'. Default 'ASC'.
	 * @param int[]  $exclude Array of post IDs to exclude from being selected
	 *                        next. Default empty array.
	 * @return WP_Post|false The next post, or false if there are no other posts
	 *                       available for the user to moderate.
	 */
	public static function get_next_post_in_queue( $orderby = 'rand', $order = 'ASC', $exclude = [] ) {
		$next = false;

		if ( 'rand' === $orderby ) {
			$order = '';
		}
		elseif ( ! in_array( $order, [ 'ASC', 'DESC' ] ) ) {
			$order = 'ASC';
		}

		$posts = get_posts( [
			'author__not_in' => [ get_current_user_id() ],
			'order'          => $order,
			'orderby'        => $orderby,
			'post__not_in'   => $exclude,
			'posts_per_page' => 1,
			'post_status'    => 'pending',
			'post_type'      => Registrations::get_post_type(),
		] );

		if ( $posts ) {
			$next = $posts[0];
		}

		return $next;
	}

	/**
	 * Changes feeds to only serve photos by default.
	 *
	 * @param array $query_vars The array of requested query variables.
	 * @return array
	 */
	public static function make_primary_feed_all_photos( $query_vars ) {
		if ( isset( $query_vars['feed'] ) && ! isset( $query_vars['post_type'] ) ) {
			$query_vars['post_type'] = Registrations::get_post_type();
		}

		return $query_vars;
	}

	/**
	 * Outputs markup to include the photo in RSS feeds of photos.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function add_photo_to_rss_feed( $content ) {
		global $post;

		$content = trim( strip_tags( $content ) );

		if ( $post && Registrations::get_post_type() === get_post_type( $post ) && has_post_thumbnail( $post->ID ) ) {
			$content = '<figure>'
				. get_the_post_thumbnail( $post->ID, self::RSS_PHOTO_SIZE, [ 'alt' => $content, 'style' => 'margin-bottom: 10px;', 'srcset' => ' ' ] ) . "\n"
				. get_the_post_thumbnail( $post->ID, self::RSS_PHOTO_SIZE, [ 'style' => 'margin-bottom: 10px;', 'srcset' => ' ' ] ) . "\n"
				. ( $content ? "<figcaption aria-hidden=\"true\">{$content}</figcaption>\n" : '' )
				. "</figure>\n";
		}

		return $content;
	}

	/**
	 * Outputs an `enclosure` tag for a photo in RSS feeds of photos.
	 */
	public static function add_photo_as_enclosure_to_rss_feed() {
		global $post;

		// Bail if not a photo post.
		if ( ! $post || Registrations::get_post_type() !== get_post_type( $post ) ) {
			return;
		}

		// Bail if somehow there is no associated photo.
		$photo_id = get_post_thumbnail_id( $post );
		if ( ! $photo_id ) {
			return;
		}

		// Get the photo's URL.
		$photo_url = get_the_post_thumbnail_url( $post, self::RSS_PHOTO_SIZE );

		// Get the photo's MIME type.
		$mime_type = get_post_mime_type( $photo_id );

		// Get the photo's file size.
		$photo_meta = wp_get_attachment_metadata( $photo_id );
		if ( 'full' === self::RSS_PHOTO_SIZE ) {
			$filesize = $photo_meta['filesize'] ?? '';
		} else {
			$filesize = $photo_meta['sizes'][ self::RSS_PHOTO_SIZE ]['filesize'] ?? '';
		}

		if ( $photo_url && $mime_type && $filesize ) {
			// Output the enclosure tag.
			printf(
				'<enclosure url="%s" length="%s" type="%s" />' . "\n",
				esc_url( $photo_url ),
				esc_attr( $filesize ),
				esc_attr( $mime_type )
			);
		}
	}

	/**
	 * Overrides the image attributes for images shown in feed.
	 *
	 * @param string[]     $attr       Array of attribute values for the image markup, keyed by attribute name.
	 *                                 See `wp_get_attachment_image()`.
	 * @param WP_Post      $attachment Image attachment post.
	 * @param string|int[] $size       Requested image size. Can be any registered image size name, or
	 *                                 an array of width and height values in pixels (in that order).
	 * @return string[]
	 */
	public static function feed_attachment_image_attributes( $attr, $attachment, $size ) {
		if ( is_feed() ) {
			$attr['class'] = '';
			unset( $attr['srcset'] );
		}

		return $attr;
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Posts', 'init' ] );
