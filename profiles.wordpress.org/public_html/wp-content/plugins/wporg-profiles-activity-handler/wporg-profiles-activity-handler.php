<?php
/*
Plugin Name: WordPress.org Profiles Activity Handler
Plugin URI: http://wordpress.org
Author: Mert Yazicioglu, Scott Reilly
Author URI: http://www.mertyazicioglu.com
License: GPL2
Version: 1.1
Description: Handles the activites sent from other services in the .org ecosystem (bbPress, WP, Trac).
*/

/*  Copyright 2013  Mert Yazicioglu  (email : mert@mertyazicioglu.com)

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

if ( ! class_exists( 'WPOrg_Profiles_Activity_Handler' ) ) {

	class WPOrg_Profiles_Activity_Handler {

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_filter( 'bp_activity_global_tables', array( $this, 'change_table_names' ) );
			add_filter( 'bp_active_components',      array( $this, 'activate_activity_component' ) );
			add_action( 'plugins_loaded',            array( $this, 'plugins_loaded' ) );
		}

		/**
		 * Actions to run on the 'plugins_loaded' filter.
		 */
		public function plugins_loaded() {
			add_action( 'wp_ajax_nopriv_wporg_handle_activity', array( $this, 'handle_activity' ) );

			// Disable default BP activity features
			add_filter( 'bp_activity_can_comment',              '__return_false' );
			add_filter( 'bp_activity_do_mentions',              '__return_false' );
		}

		/**
		 * Changes the table names to use custom wporg activity tables rather
		 * than the ones for BuddyPress.org.
		 *
		 * @param  array $tables The default tables.
		 * @return array
		 */
		public function change_table_names( $tables ) {
			global $bp;

			return array(
				'table_name'      => $bp->table_prefix . 'wporg_activity',
				'table_name_meta' => $bp->table_prefix . 'wporg_activity_meta',
			);
		}

		/**
		 * Ensures that the activity component is activated.
		 *
		 * @param  array $activated Array of activated components.
		 * @return array
		 */
		public function activate_activity_component( $activated ) {
			if ( ! isset( $activated['activity'] ) || '1' != $activated['activity'] ) {
				$activated['activity'] = '1';
			}

			return $activated;
		}

		/**
		 * Primary AJAX handler.
		 *
		 * Funnels incoming requests to appropriate sub-handler based on
		 * $_POST['source'] value.
		 *
		 * By default (and for security), this does nothing. The filter
		 * 'wporg_is_valid_activity_request' must be hooked in order to provide
		 * the appropriate validity checks on the request to permit the incoming
		 * activity notification to be handled.
		 *
		 * TODO: Make this a generic handler and require sub-handlers to
		 * register themselves.
		 */
		public function handle_activity() {
			// Return error if not a valid activity request.
			if ( true !== apply_filters( 'wporg_is_valid_activity_request', false ) ) {
				die( '-1 Not a valid activity request.' );
			}

			// Return error if activities are not enabled.
			if ( ! bp_is_active( 'activity' ) ) {
				die( '-1 Activity component not activated.' );
			}

			$source = $_POST['source'];

			switch ( $source ) {
				case 'forum':
					$activity_id = $this->handle_forum_activity();
					break;
				case 'plugin':
					$activity_id = $this->handle_plugin_activity();
					break;
				case 'theme':
					$activity_id = $this->handle_theme_activity();
					break;
				case 'trac':
					$activity_id = $this->handle_trac_activity();
					break;
				case 'wordcamp':
					$activity_id = $this->handle_wordcamp_activity();
					break;
				case 'wordpress':
					$activity_id = $this->handle_wordpress_activity();
					break;
				default:
					$activity_id = '-1 Unrecognized activity source.';
					break;
			}

			if ( false === $activity_id ) {
				$error = '-1 Unable to save activity.';
			}

			$success = intval( $activity_id ) > 0 ? '1' : $error;
			die( $success );
		}

		/**
		 * Handles incoming activities for a forum.
		 *
		 * Recognized activities:
		 *  - Creating new topic
		 *  - Replying to a topic
		 */
		private function handle_forum_activity() {
			$user = get_user_by( 'login', $_POST['user'] );

			if ( ! $user ) {
				return "-1 Activity reported for unrecognized user ({$_POST['user']}).";
			}

			if ( '1' == $_POST['newTopic'] ) {
				$action = sprintf(
					__( 'Created a topic, <a href="%s">%s</a>, on the site <a href="%s">%s</a>', 'wporg' ),
					esc_url( $_POST['url'] ),
					$_POST['title'],
					esc_url( $_POST['site_url'] ),
					$_POST['site']
				);
				$type = 'forum_topic_create';
			} else {
				$action = sprintf(
					__( 'Posted a <a href="%s">reply</a> to %s, on the site <a href="%s">%s</a>', 'wporg' ),
					esc_url( $_POST['url'] ),
					$_POST['title'],
					esc_url( $_POST['site_url'] ),
					$_POST['site']
				);
				$type = 'forum_reply_create';
			}

			$args = array(
				'user_id'           => $user->ID,
				'action'            => $action,
				'content'           => $_POST['message'],
				'primary_link'      => $_POST['url'],
				'component'         => 'forums',
				'type'              => $type,
				'item_id'           => 'forum_topic_create' ? intval( $_POST['topic_id'] ) : intval( $_POST['post_id'] ),
				'secondary_item_id' => intval( $_POST['forum_id'] ),
				'hide_sitewide'     => false,
			);

			return bp_activity_add( $args );
		}

		/**
		 * Handles incoming activities for the Plugins Directory.
		 *
		 * Recognized activities:
		 *  - Creating new plugin
		 */
		private function handle_plugin_activity() {
			$user = get_user_by( 'login', $_POST['user'] );

			if ( ! $user ) {
				return "-1 Activity reported for unrecognized user ({$_POST['user']}).";
			}

			$args = array(
				'user_id'           => $user->ID,
				'action'            => sprintf(
					__( 'Released a new plugin, <a href="%s">%s</a>', 'wporg' ),
					esc_url( $_POST['url'] ),
					$_POST['title']
				),
				'content'           => '',
				'primary_link'      => $_POST['url'],
				'component'         => 'plugins',
				'type'              => 'plugin_create',
				'item_id'           => intval( $_POST['plugin_id'] ),
				'secondary_item_id' => false,
				'hide_sitewide'     => false,
			);

			return bp_activity_add( $args );
		}

		/**
		 * Handles incoming activities for the Themes Directory.
		 *
		 * Recognized activities:
		 *  - Creating new theme
		 */
		private function handle_theme_activity() {
			$user = get_user_by( 'login', $_POST['user'] );

			if ( ! $user ) {
				return "-1 Activity reported for unrecognized user ({$_POST['user']}).";
			}

			$args = array(
				'user_id'           => $user->ID,
				'action'            => sprintf(
					__( 'Released a new theme, <a href="%s">%s</a>', 'wporg' ),
					esc_url( $_POST['url'] ),
					$_POST['title']
				),
				'content'           => '',
				'primary_link'      => $_POST['url'],
				'component'         => 'themes',
				'type'              => 'theme_create',
				'item_id'           => intval( $_POST['theme_id'] ),
				'secondary_item_id' => false,
				'hide_sitewide'     => false,
			);

			return bp_activity_add( $args );
		}

		/**
		 * Handles incoming activities for a Trac install.
		 *
		 * Recognized activities:
		 *  - Creating new ticket
		 *  - Commenting on a ticket
		 *  - Making a commit
		 *  - Receiving props on a commit
		 */
		private function handle_trac_activity() {
			$args = array();

			$user = get_user_by( 'login', $_POST['user'] );

			if ( ! $user ) {
				return "-1 Activity reported for unrecognized user ({$_POST['user']}).";
			}

			if ( ! empty( $_POST['description'] ) ) {

				$args = array(
					'user_id'           => $user->ID,
					'action'            => sprintf( __( 'Created a new ticket in %s Trac', 'wporg' ), $_POST['trac'] ),
					'content'           => $_POST['title'],
					'component'         => 'tracs',
					'type'              => 'trac_ticket_create',
					'item_id'           => intval( $_POST['id'] ),
					'secondary_item_id' => false,
					'hide_sitewide'     => false,
				);

				return bp_activity_add( $args );

			} elseif ( ! empty( $_POST['comment'] ) ) {

				$args = array(
					'user_id'           => $user->ID,
					'action'            => sprintf( __( 'Posted a reply to %s in %s Trac', 'wporg' ), $_POST['title'], $_POST['trac'] ),
					'content'           => $_POST['comment'],
					'component'         => 'tracs',
					'type'              => 'trac_comment_create',
					'item_id'           => intval( $_POST['id'] ),
					'secondary_item_id' => false,
					'hide_sitewide'     => false,
				);

				return bp_activity_add( $args );

			} else {

				// Record commit to committer's activity stream
				$args = array(
					'user_id'           => $user->ID,
					'action'            => sprintf( __( 'Committed [%s] to %s Trac', 'wporg' ), $_POST['changeset'], $_POST['trac'] ),
					'content'           => $_POST['message'],
					'component'         => 'tracs',
					'type'              => 'trac_commit_create',
					'item_id'           => intval( $_POST['changeset'] ),
					'secondary_item_id' => false,
					'hide_sitewide'     => false,
				);

				$activity_id = bp_activity_add( $args );

				// Record props for each listed user
				$regex = '/props\s+((?:(?:\w+\b(?<!\bfixes))(?:[,][ ]*)?)+)/i';
				preg_match_all( $regex, $_POST['message'], $matches );
				$usernames = explode( ',', $matches[1][0] );
				$usernames = array_map( 'trim', $usernames );
				$usernames = array_filter( $usernames );
				foreach ( $usernames as $username ) {
					$user = get_user_by( 'login', $username );
					if ( empty( $user ) ) continue;
					$args = array(
						'user_id'           => $user->ID,
						'action'            => sprintf( __( 'Received props in %s', 'wporg' ), $_POST['trac'] ),
						'content'           => $_POST['message'],
						'component'         => 'tracs',
						'type'              => 'trac_props_mention',
						'item_id'           => intval( $_POST['changeset'] ),
						'secondary_item_id' => false,
						'hide_sitewide'     => false,
					);
					bp_activity_add( $args );
				}

				return $activity_id;
			}
		}

		/**
		 * Handles incoming activities for WordCamp.
		 */
		private function handle_wordcamp_activity() {
			$user = get_user_by( 'id', $_POST['user_id'] );
			$type = '';

			if ( ! $user ) {
				return "-1 Activity reported for unrecognized user ({$_POST['user_id']}).";
			}

			if ( isset( $_POST['speaker_id'] ) && ! empty( $_POST['speaker_id'] ) ) {
				$type    = 'wordcamp_speaker_add';
				$item_id = $_POST['speaker_id'];

				if ( isset( $_POST['wordcamp_date'] ) && ! empty( $_POST['wordcamp_date'] ) ) {
					$action = sprintf(
						__( 'Confirmed as a speaker for <a href="%s">%s</a> coming up on %s', 'wporg' ),
						esc_url( $_POST['url'] ),
						$_POST['wordcamp_name'],
						$_POST['wordcamp_date']
					);
				} else {
					$action = sprintf(
						__( 'Confirmed as a speaker for <a href="%s">%s</a>', 'wporg' ),
						esc_url( $_POST['url'] ),
						$_POST['wordcamp_name']
					);
				}
			} elseif ( isset( $_POST['organizer_id'] ) && ! empty( $_POST['organizer_id'] ) ) {
				$type    = 'wordcamp_organizer_add';
				$item_id = $_POST['organizer_id'];

				if ( isset( $_POST['wordcamp_date'] ) && ! empty( $_POST['wordcamp_date'] ) ) {
					$action = sprintf(
						__( 'Joined the organizing team for <a href="%s">%s</a> coming up %s', 'wporg' ),
						esc_url( $_POST['url'] ),
						$_POST['wordcamp_name'],
						$_POST['wordcamp_date']
					);
				} else {
					$action = sprintf(
						__( 'Joined the organizing team for <a href="%s">%s</a>', 'wporg' ),
						esc_url( $_POST['url'] ),
						$_POST['wordcamp_name']
					);
				}
			}

			if ( empty( $type ) ) {
				return "-1 Unrecognized WordCamp activity.";
			}

			$args = array(
				'user_id'           => $user->ID,
				'action'            => $action,
				'content'           => '',
				'primary_link'      => $_POST['url'],
				'component'         => 'wordcamp',
				'type'              => $type,
				'item_id'           => intval( $item_id ),
				'secondary_item_id' => intval( $_POST['wordcamp_id'] ),
				'hide_sitewide'     => false
			);

			$ret = bp_activity_add( $args );
			if ( ! $ret ) {
				$ret = '-1 Unable to save activity: ' . json_encode( $args );
			}
			return $ret;
		}

		/**
		 * Handles incoming activities for a WordPress install.
		 */
		private function handle_wordpress_activity() {
			$user = get_user_by( 'login', $_POST['user'] );

			if ( ! $user ) {
				return "-1 Activity reported for unrecognized user ({$_POST['user']}).";
			}

			if ( isset( $_POST['comment_id'] ) && ! empty( $_POST['comment_id'] ) ) {
				$type    = 'blog_comment_create';
				$item_id = $_POST['comment_id'];
				$action  = sprintf(
					__( 'Wrote a <a href="%s">comment</a> on the post %s, on the site <a href="%s">%s</a>', 'wporg' ),
					esc_url( $_POST['url'] ),
					$_POST['title'],
					esc_url( $_POST['blog_url'] ),
					$_POST['blog']
				);
			} else {
				$type    = 'blog_post_create';
				$item_id = $_POST['post_id'];
				$action  = sprintf(
					__( 'Wrote a new post, <a href="%s">%s</a>, on the site <a href="%s">%s</a>', 'wporg' ),
					esc_url( $_POST['url'] ),
					$_POST['title'],
					esc_url( $_POST['blog_url'] ),
					$_POST['blog']
				);
			}

			$args = array(
				'user_id'           => $user->ID,
				'action'            => $action,
				'content'           => $_POST['content'],
				'primary_link'      => $_POST['url'],
				'component'         => 'blogs',
				'type'              => $type,
				'item_id'           => intval( $item_id ),
				'secondary_item_id' => false,
				'hide_sitewide'     => false
			);

			return bp_activity_add( $args );
		}

	} /* /class WPOrg_Profiles_Activity_Handler */
} /* if class_exists */

if ( class_exists( 'WPOrg_Profiles_Activity_Handler' ) ) {
	new WPOrg_Profiles_Activity_Handler();
}
