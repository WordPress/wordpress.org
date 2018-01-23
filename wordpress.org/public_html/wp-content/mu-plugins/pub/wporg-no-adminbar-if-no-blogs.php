<?php
/**
 * Plugin Name: No Adminbar If No Blogs
 * Description: Allow the admin bar to be displayed only if the current user has a blog in this site.
 * Version:     1.0
 * Author:      WordPress.org, Peter Westwood
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * @package WordPressdotorg\NoBlogsNoAdminbar
 */

namespace WordPressdotorg\NoBlogsNoAdminbar;

/**
 * Filter to determine if adminbar is shown.
 *
 * Allow the admin bar to be displayed only if the current user has a blog in this site.
 * If not we hide it - so if you have no blogs on WP.org you get no bar even if you have make. or learn. blogs.
 *
 * @param bool $show Whether to show the admin bar.
 * @return bool
 */
function show_admin_bar( $show ) {
	$blogs = (array) get_blogs_of_user( get_current_user_id() );

	foreach ( $blogs as $blog ) {
		if ( get_current_site()->id === $blog->site_id ) {
			return $show;
		}
	}

	return false;
}
add_filter( 'show_admin_bar', __NAMESPACE__ . '\show_admin_bar', 1000 );
