<?php
/**
 * Plugin Name: o2 cross-Posting Access
 * Description: Allows any cross-posting to any other site in the network (even if you don't have access to the site).
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 */

namespace WordPressdotorg\o2\Cross_Posting_Access;

class Plugin {

	/*
	 * Keep track of the current site so we can add caps for not-this-site only.
	 */
	protected $_current_blog_id = 0;

	/**
	 * Initializes actions and filters.
	 */
	public function init() {
		if ( ! defined( 'O2__PLUGIN_LOADED' ) ) {
			return;
		}

		// If the current user can't edit posts, they're not going to need this.
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		$this->_current_blog_id = get_current_blog_id();

		// Dynamically allow edit_posts when needed.
		add_filter( 'user_has_cap', [ $this, 'user_has_cap' ], 10, 4 );

		// Include all current network sites in the suggestions.
		add_filter( 'o2_xposts_site_list', [ $this, 'add_network_blogs_to_o2_site_list'] );
	}

	/**
	 * Include all current network sites in the blog suggestions for cross-posting.
	 */
	public function add_network_blogs_to_o2_site_list( $site_suggestions ) {
		// Ignore anything that it detects automatically.
		$site_suggestions = array();

		$current_site = get_blog_details();
	
		$sites = get_sites( [
			'network_id' => $current_site->site_id,
			'public'     => $current_site->public,
			'orderby'    => ( is_subdomain_install() ? 'domain' : 'path' ),
		] );

		foreach ( $sites as $site ) {
			$o2_settings = get_blog_option( $site->ID, 'o2_options' );
			if ( ! $o2_settings || empty( $o2_settings['o2_enabled'] ) ) {
				continue;
			}

			$site_suggestions[ $site->blog_id ] = array(
				'blog_id'   => $site->blog_id,
				'title'     => $site->blogname,
				'siteurl'   => $site->siteurl,
				'subdomain' => $site->domain . $site->path,
				'blavatar'  => ''
			);
		}

		return $site_suggestions;
	}

	/**
	 * Adds edit_posts capabilities to current user dynamically when required.
	 *
	 * @param array   $allcaps An array of all the user's capabilities.
	 * @param array   $caps    Actual capabilities for meta capability.
	 * @param array   $args    Optional parameters passed to has_cap(), typically object ID.
	 * @param WP_User $user    The user object.
	 * @return array Array of all the user's capabilities.
	 */
	public function user_has_cap( $allcaps, $caps, $args, $user ) {
		if ( $user->ID !== get_current_user_id() ) {
			return $allcaps;
		}

		if ( ! empty( $allcaps[ 'edit_posts' ] ) ) {
			return $allcaps;
		}

		$required_on_this_request = false;

		// This is the /?get-xpost-data request.
		if ( isset( $_GET['get-xpost-data'] ) && doing_action( 'init' ) && ! $_POST ) {
			$required_on_this_request = true;
		}

		// During the filters that process xposts/xcomments
		if (
			doing_action( 'transition_post_status' ) ||
			doing_action( 'wp_insert_comment' ) ||
			doing_action( 'transition_comment_status' )
		) {
			if ( $this->_current_blog_id !== get_current_blog_id() ) {
				$required_on_this_request = true;
			}
		}

		if ( $required_on_this_request ) {
			$allcaps['edit_posts'] = true;
		}

		return $allcaps;
	}
}

add_action( 'init', [ new Plugin(), 'init' ], 9 );
