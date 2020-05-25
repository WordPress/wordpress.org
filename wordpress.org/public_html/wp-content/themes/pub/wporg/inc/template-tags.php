<?php
/**
 * Custom template tags
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\Theme;

if ( ! function_exists( __NAMESPACE__ . '\entry_meta' ) ) :
	/**
	 * Prints HTML with meta information for the categories, tags.
	 *
	 * Create your own  WordPressdotorg\Theme\entry_meta() function to override in a child theme.
	 */
	function entry_meta() {
		if ( in_array( get_post_type(), array( 'post', 'attachment' ), true ) ) {
			$time_string = sprintf(
				'<a href="%1$s" rel="bookmark">%2$s</a>',
				esc_url( get_permalink() ),
				get_entry_date()
			);

			$author_string = sprintf(
				'<span class="entry-author vcard"><a class="url fn n" href="%1$s">%2$s</a></span>',
				esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
				get_the_author()
			);

			// phpcs:disable WordPress.XSS.EscapeOutput.OutputNotEscaped
			printf(
				/* translators: 1: post date 2: post author */
				'<span class="posted-on">' . __( 'Posted on %1$s by %2$s.', 'wporg' ) . '</span>',
				$time_string,
				$author_string
			);
			// phpcs:enable WordPress.XSS.EscapeOutput.OutputNotEscaped
		}

		$format = get_post_format();
		if ( current_theme_supports( 'post-formats', $format ) ) {
			printf(
				'<span class="entry-format">%1$s<a href="%2$s">%3$s</a></span>',
				sprintf( '<span class="screen-reader-text">%s </span>', esc_html_x( 'Format', 'Used before post format.', 'wporg' ) ),
				esc_url( get_post_format_link( $format ) ),
				esc_html( get_post_format_string( $format ) )
			);
		}

		if ( 'post' === get_post_type() ) {
			entry_taxonomies();
		}

		if ( ! is_singular() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
			echo '<span class="comments-link">';
			comments_popup_link( sprintf(
				/* translators: Post title. */
				__( 'Leave a comment<span class="screen-reader-text"> on %s</span>', 'wporg' ),
				get_the_title()
			) );
			echo '</span>';
		}
	}
endif;

if ( ! function_exists( __NAMESPACE__ . '\get_entry_date' ) ) :
	/**
	 * Prints HTML with published and updated information for current post.
	 *
	 * Create your own  WordPressdotorg\Theme\get_entry_date() function to override in a child theme.
	 */
	function get_entry_date() {
		$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';

		if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
			$time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
		}

		return sprintf(
			$time_string,
			esc_attr( get_the_date( 'c' ) ),
			get_the_date(),
			esc_attr( get_the_modified_date( 'c' ) ),
			get_the_modified_date()
		);
	}
endif;

if ( ! function_exists( __NAMESPACE__ . '\entry_date' ) ) :
	/**
	 * Prints HTML with date information for current post.
	 *
	 * Create your own  WordPressdotorg\Theme\entry_date() function to override in a child theme.
	 */
	function entry_date() {
		printf(
			'<span class="posted-on">%1$s <a href="%2$s" rel="bookmark">%3$s</a></span>',
			esc_html_x( 'Posted on', 'Used before publish date.', 'wporg' ),
			esc_url( get_permalink() ),
			get_entry_date() // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
		);
	}
endif;

if ( ! function_exists( __NAMESPACE__ . '\entry_taxonomies' ) ) :
	/**
	 * Prints HTML with category and tags for current post.
	 *
	 * Create your own WordPressdotorg\Theme\entry_taxonomies() function to override in a child theme.
	 */
	function entry_taxonomies() {
		$categories_list = get_the_category_list( _x( ', ', 'Used between list items, there is a space after the comma.', 'wporg' ) );
		if ( $categories_list && categorized_blog() ) {
			printf(
				'<span class="cat-links"><span class="screen-reader-text">%1$s </span>%2$s</span>',
				esc_html_x( 'Categories', 'Used before category names.', 'wporg' ),
				$categories_list // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			);
		}

		$tags_list = get_the_tag_list( '', _x( ', ', 'Used between list items, there is a space after the comma.', 'wporg' ) );
		if ( $tags_list ) {
			printf(
				'<span class="tags-links"><span class="screen-reader-text">%1$s </span>%2$s</span>',
				esc_html_x( 'Tags', 'Used before tag names.', 'wporg' ),
				$tags_list // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			);
		}
	}
endif;

if ( ! function_exists( __NAMESPACE__ . '\categorized_blog' ) ) :
	/**
	 * Determines whether blog/site has more than one category.
	 *
	 * Create your own  WordPressdotorg\Theme\categorized_blog() function to override in a child theme.
	 *
	 * @return bool True if there is more than one category, false otherwise.
	 */
	function categorized_blog() {
		$all_the_cool_cats = get_transient( 'wporg_categories' );

		if ( false === $all_the_cool_cats ) {
			// Create an array of all the categories that are attached to posts.
			$all_the_cool_cats = get_categories( array(
				'fields' => 'ids',
				// We only need to know if there is more than one category.
				'number' => 2,
			) );

			// Count the number of categories that are attached to the posts.
			$all_the_cool_cats = count( $all_the_cool_cats );

			set_transient( 'wporg_categories', $all_the_cool_cats );
		}

		if ( $all_the_cool_cats > 1 ) {
			// This blog has more than 1 category so wporg_categorized_blog should return true.
			return true;
		} else {
			// This blog has only 1 category so wporg_categorized_blog should return false.
			return false;
		}
	}
endif;

/**
 * Flushes out the transients used in wporg_categorized_blog().
 */
function category_transient_flusher() {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// Like, beat it. Dig?
	delete_transient( 'wporg_categories' );
}
add_action( 'edit_category', __NAMESPACE__ . '\category_transient_flusher' );
add_action( 'save_post', __NAMESPACE__ . '\category_transient_flusher' );
