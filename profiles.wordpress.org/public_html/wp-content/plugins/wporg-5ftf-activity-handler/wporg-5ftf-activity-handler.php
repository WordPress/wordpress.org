<?php
/*
Plugin Name: WordPress.org 5ftf Activity Handler
Plugin URI: http://wordpress.org
License: GPL2
Version: 1.1
Description: Handles saving of last 5ftf contribution.
*/

defined( 'ABSPATH' ) or die();

if ( class_exists( 'WPOrg_5ftf_Activity_Handler' ) ) {
	return;
}

class WPOrg_5ftf_Activity_Handler {

	/**
	 * @var WPOrg_5ftf_Activity_Handler The singleton instance.
	 */
	private static $instance;

	/**
	 *  Name of meta key that tracks last contribution as a unix timestamp.
	 *
	 * @var string
	 */
	const last_contribution_meta_key = 'wporg_5ftf_last_contribution';

	/**
	 *  Name of meta key that stores information about the last_contribution_meta_key.
	 *
	 * @var string
	 */
	const last_contribution_info_meta_key = 'last_contribution_info_meta_key';

	/**
	 * Returns always the same instance of this plugin.
	 *
	 * @return WPOrg_5ftf_Activity_Handler
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {

		if ( 'profiles.wordpress.org' === site_url() ) {
			add_filter( 'bp_activity_add', array( $this, 'handle_activity' ) );
		} elseif ( defined( 'IS_WPORG_MULTINETWORK' ) && IS_WPORG_MULTINETWORK ) {
			add_filter( 'wporg_github_added_activity', array( $this, 'handle_github_activity' ) );
		}
	}

	/**
	 * Saves meta value if it qualifies as a contribution.
	 */
	public function handle_activity( $args ) {

		if ( empty( $args['user_id'] ) ) {
			return '-1 WordPress.org user id is missing';
		}

		if ( ! isset( $args['type'] ) ) {
			return '-1 Activity type is missing';
		}

		if ( ! self::is_5ftf_contribution( $args['type'] ) ) {
			return '-1 Activity is not considered a contribution';
		}

		return self::update_last_contribution_meta( $args['user_id'], $args );
	}

	/**
	 * Saves meta value if it qualifies as a github contribution.
	 *
	 * $args.category string Type of github event
	 * $args.repo string Name of the public repository
	 * $args.user_id string|null Name of the public repository
	 *
	 * @return int|bool
	 */
	public function handle_github_activity( $args ) {
		$valid_actions = array( 'pr_opened', 'pr_merged', 'issue_opened', 'issue_closed' );

		if ( empty( $args['user_id'] ) ) {
			// user_id may be null if the user didn't connect their github account to their wordpress.org account
			return '-1 WordPress.org user id is missing';
		}

		if ( ! in_array( $args['category'], $valid_actions, true ) ) {
			return '-1 Category: ' . sanitize_text_field( $args['category'] ) . ' is not a contribution.';
		}

		return self::update_last_contribution_meta( $args['user_id'], $args );
	}

	/**
	 * Returns whether action is considered a contribution.
	 *
	 * @return boolean True if action == contribution
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
	protected function update_last_contribution_meta( $user_id, $args ) {

		// Save information about what updates the value
		update_user_meta( $user_id, self::last_contribution_info_meta_key, json_encode( $args ) );

		return update_user_meta( $user_id, self::last_contribution_meta_key, time() );
	}

} /* /class WPOrg_5ftf_Activity_Handler */

if ( class_exists( 'WPOrg_5ftf_Activity_Handler' ) ) {
	WPOrg_5ftf_Activity_Handler::get_instance();
}
