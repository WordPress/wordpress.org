<?php
/**
 * Custom template tags for this theme.
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;
use WordPressdotorg\Plugin_Directory\Template;

if ( ! function_exists( 'wporg_plugins_posted_on' ) ) :
/**
 * Prints HTML with meta information for the current post-date/time and author.
 */
function wporg_plugins_posted_on() {
	$time_string = '<time class="entry-date published updated" datetime="%1$s">%2$s</time>';
	if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) {
		$time_string = '<time class="entry-date published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
	}

	$time_string = sprintf( $time_string,
		esc_attr( get_the_date( 'c' ) ),
		esc_html( get_the_date() ),
		esc_attr( get_the_modified_date( 'c' ) ),
		esc_html( get_the_modified_date() )
	);

	$posted_on = sprintf(
		esc_html_x( 'Posted on %s', 'post date', 'wporg-plugins' ),
		'<a href="' . esc_url( get_permalink() ) . '" rel="bookmark">' . $time_string . '</a>'
	);

	$byline = sprintf(
		esc_html_x( 'by %s', 'post author', 'wporg-plugins' ),
		'<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a></span>'
	);

	echo '<span class="posted-on">' . $posted_on . '</span><span class="byline"> ' . $byline . '</span>'; // WPCS: XSS OK.

}
endif;

if ( ! function_exists( 'wporg_plugins_entry_footer' ) ) :
/**
 * Prints HTML with meta information for the categories, tags and comments.
 */
function wporg_plugins_entry_footer() {
	// Hide category and tag text for pages.
	if ( 'post' === get_post_type() ) {
		/* translators: used between list items, there is a space after the comma */
		$categories_list = get_the_category_list( esc_html__( ', ', 'wporg-plugins' ) );
		if ( $categories_list && wporg_plugins_categorized_blog() ) {
			printf( '<span class="cat-links">' . esc_html__( 'Posted in %1$s', 'wporg-plugins' ) . '</span>', $categories_list ); // WPCS: XSS OK.
		}

		/* translators: used between list items, there is a space after the comma */
		$tags_list = get_the_tag_list( '', esc_html__( ', ', 'wporg-plugins' ) );
		if ( $tags_list ) {
			printf( '<span class="tags-links">' . esc_html__( 'Tagged %1$s', 'wporg-plugins' ) . '</span>', $tags_list ); // WPCS: XSS OK.
		}
	}

	if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
		echo '<span class="comments-link">';
		/* translators: %s: post title */
		comments_popup_link( sprintf( wp_kses( __( 'Leave a Comment<span class="screen-reader-text"> on %s</span>', 'wporg-plugins' ), array( 'span' => array( 'class' => array() ) ) ), get_the_title() ) );
		echo '</span>';
	}

	edit_post_link(
		sprintf(
			/* translators: %s: Name of current post */
			esc_html__( 'Edit %s', 'wporg-plugins' ),
			the_title( '<span class="screen-reader-text">"', '"</span>', false )
		),
		'<span class="edit-link">',
		'</span>'
	);
}
endif;

/**
 * Returns true if a blog has more than 1 category.
 *
 * @return bool
 */
function wporg_plugins_categorized_blog() {
	if ( false === ( $all_the_cool_cats = get_transient( 'wporg_plugins_categories' ) ) ) {
		// Create an array of all the categories that are attached to posts.
		$all_the_cool_cats = get_categories( array(
			'fields'     => 'ids',
			'hide_empty' => 1,
			// We only need to know if there is more than one category.
			'number'     => 2,
		) );

		// Count the number of categories that are attached to the posts.
		$all_the_cool_cats = count( $all_the_cool_cats );

		set_transient( 'wporg_plugins_categories', $all_the_cool_cats );
	}

	if ( $all_the_cool_cats > 1 ) {
		// This blog has more than 1 category so wporg_plugins_categorized_blog should return true.
		return true;
	} else {
		// This blog has only 1 category so wporg_plugins_categorized_blog should return false.
		return false;
	}
}

/**
 * Flush out the transients used in wporg_plugins_categorized_blog.
 */
function wporg_plugins_category_transient_flusher() {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	// Like, beat it. Dig?
	delete_transient( 'wporg_plugins_categories' );
}
add_action( 'edit_category', __NAMESPACE__ . '\wporg_plugins_category_transient_flusher' );
add_action( 'save_post',     __NAMESPACE__ . '\wporg_plugins_category_transient_flusher' );


// Returns an absolute url to the current url, no matter what that actually is.
function wporg_plugins_self_link() {
	$site_path = preg_replace( '!^' . preg_quote( parse_url( home_url(), PHP_URL_PATH ), '!' ) . '!', '', $_SERVER['REQUEST_URI'] );
	return home_url( $site_path );
}

function wporg_plugins_template_last_updated() {
	return '<span title="' . get_the_time('Y-m-d') . '">' . sprintf( _x( '%s ago', 'wporg-plugins' ), human_time_diff( get_the_time( 'U' ), current_time( 'timestamp' ) ) ) . '</span>';
}

function wporg_plugins_template_compatible_up_to() {
	$tested = get_post_meta( get_the_id(), 'tested', true ) ;
	if ( ! $tested ) {
		$tested = _x( 'unknown', 'unknown version', 'wporg-plugins' );
	}
	return esc_html( $tested );
}

function wporg_plugins_template_requires() {
	return esc_html( get_post_meta( get_the_id(), 'requires', true ) );
}

function wporg_plugins_the_version() {
	return esc_html( get_post_meta( get_the_id(), 'version', true ) );
}

function wporg_plugins_download_link() {
	return esc_url( Template::download_link( get_the_id() ) );
}

function wporg_plugins_template_authors() {
	$contributors = get_post_meta( get_the_id(), 'contributors', true );

	$authors = array();
	foreach ( $contributors as $contributor ) {
		$user = get_user_by( 'login', $contributor );
		if ( $user ) {
			$authors[] = $user;
		}
	}

	if ( ! $authors ) {
		$authors[] = new \WP_User( get_post()->post_author );
	}

	$author_links = array();
	$and_more = false;
	foreach ( $authors as $user ) {
		$author_links[] = sprintf( '<a href="%s">%s</a>', 'https://profiles.wordpress.org/' . $user->user_nicename . '/', $user->display_name );
		if ( count( $author_links ) > 5 ) {
			$and_more = true;
			break;
		}
	}

	if ( $and_more ) {
		return sprintf( '<cite> By: %s, and others.</cite>', implode(', ', $author_links ) );
	} else {
		return sprintf( '<cite> By: %s</cite>', implode(', ', $author_links ) );
	}
}
