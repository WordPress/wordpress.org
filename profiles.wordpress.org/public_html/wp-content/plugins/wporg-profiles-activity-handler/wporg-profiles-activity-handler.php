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
			add_filter( 'bp_activity_global_tables', array( $this, 'change_global_table_names' ) );
			add_filter( 'bp_activity_meta_tables',   array( $this, 'change_meta_table_names' ) );
			add_filter( 'bp_active_components',      array( $this, 'activate_activity_component' ) );
			add_action( 'plugins_loaded',            array( $this, 'plugins_loaded' ) );

			// Disable default BP activity features
			add_filter( 'bp_activity_can_comment',    '__return_false' );
			add_filter( 'bp_activity_do_mentions',    '__return_false' );
			add_filter( 'bp_activity_use_akismet',    '__return_false' );
		}

		/**
		 * Gets a user by either login, slug, or id.
		 *
		 * @param string|int     $username The user's login, slug, or id.
		 * @return WP_User|false WP_User object on success, false on failure.
		 */
		protected function get_user( $username ) {
			if ( is_numeric( $username ) && ( absint( $username ) == $username ) ) {
				$user = get_user_by( 'id', $username );
			} else {
				$user = get_user_by( 'login', $username );
			}

			if ( ! $user ) {
				$user = get_user_by( 'slug', strtolower( $username ) );
			}

			return $user;
		}

		/**
		 * Actions to run on the 'plugins_loaded' filter.
		 */
		public function plugins_loaded() {
			add_action( 'wp_ajax_nopriv_wporg_handle_activity', array( $this, 'handle_activity' ) );
		}

		/**
		 * Changes the table names to use custom wporg activity tables rather
		 * than the ones for BuddyPress.org.
		 *
		 * @param  array $tables The default tables.
		 * @return array
		 */
		public function change_global_table_names( $tables ) {
			global $bp;

			$tables['table_name']      = $bp->table_prefix . 'wporg_activity';
			$tables['table_name_meta'] = $bp->table_prefix . 'wporg_activity_meta';

			return $tables;
		}

		/**
		 * Changes the meta table name to use custom wporg activity meta tables rather
		 * than the ones for BuddyPress.org.
		 *
		 * @param  array $tables The default meta table.
		 * @return array
		 */
		public function change_meta_table_names( $tables ) {
			global $bp;

			$tables['activity'] = $bp->table_prefix . 'wporg_activity_meta';

			return $tables;
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
		 * Checks that required POST arguments are defined and have a value.
		 *
		 * Ends request and reports on any missing arguments, if necessary.
		 *
		 * @param array $args The array of required POST arguments.
		 */
		protected function require_args( $args ) {
			$missing = array();

			foreach ( $args as $arg ) {
				if ( empty( $_POST[ $arg ] ) ) {
					$missing[] = $arg;
				}
			}

			if ( $missing ) {
				die( '-1 Required argument(s) are missing: ' . implode( ', ', $missing ) );
			}
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
				die( '-1 Not a valid activity request' );
			}

			// Return error if activities are not enabled.
			if ( ! bp_is_active( 'activity' ) ) {
				die( '-1 Activity component not activated' );
			}

			if ( empty( $_POST['user'] ) ) {
				die( '-1 No user specified.' );
			}

			if ( empty( $_POST['source'] ) ) {
				die( '-1 No source specified.' );
			}

			$source = $_POST['source'];

			// Disable default BP moderation
			remove_action( 'bp_activity_before_save', 'bp_activity_check_moderation_keys', 2, 1 );
			remove_action( 'bp_activity_before_save', 'bp_activity_check_blacklist_keys',  2, 1 );

			// Disable requirement that user have a display_name set
			remove_filter( 'bp_activity_before_save', 'bporg_activity_requires_display_name' );

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
					$activity_id = '-1 Unrecognized activity source';
					break;
			}

			if ( false === $activity_id ) {
				$activity_id = '-1 Unable to save activity';
			}

			$success = intval( $activity_id ) > 0 ? '1' : $activity_id;
			die( $success );
		}

		/**
		 * Handles incoming activities for a forum.
		 *
		 * Recognized activities:
		 *  - Creating new topic
		 *  - Replying to a topic
		 *  - Removing a topic
		 *  - Removing a topic reply
		 */
		private function handle_forum_activity() {
			$user = $this->get_user( $_POST['user'] );
			$type = '';

			// Check for valid user.
			if ( ! $user ) {
				return '-1 Activity reported for unrecognized user : ' . sanitize_text_field( $_POST['user'] );
			}

			// Check for valid forum activities.
			$activities = array(
				'create-topic' => 'forum_topic_create',
				'remove-topic' => 'forum_topic_remove',
				'create-reply' => 'forum_reply_create',
				'remove-reply' => 'forum_reply_remove',
			);
			if ( ! empty( $_POST['activity'] ) && ! empty( $activities[ $_POST['activity'] ] ) ) {
				$type = $activities[ $_POST['activity'] ];
			} else {
				return '-1 Unrecognized forum activity.';
			}

			// Check for required args.
			$required_args = array( 'forum_id' );
			if ( in_array( $type, array( 'forum_topic_create', 'forum_reply_create' ) ) ) {
				$required_args[] = 'message';
				$required_args[] = 'url';
				$required_args = array_merge( $required_args, array( 'title', 'site', 'message', 'url' ) );
			}
			if ( in_array( $type, array( 'forum_topic_create', 'forum_topic_remove' ) ) ) {
				$required_args[] = 'topic_id';
			} else {
				$required_args[] = 'post_id';
			}
			$this->require_args( $required_args );

			// Determine 'item_id' value based on context.
			$item_id = in_array( $type, array( 'forum_topic_create', 'forum_topic_remove' ) ) ?
				intval( $_POST['topic_id'] ) :
				intval( $_POST['post_id'] );

			// Find an existing activity uniquely identified by the reported criteria.
			// For a creation, this prevents duplication and permits a previously
			// trashed/spammed/unapproved activity to be restored.
			// For a removal, this is used to find the activity to mark it as spam.
			// Note: It's unlikely, but possible, that this is a non-unique request.
			$args = array(
				'user_id'           => $user->ID,
				'component'         => 'forums',
				'type'              => str_replace( 'remove', 'create', $type ),
				'item_id'           => $item_id,
				'secondary_item_id' => intval( $_POST['forum_id'] ),
			);
			$activity_id = bp_activity_get_activity_id( $args );
			$activity_obj = $activity_id ? new BP_Activity_Activity( $activity_id ) : false;

			// Record the creation of a topic or reply.
			if ( in_array( $type, array( 'forum_topic_create', 'forum_reply_create' ) ) ) {
				if ( $activity_obj ) {
					bp_activity_mark_as_ham( $activity_obj, 'by_source' );
					$activity_obj->save();

					return true;
				}

				// Action message for topic creation.
				if ( 'forum_topic_create' === $type ) {
					$action = sprintf(
						'Created a topic, <i><a href="%s">%s</a></i>, on the site %s',
						esc_url( $_POST['url'] ),
						esc_html( $_POST['title'] ),
						esc_html( $_POST['site'] )
					);
				}
				// Action message for reply creation.
				else {
					$action = sprintf(
						'Posted a <a href="%s">reply</a> to <i>%s</i>, on the site %s',
						esc_url( $_POST['url'] ),
						esc_html( $_POST['title'] ),
						esc_html( $_POST['site'] )
					);
				}

				$args = array(
					'user_id'           => $user->ID,
					'action'            => $action,
					'content'           => esc_html( $_POST['message'] ),
					'primary_link'      => esc_url( $_POST['url'] ),
					'component'         => 'forums',
					'type'              => $type,
					'item_id'           => $item_id,
					'secondary_item_id' => intval( $_POST['forum_id'] ),
					'hide_sitewide'     => false,
				);

				return bp_activity_add( $args );
			}
			// Remove activity related to a topic or reply.
			elseif ( in_array( $type, array( 'forum_topic_remove', 'forum_reply_remove' ) ) ) {
				if ( ! $activity_obj ) {
					return '-1 Activity not previously reported.';
				}

				bp_activity_mark_as_spam( $activity_obj, 'by_source' );
				$activity_obj->save();

				return true;
			}
		}

		/**
		 * Handles incoming activities for the Plugins Directory.
		 *
		 * Recognized activities:
		 *  - Creating new plugin
		 */
		private function handle_plugin_activity() {
			$user = $this->get_user( $_POST['user'] );

			if ( ! $user ) {
				return '-1 Activity reported for unrecognized user : ' . sanitize_text_field( $_POST['user'] );
			}

			$args = array(
				'user_id'           => $user->ID,
				'action'            => sprintf(
					'Released a new plugin, <a href="%s">%s</a>',
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
			$user = $this->get_user( $_POST['user'] );

			if ( ! $user ) {
				return '-1 Activity reported for unrecognized user : ' . sanitize_text_field( $_POST['user'] );
			}

			$args = array(
				'user_id'           => $user->ID,
				'action'            => sprintf(
					'Released a new theme, <a href="%s">%s</a>',
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

			$user = $this->get_user( $_POST['user'] );

			if ( ! $user ) {
				return '-1 Activity reported for unrecognized user : ' . sanitize_text_field( $_POST['user'] );
			}

			if ( ! empty( $_POST['description'] ) ) {

				$args = array(
					'user_id'           => $user->ID,
					'action'            => sprintf( 'Created a new ticket in %s Trac', $_POST['trac'] ),
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
					'action'            => sprintf( 'Posted a reply to <i>%s</i> in %s Trac', $_POST['title'], $_POST['trac'] ),
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
					'action'            => sprintf( 'Committed [%s] to %s Trac', $_POST['changeset'], $_POST['trac'] ),
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
					$user = $this->get_user( $username );
					if ( empty( $user ) ) continue;
					$args = array(
						'user_id'           => $user->ID,
						'action'            => sprintf( 'Received props in %s', $_POST['trac'] ),
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
			$user = $this->get_user( $_POST['user'] );
			$type = '';

			if ( ! $user ) {
				return '-1 Activity reported for unrecognized user : ' . sanitize_text_field( $_POST['user'] );
			}

			if ( isset( $_POST['speaker_id'] ) && ! empty( $_POST['speaker_id'] ) ) {
				$type    = 'wordcamp_speaker_add';
				$item_id = $_POST['speaker_id'];

				if ( isset( $_POST['wordcamp_date'] ) && ! empty( $_POST['wordcamp_date'] ) ) {
					$action = sprintf(
						'Confirmed as a speaker for <a href="%s">%s</a> coming up on %s',
						esc_url( $_POST['url'] ),
						$_POST['wordcamp_name'],
						$_POST['wordcamp_date']
					);
				} else {
					$action = sprintf(
						'Confirmed as a speaker for <a href="%s">%s</a>',
						esc_url( $_POST['url'] ),
						$_POST['wordcamp_name']
					);
				}
			} elseif ( isset( $_POST['organizer_id'] ) && ! empty( $_POST['organizer_id'] ) ) {
				$type    = 'wordcamp_organizer_add';
				$item_id = $_POST['organizer_id'];

				if ( isset( $_POST['wordcamp_date'] ) && ! empty( $_POST['wordcamp_date'] ) ) {
					$action = sprintf(
						'Joined the organizing team for <a href="%s">%s</a> coming up on %s',
						esc_url( $_POST['url'] ),
						$_POST['wordcamp_name'],
						$_POST['wordcamp_date']
					);
				} else {
					$action = sprintf(
						'Joined the organizing team for <a href="%s">%s</a>',
						esc_url( $_POST['url'] ),
						$_POST['wordcamp_name']
					);
				}
			} elseif ( isset( $_POST['attendee_id'] ) && ! empty( $_POST['attendee_id'] ) ) {
				$item_id = $_POST['attendee_id'];

				if ( 'attendee_registered' == $_POST['activity_type'] ) {
					$type = 'wordcamp_attendee_add';

					if ( isset( $_POST['wordcamp_date'] ) && ! empty( $_POST['wordcamp_date'] ) ) {
						$action = sprintf(
							'Registered to attend <a href="%s">%s</a> coming up on %s',
							esc_url( $_POST['url'] ),
							$_POST['wordcamp_name'],
							$_POST['wordcamp_date']
						);
					} else {
						$action = sprintf(
							'Registered to attend <a href="%s">%s</a>',
							esc_url( $_POST['url'] ),
							$_POST['wordcamp_name']
						);
					}
				} elseif ( 'attendee_checked_in' == $_POST['activity_type'] ) {
					$type = 'wordcamp_attendee_checked_in';
					$order = absint( $_POST['checked_in_count'] );

					$action = sprintf(
						'Is the %s person to arrive at <a href="%s">%s</a>',
						$this->append_ordinal_suffix( $order ),
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
				$ret = "-1 Unable to save activity: \n";
				foreach ( $args as $k => $v ) {
					$ret .= "\t$k => $v\n";
				}
			}
			return $ret;
		}

		/*
		 * Append an ordinal suffix to the given number
		 *
		 * Based on https://stackoverflow.com/questions/3109978/php-display-number-with-ordinal-suffix
		 *
		 * @param int $number
		 *
		 * @return string
		 */
		private function append_ordinal_suffix( $number ) {
			$ends = array( 'th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th' );

			if ( ( $number % 100 ) >= 11 && ( $number % 100 ) <= 13 ) {
				$suffix = $ends[0];
			} else {
				$suffix = $ends[ $number % 10 ];
			}

			return $number . $suffix;
		}

		/**
		 * Handles incoming activities for a WordPress install.
		 */
		private function handle_wordpress_activity() {
			$user = $this->get_user( $_POST['user'] );

			if ( ! $user ) {
				return '-1 Activity reported for unrecognized user : ' . sanitize_text_field( $_POST['user'] );
			}

			if ( isset( $_POST['comment_id'] ) && ! empty( $_POST['comment_id'] ) ) {
				$type    = 'blog_comment_create';
				$item_id = $_POST['comment_id'];
				$action  = sprintf(
					'Wrote a <a href="%s">comment</a> on the post <i>%s</i>, on the site %s',
					esc_url( $_POST['url'] ),
					$_POST['title'],
					$_POST['blog']
				);
			} else {
				$type    = 'blog_post_create';
				$item_id = $_POST['post_id'];
				$action  = sprintf(
					'Wrote a new post, <i><a href="%s">%s</a></i>, on the site %s',
					esc_url( $_POST['url'] ),
					$_POST['title'],
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
