<?php
/*
Plugin Name: WordPress.org 5ftf Activity Handler
Plugin URI: http://wordpress.org
License: GPL2
Version: 1.1
Description: Handles saving of last 5ftf contribution.
*/

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'WPOrg_5ftf_Activity_Handler' ) ) {

	class WPOrg_5ftf_Activity_Handler {

		/**
		 *  Name of meta key that tracks last contribution as a unix timestamp.
		 *
		 * @var string
		 */
		const last_contribution_meta_key = 'wporg_5ftf_last_contribution';

		/**
		 * Constructor.
		 */
		public function __construct() {
			if ( 'profiles.wordpress.org' === site_url() ) {
				add_filter( 'bp_activity_add', array( $this, 'handle_wordpress_activity' ) );
			} elseif ( str_ends_with( 'wordpress.org', site_url() ) ) {
				add_filter( 'wporg_github_added_activity', array( $this, 'handle_github_activity' ) );
			}
		}

		/**
		 * Saves meta value if it qualifies as a contribution.
		 */
		public function handle_wordpress_activity( $args ) {
			if ( self::is_5ftf_contribution( $args['type'] ) ) {
				self::update_last_contribution_meta( $args['user_id'] );
			}
		}

		/**
		 * Saves meta value if it qualifies as a github contribution.
		 */
		public function handle_github_activity( $args ) {
			/**
			* $args.category string Type of github event
			* $args.repo string Name of the public repository
			* $args.user_id string|null Name of the public repository
			*/

			$valid_actions = array( 'pr_opened', 'pr_merged', 'issue_opened' );

			if ( in_array( $args['category'], $valid_actions, true ) ) {
				// user_id may be null if the user didn't connect their github account to their wordpress.org account
				if ( $args['user_id'] ) {
					update_last_contribution_meta( $args['user_id'] );
				}
			}
		}

		/**
		 * Returns whether action is considered a contribution.
		 */
		public function is_5ftf_contribution( $action ) {
			$wordpress_actions = array( 'blog_post_create' );
			$wordcamp_actions  = array( 'wordcamp_speaker_add', 'wordcamp_organizer_add' );

			$valid_actions = array_merge( $wordpress_actions, $wordcamp_actions );

			return in_array( $action, $valid_actions, true );
		}

		/**
		 * Updates meta value to current timestamp indicating the user's last contribution.
		 *
		 * @return int|bool result of update_user_meta();
		 */
		protected function update_last_contribution_meta( $user_id ) {
			return update_user_meta( $user_id, self::last_contribution_meta_key, time() );
		}

	} /* /class WPOrg_5ftf_Activity_Handler */
} /* if class_exists */

if ( class_exists( 'WPOrg_5ftf_Activity_Handler' ) ) {
	new WPOrg_5ftf_Activity_Handler();
}
