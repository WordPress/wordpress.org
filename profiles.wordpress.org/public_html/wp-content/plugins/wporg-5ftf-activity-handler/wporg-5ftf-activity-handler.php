<?php
/*
Plugin Name: WordPress.org 5ftf Activity Handler
Plugin URI: http://wordpress.org
License: GPL2
Version: 1.1
Description: Handles saving of last 5ftf 
*/

/*  Copyright 2013

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
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
			add_filter( 'bp_activity_add', array( $this, 'handle_contribution' ) );
		}

		/**
		 * Saves contribution if it qualifies as a contribution.
		 * 
		 */
		public function handle_contribution( $args ) {
			if( self::is_5ftf_contribution( $args['type'] ) ) {
				self::update_last_contribution_meta( $args['user_id'] );
			}
		}

		/**
		 * Returns whether action is considered a contribution.
		 */
		public function is_5ftf_contribution( $action ) {
			$valid_actions = array( 'forum_topic_create' );

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
