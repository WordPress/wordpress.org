<?php
/**
 * Code Reference redirects.
 *
 * @package wporg-developer
 */

/**
 * Class to handle redirects.
 */
class DevHub_Redirects {

	/**
	 * Initializer
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Handles adding/removing hooks to perform redirects as needed.
	 */
	public static function do_init() {
		add_action( 'template_redirect', array( __CLASS__, 'redirect_single_search_match' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_handbook' ) );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_resources' ) );
	}

	/**
	 * Redirects a search query with only one result directly to that result.
	 */
	public static function redirect_single_search_match() {
		if ( is_search() && 1 == $GLOBALS['wp_query']->found_posts ) {
			wp_redirect( get_permalink( get_post() ) );
			exit();
		}
	}

	/**
	 * Redirects a naked handbook request to home.
	 */
	public static function redirect_handbook() {
		if (
			// Naked /handbook/ request
			( 'handbook' == get_query_var( 'name' ) && ! get_query_var( 'post_type' ) ) ||
			// Temporary: Disable access to handbooks unless a member of the site
			( ! is_user_member_of_blog() && is_post_type_archive( array( 'plugin-handbook', 'theme-handbook' ) ) )
		) {
			wp_redirect( home_url() );
			exit();
		}
	}

	/**
	 * Redirects a naked /resources/ request to dashicons page.
	 *
	 * Temporary until a resource page other than dashicons is created.
	 */
	public static function redirect_resources() {
		if ( is_page( 'resources' ) ) {
			wp_redirect( get_permalink( get_page_by_title( 'dashicons' ) ) );
			exit();
		}
	}

} // DevHub_Redirects

DevHub_Redirects::init();
