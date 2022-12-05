<?php
/*
Plugin Name: WordPress.org Profiles Association Handler
Plugin URI: http://wordpress.org
Author: Scott Reilly
License: GPL2
Version: 1.0
Description: Handles the associations sent from other services in the .org ecosystem (WP, WordCamp, etc).
*/

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'WPOrg_Profiles_Association_Handler' ) ) {

	class WPOrg_Profiles_Association_Handler {

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_filter( 'bp_groups_global_tables', array( $this, 'change_global_table_names' ) );
			add_filter( 'bp_groups_meta_tables',   array( $this, 'change_meta_table_names' ) );
			add_filter( 'bp_active_components',    array( $this, 'activate_groups_component' ) );
			add_action( 'bp_setup_cache_groups',   array( $this, 'bp_setup_cache_groups' ), 11 );

			add_action( 'plugins_loaded',          array( $this, 'plugins_loaded' ) );
		}

		/**
		 * Actions to run on the 'plugins_loaded' filter.
		 */
		public function plugins_loaded() {
			add_action( 'wp_ajax_nopriv_wporg_handle_association', array( $this, 'handle_association' ) );

			// Disable activity reporting related to groups
			add_filter( 'bp_activity_component_before_save',       array( $this, 'disable_group_activity_reporting' ) );
			remove_action( 'bp_register_activity_actions',         'groups_register_activity_actions' );

			// Workaround (temp?) for wp-admin being considered the current user
			add_filter( 'bp_displayed_user_id', array( $this, 'wp_admin_is_not_a_user' ) );
		}

		/**
		 * Changes the table names to use custom wporg groups tables rather
		 * than the ones for BuddyPress.org.
		 *
		 * @param  array $tables The default tables.
		 * @return array
		 */
		public function change_global_table_names( $tables ) {
			global $bp;

			$tables['table_name']           = $bp->table_prefix . 'wporg_groups';
			$tables['table_name_members']   = $bp->table_prefix . 'wporg_groups_members';
			$tables['table_name_groupmeta'] = $bp->table_prefix . 'wporg_groups_groupmeta';

			return $tables;
		}

		/**
		 * Changes the meta table name to use custom wporg groups meta tables rather
		 * than the ones for BuddyPress.org.
		 *
		 * @param  array $tables The default meta table.
		 * @return array
		 */
		public function change_meta_table_names( $tables ) {
			global $bp;

			$tables['group'] = $bp->table_prefix . 'wporg_groups_groupmeta';

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
					'bp_groups',
					'bp_group_admins',
					'bp_group_invite_count',
					'group_meta',
					'bp_groups_memberships',
					'bp_groups_memberships_for_user',
					'bp_group_mods',
					'bp_groups_invitations_as_memberships',
					'bp_groups_group_type'
				]
			);
		}


		/**
		 * Ensures that the groups component is activated.
		 *
		 * @param  array $activated Array of activated components.
		 * @return array
		 */
		public function activate_groups_component( $activated ) {
			if ( ! isset( $activated['groups'] ) || '1' != $activated['groups'] ) {
				$activated['groups'] = '1';
			}

			return $activated;
		}

		/**
		 * Disables ALL group-related activities being reported to activity stream.
		 *
		 * @param string $component Component.
		 */
		public function disable_group_activity_reporting( $component ) {
			if ( buddypress()->groups->id == $component ) {
				$component = false;
			}

			return $component;
		}

		/**
		 * BP thinks wp-admin is the slug for a user being displayed (which happens
		 * to actually exist on .org), so clear that until a proper fix is pursued.
		 *
		 * @param  int $user_id The user ID of the displayed_user
		 * @return int
		 */
		public function wp_admin_is_not_a_user( $user_id ) {
			if ( '440141' == $user_id ) {
				$user_id = 0;
			}
			return $user_id;
		}

		/**
		 * Primary AJAX handler.
		 *
		 * Funnels incoming requests to appropriate sub-handler based on
		 * $_POST['source'] value.
		 *
		 * By default (and for security), this does nothing. The filter
		 * 'wporg_is_valid_association_request' must be hooked in order to provide
		 * the appropriate validity checks on the request to permit the incoming
		 * association notification to be handled.
		 *
		 * TODO: Make this a generic handler and require sub-handlers to
		 * register themselves.
		 */
		public function handle_association() {
			// Return error if not a valid association request.
			if ( true !== apply_filters( 'wporg_is_valid_association_request', false ) ) {
				die( '-1 Not a valid association request.' );
			}

			// Return error if activities are not enabled.
			if ( ! bp_is_active( 'groups' ) ) {
				die( '-1 Group component not activated.' );
			}

			$source = $_POST['source'];

			switch ( $source ) {
				case 'wordcamp':
				case 'meetups':
				case 'polyglots':
					// These sources may send the field as `user_id`/`association`, so handle that format if not specified otherwise.
					$_POST['users'] ??= (array) $_POST['user_id'];
					$_POST['badge'] ??= $_POST['association'];

					// Fall through, the generic-badge uses `users`/`badge` for the fields, as set above.
				case 'generic-badge':
					$association_id = $this->handle_badge_association();
					break;
				default:
					$association_id = '-1 Unrecognized association source.';
					break;
			}

			if ( false === $association_id ) {
				$association_id = '-1 Unable to save association.';
			}

			$success = intval( $association_id ) > 0 ? '1' : $association_id;
			die( $success );
		}

		/**
		 * Handles incoming associations for the generic '{assign|remove}_badge()' functions. See pub/profile-helpers.php.
		 *
		 * Payload:  (beyond 'action' and 'source')
		 *  users:   User ID(s)/login(s)/nicename(s).
		 *  badge:   Slug for group/association.
		 *  command: Either 'add' or 'remove'
		 */
		private function handle_badge_association() {
			$users    = wp_unslash( $_POST['users'] ?? [] );
			$command  = $_POST['command'] ?? '';
			$badge    = sanitize_key( $_POST['badge'] ?? '' );
			$group_id = BP_Groups_Group::group_exists( $badge );

			if ( ! $badge || ! $group_id ) {
				status_header( 400 );
				return '-1 Association does not exist: ' . $badge;
			}

			if ( 'add' !== $command && 'remove' !== $command ) {
				status_header( 400 );
				return '-1 Unknown association command';
			}

			if ( empty( $users ) ) {
				status_header( 400 );
				return '-1 User(s) not specified';
			}

			// Validate all users.
			foreach ( $users as $i => $user ) {
				if ( is_numeric( $user ) && ( absint( $user ) == $user ) ) {
					$_user = get_user_by( 'id', $user );
				} else {
					$_user = get_user_by( 'login', $user );
					if ( ! $_user ) {
						$_user = get_user_by( 'slug', $user );
					}
				}

				if ( ! $_user ) {
					status_header( 400 );
					return '-1 Association requested for unrecognized user: ' . sanitize_text_field( $user );
				}

				$users[ $i ] = $_user->ID;
			}

			// Defer group re-counts.
			bp_groups_defer_group_members_count( true );

			$users_altered = 0;

			foreach ( $users as $user_id ) {
				if ( 'add' == $command ) {
					if ( ! groups_is_user_member( $user_id, $group_id ) ) {
						groups_join_group( $group_id, $user_id );
						$users_altered++;
					}
				} elseif ( 'remove' == $command ) {
					if ( groups_is_user_member( $group_id, $user_id ) ) {
						groups_leave_group( $group_id, $user_id );
						$users_altered++;
					}
				}
			}

			// If we altered any group memberships, perform the group recounts during shutdown, so as not to delay the request returning.
			if ( $users_altered ) {
				add_action( 'shutdown', function() use( $group_id ) {
					if ( function_exists( 'fastcgi_finish_request' ) ) {
						fastcgi_finish_request();
					}

					bp_groups_defer_group_members_count( false, $group_id );
				} );
			}

			return 1;
		}

	} /* /class WPOrg_Profiles_Association_Handler */
} /* if class_exists */

if ( class_exists( 'WPOrg_Profiles_Association_Handler' ) ) {
	new WPOrg_Profiles_Association_Handler();
}

