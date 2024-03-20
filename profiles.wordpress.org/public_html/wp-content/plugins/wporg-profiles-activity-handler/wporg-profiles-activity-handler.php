<?php
/*
Plugin Name: WordPress.org Profiles Activity Handler
Plugin URI: http://wordpress.org
Author: Mert Yazicioglu, Scott Reilly
Author URI: http://www.mertyazicioglu.com
License: GPL2
Version: 1.1
Description: Handles the activities sent from other services in the .org ecosystem (bbPress, WP, Trac).
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
			add_action( 'bp_setup_cache_groups',     array( $this, 'bp_setup_cache_groups' ), 11 );
			add_action( 'plugins_loaded',            array( $this, 'plugins_loaded' ) );

			// Disable default BP activity features
			add_filter( 'bp_activity_can_comment', '__return_false' );
			add_filter( 'bp_activity_do_mentions', '__return_false' );
			add_filter( 'bp_activity_use_akismet', '__return_false' );

		}

		/**
		 * Gets a user by either login, slug, or id.
		 *
		 * @param string|int $username The user's login, slug, or id.
		 *
		 * @return WP_User|false WP_User object on success, false on failure.
		 */
		protected static function get_user( $username ) {
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
		 * @param array $tables The default tables.
		 *
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
		 * @param array $tables The default meta table.
		 *
		 * @return array
		 */
		public function change_meta_table_names( $tables ) {
			global $bp;

			$tables['activity'] = $bp->table_prefix . 'wporg_activity_meta';

			return $tables;
		}

		/**
		 * Make the cache-group localised to the profile site.
		 *
		 * See https://core.trac.wordpress.org/ticket/54303 for remove_global_group.
		 */
		public function bp_setup_cache_groups() {
			global $wp_object_cache;

			if ( ! is_object( $wp_object_cache ) || 'WPORG_Object_Cache' !== get_class( $wp_object_cache ) ) {
				return;
			}

			$wp_object_cache->global_groups = array_diff(
				$wp_object_cache->global_groups,
				[
					'bp_activity',
					'bp_activity_comments',
					'activity_meta'
				]
			);
		}

		/**
		 * Ensures that the activity component is activated.
		 *
		 * @param array $activated Array of activated components.
		 *
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
			try {
				/*
				 * This is useful for testing on your sandbox.
				 *
				 * e.g., Edit `$_POST['user_id']` so that activity goes to a test account rather than a real one.
				 */
				do_action( 'wporg_profiles_before_handle_activity' );

				// Return error if not a valid activity request.
				if ( true !== apply_filters( 'wporg_is_valid_activity_request', false ) ) {
					throw new Exception( '-1 Not a valid activity request' );
				}

				// Return error if activities are not enabled.
				if ( ! bp_is_active( 'activity' ) ) {
					throw new Exception( '-1 Activity component not activated' );
				}

				$source = sanitize_text_field( $_POST['source'] ?? $_POST['component'] );

				// The original `action` was the `admin-ajax.php` action, which is no longer needed.
				// Renaming this allows simple handlers to pass the sanitized $_POST directly to `bp_activity_add()`.
				$_POST['action'] = $_POST['message'] ?? '';

				// Slack and GlotPress sometimes include user IDs in a different location, and they always use
				// `sanitize_activity()`, which checks for a valid user ID. Checking here too adds complexity and
				// is unnecessary.
				if ( ! in_array( $source, array( 'slack', 'glotpress' ), true ) ) {
					if ( empty( $_POST['user'] ) && empty( $_POST['user_id'] ) ) {
						throw new Exception( '-1 No user specified.' );
					}
				}

				// Disable default BP moderation
				remove_action( 'bp_activity_before_save', 'bp_activity_check_moderation_keys', 2 );
				remove_action( 'bp_activity_before_save', 'bp_activity_check_blacklist_keys',  2 );

				// Disable requirement that user have a display_name set
				remove_filter( 'bp_activity_before_save', 'bporg_activity_requires_display_name' );

				// If an activity doesn't require special logic, then `add_activity()` can be called directly. Compare
				// Learn and Slack to see the difference.
				switch ( $source ) {
					case 'forum':
						$activity_id = $this->handle_forum_activity();
						break;
					case 'learn':
						$activity_id = bp_activity_add( $this->sanitize_activity( $_POST ) );
						break;
					case 'glotpress':
						$activity_id = $this->handle_glotpress_activity( $_POST );
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
					case 'slack':
						$activity_id = $this->handle_slack_activity();
						break;
					default:
						throw new Exception( '-1 Unrecognized activity source' );
						break;
				}

				if ( is_wp_error( $activity_id ) ) {
					throw new Exception( '-1 Unable to save activity: ' . $activity_id->get_error_message() );
				} elseif ( str_starts_with( $activity_id, '-1' ) ) {
					throw new Exception( $activity_id );
				} elseif ( false === $activity_id || intval( $activity_id ) <= 0 ) {
					throw new Exception( '-1 Unable to save activity' );
				}

				$response = '1';

			} catch ( Exception $exception ) {
				trigger_error( $exception->getMessage(), E_USER_WARNING );

				$response = $exception->getMessage();
			}

			die( $response );
		}

		/**
		 * Sanitize `$_POST` args intended for `bp_activity_add()`.
		 *
		 * @throws Exception
		 */
		public static function sanitize_activity( array $activity ) : array {
			$defaults = array(
				// These items are intentionally left out as a precaution.
				// `id`, `recorded_time`, `is_spam`, `hide_sitewide`.

				// These are safe for clients to override.
				'action'            => '',
				'content'           => '',
				'component'         => false,
				'type'              => false,
				'primary_link'      => '',
				'user_id'           => false,
				'item_id'           => false,
				'secondary_item_id' => false,
			);

			$activity = array_intersect_key( $activity, $defaults );
			$activity = array_merge( $defaults, $activity );

			$filters = array(
				'wp_kses_data'        => array( 'action', 'content' ),
				'sanitize_text_field' => array( 'component', 'type' ),
				'intval'              => array( 'user_id', 'item_id', 'secondary_item_id' ),
				'sanitize_url'        => array( 'primary_link' ),
			);

			foreach ( $filters as $filter => $keys ) {
				foreach ( $keys as $key ) {
					$activity[ $key ] = call_user_func( $filter, $activity[ $key ] );
				}
			}

			$user = self::get_user( $activity['user_id'] );

			if ( ! $user ) {
				throw new Exception( '-1 Activity reported for unrecognized user ID: ' . $activity['user_id'] );
			}

			// Standardize on a WP_Error so that the rest of the file can assume it, rather than handling both.
			$activity['error_type'] = 'wp_error';

			return $activity;
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
			$user = self::get_user( $_POST['user'] );
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
				$required_args   = array_merge( $required_args, array( 'title', 'site', 'message', 'url' ) );
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
			$activity_id  = bp_activity_get_activity_id( $args );
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

				} else {
					 // Action message for reply creation.
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

			} elseif ( in_array( $type, array( 'forum_topic_remove', 'forum_reply_remove' ) ) ) {
				// Remove activity related to a topic or reply.
				if ( ! $activity_obj ) {
					// Verbose error on development environments.
					if ( 'production' != wp_get_environment_type() ) {
						return '-1 Activity not previously reported.';
					}

					// Don't need to worry about this on production.
					return true;
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
			$user = self::get_user( $_POST['user'] );

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
			$user = self::get_user( $_POST['user'] );

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
			$user = self::get_user( $_POST['user'] );

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
					$user = self::get_user( $username );
					if ( empty( $user ) ) {
						continue;
					}
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
			$user = self::get_user( $_POST['user'] );
			$type = '';

			if ( ! $user ) {
				return '-1 Activity reported for unrecognized user : ' . sanitize_text_field( $_POST['user'] );
			}

			if ( isset( $_POST['speaker_id'] ) && ! empty( $_POST['speaker_id'] ) ) {
				$type    = 'wordcamp_speaker_add';
				$item_id = $_POST['speaker_id'];

				if ( isset( $_POST['wordcamp_date'] ) && ! empty( $_POST['wordcamp_date'] ) ) {
					$action = sprintf(
						'Confirmed as a speaker for <a href="%s">%s</a>',
						esc_url( $_POST['url'] ),
						$_POST['wordcamp_name']
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
						'Joined the organizing team for <a href="%s">%s</a>',
						esc_url( $_POST['url'] ),
						$_POST['wordcamp_name']
					);
				} else {
					$action = sprintf(
						'Joined the organizing team for <a href="%s">%s</a>',
						esc_url( $_POST['url'] ),
						$_POST['wordcamp_name']
					);
				}

			} elseif ( isset( $_POST['type'] ) && 'mentor_assign' === $_POST['type'] ) {
				$type          = 'wordcamp_mentor_assign';
				$item_id       = absint( $_POST['wordcamp_id'] );
				$wordcamp_name = sanitize_text_field( $_POST['wordcamp_name'] );

				if ( empty( $_POST['url'] ) ) {
					$action = 'Started mentoring ' . $wordcamp_name;
				} else {
					$action = sprintf(
						'Started mentoring <a href="%s">%s</a>',
						sanitize_url( $_POST['url'] ),
						$wordcamp_name
					);
				}

			} elseif ( isset( $_POST['attendee_id'] ) && ! empty( $_POST['attendee_id'] ) ) {
				$item_id = $_POST['attendee_id'];

				if ( 'attendee_registered' == $_POST['activity_type'] ) {
					$type = 'wordcamp_attendee_add';

					if ( isset( $_POST['wordcamp_date'] ) && ! empty( $_POST['wordcamp_date'] ) ) {
						$action = sprintf(
							'Registered to attend <a href="%s">%s</a>',
							esc_url( $_POST['url'] ),
							$_POST['wordcamp_name']
						);
					} else {
						$action = sprintf(
							'Registered to attend <a href="%s">%s</a>',
							esc_url( $_POST['url'] ),
							$_POST['wordcamp_name']
						);
					}

				} elseif ( 'attendee_checked_in' == $_POST['activity_type'] ) {
					$type  = 'wordcamp_attendee_checked_in';
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
				'primary_link'      => $_POST['url'] ?? '',
				'component'         => 'wordcamp',
				'type'              => $type,
				'item_id'           => intval( $item_id ),
				'secondary_item_id' => intval( $_POST['wordcamp_id'] ),
				'hide_sitewide'     => false,
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
			$user = self::get_user( $_POST['user'] );
			$content      = $_POST['content'];
			$primary_link = sanitize_url( $_POST['url'] );

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
			} elseif ( isset( $_POST['type'] ) && 'new' === $_POST['type'] ) {
				$type    = 'blog_post_create';
				$item_id = $_POST['post_id'];

				switch ( $_POST['post_type'] ) {
					case 'wporg_workshop':
						$post_type = 'workshop';
						break;

					case 'lesson-plan':
						$post_type = 'lesson plan';
						break;

					case 'course':
						$post_type = 'course';
						break;

					case 'handbook':
					case str_contains( $_POST['post_type'], '-handbook' ):
						$post_type = 'handbook page';
						break;

					default:
						$post_type = 'post';
				}

				$action = sprintf(
					'Wrote a new %s, <i><a href="%s">%s</a></i>, on the site %s',
					$post_type,
					esc_url( $_POST['url'] ),
					$_POST['title'],
					$_POST['blog']
				);
			} elseif ( isset( $_POST['type'] ) && 'update' === $_POST['type'] ) {
				// Handbooks are currently the only post type that send notifications of updates.
				$type    = 'blog_handbook_update';
				$item_id = $_POST['post_id'];
				$action  = ''; // Will be set by `digest_bump()`
				$content = false;
				$primary_link = sanitize_url( $_POST['blog_url'] ); // To group digest entries by site.

				$singular = sprintf(
					'Updated a handbook page on <a href="%s">%s</a>.',
					sanitize_url( $_POST['blog_url'] ),
					sanitize_text_field( $_POST['blog'] )
				);

				$plural = sprintf(
					'Made %s updates to handbook pages on <a href="%s">%s</a>.',
					'%d',
					sanitize_url( $_POST['blog_url'] ),
					sanitize_text_field( $_POST['blog'] )
				);
			}

			$args = array(
				'user_id'           => $user->ID,
				'action'            => $action,
				'content'           => $content,
				'primary_link'      => $primary_link,
				'component'         => 'blogs',
				'type'              => $type,
				'item_id'           => intval( $item_id ),
				'secondary_item_id' => false,
				'hide_sitewide'     => false,
			);

			if ( 'blog_handbook_update' === $type ) {
				$activity_id = $this->digest_bump( $args, 1, $singular, $plural, true );
			} else {
				$activity_id = bp_activity_add( $args );
			}

			return $activity_id;
		}

		/**
		 * Process activity stream requests from wordpress.slack.com (via api.wordpress.org).
		 *
		 * The giver/recipient IDs have already been validated by `api.w.org/.../props`, so we can assume they're
		 * valid.
		 *
		 * @return bool|string `true` if all activities are added, string error message if any of them fail.
		 */
		protected function handle_slack_activity() {
			$activity_type  = sanitize_text_field( $_POST['activity'] );
			$user_case_args = array();
			$errors         = '';

			$default_args = array(
				'component'  => 'slack',
				'type'       => "slack_$activity_type",
				'error_type' => 'wp_error',
			);

			switch ( $activity_type ) {
				case 'props_given':
					$user_case_args = $this->handle_props_given( $_POST );
					break;

				default:
					$errors .= "-1 Unrecognized Slack activity. ";
			}

			if ( ! $errors ) {
				foreach ( $user_case_args as $case_args ) {
					$new_activity_args = array_merge( $default_args, $case_args );
					$activity_id       = bp_activity_add( $new_activity_args );

					if ( ! is_int( $activity_id ) ) {
						$errors .= sprintf(
							'-1 Unable to save activity for %d: %s. ',
							$case_args['user_id'],
							$activity_id->get_error_message()
						);
					}
				}
			}

			return $errors ?: true;
		}

		protected function handle_props_given( $post_unsafe ) {
			$giver_id       = (int) $post_unsafe['giver_user']['id'];
			$giver_username = sanitize_text_field( $post_unsafe['giver_user']['user_login'] );
			$recipient_ids  = array_map( 'intval', $post_unsafe['recipient_ids'] );
			$url            = wp_http_validate_url( $post_unsafe['url'] );
			$message        = sanitize_text_field( $post_unsafe['message'] );
			$message_id     = sanitize_text_field( $post_unsafe['message_id'] ); // {channel}-{timestamp}

			$action_given = sprintf(
				'<a href="%s">Gave props</a> in <a href="https://make.wordpress.org/chat/">Slack</a>',
				esc_url_raw( $url ),
			);

			$action_received = sprintf(
				'<a href="%1$s">Received props</a> from <a href="https://profiles.wordpress.org/%2$s/">@%2$s</a> in <a href="https://make.wordpress.org/chat/">Slack</a>',
				esc_url_raw( $url ),
				$giver_username,
			);

			$user_case_args[] = array(
				'user_id'      => $giver_id,
				'item_id'      => $message_id,
				'primary_link' => $url,
				'action'       => $action_given,
				'content'      => wp_kses_data( $message ),
			);

			foreach ( $recipient_ids as $recipient_id ) {
				$user_case_args[] = array(
					'user_id'      => $recipient_id,
					'item_id'      => $message_id,
					'primary_link' => $url,
					'action'       => $action_received,
					'content'      => wp_kses_data( $message ),
				);
			}

			return $user_case_args;
		}

		/**
		 * Process activity from translate.w.org (via api.wordpress.org).
		 *
		 * @return bool|string `true` if all activities are added, string error message if any of them fail.
		 * @throws Exception
		 */
		protected function handle_glotpress_activity( $post_unsafe ) {
			$activities = array();
			$errors     = '';

			// The client may send multiple activities in a single request.
			if ( isset( $post_unsafe['activities'] ) ) {
				$activities = $post_unsafe['activities'];
			} else {
				$activities[] = $post_unsafe;
			}

			foreach ( $activities as $activity ) {
				$bump        = intval( $activity['bump'] ?? 1 );
				$activity = $this->sanitize_activity( $activity );

				switch ( $activity['type'] ) {
					case 'glotpress_translation_suggested':
						$action = 'Suggested';
						break;

					case 'glotpress_translation_approved':
						$action = 'Translated';
						break;

					case 'glotpress_translation_reviewed':
						$action = 'Reviewed';
						break;
				}

				$singular    = $action . ' %d string on <a href="https://translate.wordpress.org">translate.wordpress.org</a>.';
				$plural      = $action . ' %d strings on <a href="https://translate.wordpress.org">translate.wordpress.org</a>.';
				$activity_id = $this->digest_bump( $activity, $bump, $singular, $plural );

				if ( is_wp_error( $activity_id ) ) {
					$errors .= sprintf(
						'-1 Unable to save activity for %d: %s. ',
						$activity['user_id'],
						$activity_id->get_error_message()
					);
				}
			}

			return $errors ?: true;
		}

		/**
		 * Bump the count for an activity digest.
		 *
		 * Many contributions happen too frequently to show each one on a profile, because they would quickly fill
		 * it, and crowd out all of the person's other activities. This creates a rolling digest, so that only 1
		 * entry per day is created. That single entry gets updated each time a new action occurs, so the latest
		 * count is always displayed.
		 *
		 * @param bool $group_by_site If `true`, a separate digest entry is created for each site; otherwise a
		 *                            single entry is shared across all sites. If `true`,
		 *                            `$new_activity['primary_link']` must be the _site_ URL, rather than the
		 *                            _post_ URL.
		 *
		 * @return WP_Error|int
		 */
		protected function digest_bump(
			array $new_activity, int $bump, string $action_singular, string $action_plural,
			bool $group_by_site = false
		) {
			// Standardize on this to reduce the number of paths in error handling code.
			// Also done in `sanitize_activity()`, but callers aren't guaranteed to use that.
			$new_activity['error_type'] = 'wp_error';

			$args = array(
				'fields'   => 'ids',
				'per_page' => 1,

				'date_query' => array(
					array(
						'after' => date( 'Y-m-d H:i:s', strtotime( 'today midnight' ) ),
					),
				),

				'filter_query' => array(
					array(
						'column'   => 'component',
						'value'    => $new_activity['component'],
						'relation' => 'AND',
					),
					array(
						'column'   => 'type',
						'value'    => $new_activity['type'],
						'relation' => 'AND',
					),
					array(
						'column'   => 'user_id',
						'value'    => $new_activity['user_id'],
						'relation' => 'AND',
					),
				),
			);

			if ( $group_by_site ) {
				$args['filter_query'][] = array(
					array(
						'column'   => 'primary_link',
						'value'    => $new_activity['primary_link'],
						'relation' => 'AND',
					),
				);
			}

			$stored_activity_id = bp_activity_get( $args )['activities'][0] ?? false;
			$current_count      = (int) bp_activity_get_meta( $stored_activity_id, 'digest_count', true );
			$new_total          = $current_count + $bump;

			$new_action = sprintf(
				_n( $action_singular, $action_plural, $new_total ),
				$new_total
			);

			if ( $stored_activity_id ) {
				$activity_object         = new BP_Activity_Activity( $stored_activity_id );
				$activity_object->action = $new_action;
				$saved                   = $activity_object->save();
				$activity_id             = is_wp_error( $saved ) ? $saved : $stored_activity_id;

				// Increase this even if couldn't update action, to preserve accurate count.
				bp_activity_update_meta( $stored_activity_id, 'digest_count', $new_total );

			} else {
				$new_activity['action'] = $new_action;
				$activity_id            = bp_activity_add( $new_activity );

				bp_activity_update_meta( $activity_id, 'digest_count', $bump );
			}

			return $activity_id;
		}
	} /* /class WPOrg_Profiles_Activity_Handler */
} /* if class_exists */

if ( class_exists( 'WPOrg_Profiles_Activity_Handler' ) ) {
	new WPOrg_Profiles_Activity_Handler();
}
