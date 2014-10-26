<?php
/**
 * Custom functions that act independently of the theme templates
 *
 * Eventually, some of the functionality here could be replaced by core features
 *
 * @package wporg-developer
 */

/**
 * Get our wp_nav_menu() fallback, wp_page_menu(), to show a home link.
 *
 * @param array $args Configuration arguments.
 * @return array
 */
function wporg_developer_page_menu_args( $args ) {
	$args['show_home'] = true;
	return $args;
}
add_filter( 'wp_page_menu_args', 'wporg_developer_page_menu_args' );

/**
 * Adds custom classes to the array of body classes.
 *
 * @param array $classes Classes for the body element.
 * @return array
 */
function wporg_developer_body_classes( $classes ) {
	// Adds a class of group-blog to blogs with more than 1 published author.
	if ( is_multi_author() ) {
		$classes[] = 'group-blog';
	}

	return $classes;
}
add_filter( 'body_class', 'wporg_developer_body_classes' );

/**
 * Filters wp_title to print a neat <title> tag based on what is being viewed.
 *
 * @param string $title Default title text for current view.
 * @param string $sep Optional separator.
 * @return string The filtered title.
 */
function wporg_developer_wp_title( $title, $sep ) {
	global $page, $paged;

	if ( is_feed() ) {
		return $title;
	}

	$post_type = get_query_var( 'post_type' );

	// Add post type to title if it's a parsed item.
	if ( is_singular() && 0 === strpos( $post_type, 'wp-parser-' ) ) {
		if ( $post_type_object = get_post_type_object( $post_type ) ) {
			$title .= get_post_type_object( $post_type )->labels->singular_name . " $sep ";
		}
	}
	// Add handbook name to title if relevent
	elseif ( is_singular() && false !== strpos( $post_type, 'handbook' ) ) {
		if ( $post_type_object = get_post_type_object( $post_type ) ) {
			$handbook_label = get_post_type_object( $post_type )->labels->name . " $sep ";
			$handbook_name  = \WPorg_Handbook::get_name( $post_type ) . " Handbook $sep ";

			// Replace title with handbook name if this is landing page for the handbook
			if ( $title == $handbook_label ) {
				$title = $handbook_name;
			// Otherwise, append the handbook name
			} else {
				$title .= $handbook_name;
			}
		}
	}

	// Add the blog description for the home/front page.
	$site_description = get_bloginfo( 'description', 'display' );
	if ( $site_description && ( is_home() || is_front_page() ) ) {
		$title .= " $sep $site_description";
	}

	// Add a page number if necessary:
	if ( $paged >= 2 || $page >= 2 ) {
		$title .= " $sep " . sprintf( __( 'Page %s', 'wporg' ), max( $paged, $page ) );
	}

	// Add the blog name
	$title .= get_bloginfo( 'name' );

	return $title;
}
add_filter( 'wp_title', 'wporg_developer_wp_title', 10, 2 );

/**
 * Prefixes excerpts for archive view with content type label.
 *
 * @param  string $excerpt The excerpt.
 * @return string
 */
function wporg_filter_archive_excerpt( $excerpt ) {
	if ( ! is_single() ) {
		$excerpt = '<b>' . get_post_type_object( get_post_type( get_the_ID() ) )->labels->singular_name . ': </b>' . $excerpt;
	}

	return $excerpt;
}
add_filter( 'get_the_excerpt', 'wporg_filter_archive_excerpt' );

/**
 * Appends parentheses to titles in archive view for functions and methods.
 *
 * @param  string      $title The title.
 * @param  int|WP_Post $post  Optional. The post ID or post object.
 * @return string
 */
function wporg_filter_archive_title( $title, $post = null ) {
	if ( ! is_admin() && $post && ( ! is_single() || doing_filter( 'single_post_title' ) ) && in_array( get_post_type( $post ), array( 'wp-parser-function', 'wp-parser-method' ) ) ) {
		$title .= '()';
	}

	return $title;
}
add_filter( 'the_title',         'wporg_filter_archive_title', 10, 2 );
add_filter( 'single_post_title', 'wporg_filter_archive_title', 10, 2 );
