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
use o2_Xposts;

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

		$this->_current_blog_id = get_current_blog_id();

		// Dynamically allow edit_posts when needed.
		add_filter( 'user_has_cap', [ $this, 'user_has_cap' ], 10, 4 );

		// Include all current network sites in the suggestions.
		add_filter( 'o2_xposts_site_list', [ $this, 'add_network_blogs_to_o2_site_list'] );

		// List all sites in the auto-complete.
		add_action( 'init', [ $this, 'override_o2_xposts_get_data' ], 9 ); // before o2_Xposts::get_data();

		// Filter new posts & comments to fix/expand short xposts.
		add_filter( 'preprocess_comment', [ $this, 'preprocess_comment' ] );
		add_filter( 'o2_create_post', [ $this, 'o2_create_post' ] );
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
				// Name is used for the search index. This allows searching by each component, such as the path.
				'name'      => $site->domain . ' ' . trim( $site->path, '/' ) . ' ' . $site->blogname,
				'blavatar'  => ''
			);
		}

		return $site_suggestions;
	}

	public function override_o2_xposts_get_data() {
		$xposts = new o2_Xposts();
		if (
			! isset( $_GET['get-xpost-data'] ) ||
			! is_user_logged_in() ||
			! $xposts->should_process_terms()
		) {
			return;
		}

		$data = [
			'data'  => array_values( $xposts->site_suggestions() ),
			'limit' => 50, // Show all sites
		];

		wp_send_json_success( json_encode( $data ) );
		die();
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
		if ( ! is_user_logged_in() ) {
			return $allcaps;
		}

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


	/**
	 * Filter newly created comments to correct any mis-typed crossposts.
	 */
	public function preprocess_comment( $comment_data ) {
		$comment_data['comment_content'] = $this->correct_xposts( $comment_data['comment_content'] );

		return $comment_data;
	}

	/**
	 * Filter newly created posts to correct any mis-typed crossposts.
	 */
	public function o2_create_post( $post ) {
		$post->post_content = addslashes( $this->correct_xposts( stripslashes( $post->post_content ) ) );

		return $post;
	}

	/**
	 * Corrects xposts from either +site or +domain/site to +domain/site/ as required on make.wordpress.org.
	 */
	protected function correct_xposts( $string ) {
		$current_site = get_blog_details();

		return preg_replace_callback(
			o2_Xposts::XPOSTS_REGEX,
			function( $m ) use( $current_site ) {
				$site = $m[1];
				$site = str_replace( $current_site->domain, '', $site );
				$site = trim( $site, '/' );

				$sites = get_sites( [
					'fields'     => 'ids',
					'network_id' => $current_site->site_id,
					'domain'     => $current_site->domain,
					'path'       => "/{$site}/",
				] );

				// If site could not be found, or there's multiple, let o2 deal with it.
				if ( count( $sites ) !== 1 ) {
					return $m[0];
				}

				$correct = "{$current_site->domain}/{$site}/";
				if ( $m[1] !== $correct ) {
					return str_replace( $m[1], $correct, $m[0] );
				}

				return $m[0];
			},
			$string
		);
	}

}

add_action( 'init', [ new Plugin(), 'init' ], 5 );
