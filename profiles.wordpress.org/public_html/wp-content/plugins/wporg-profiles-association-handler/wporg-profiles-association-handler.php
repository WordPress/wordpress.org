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
		 * @var WPOrg_Profiles_Association_Handler|null
		 */
		protected static $instance = null;

		/**
		 * Returns the shared instance, constructing it on first call.
		 *
		 * Used by both the bootstrap at the bottom of this file and by the
		 * CLI queue processor to avoid duplicate hook registration.
		 */
		public static function get_instance() {
			return self::$instance ??= new self();
		}

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
			add_action( 'wp_ajax_nopriv_wporg_handle_association', array( $this, 'ajax_handle_association' ) );

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
		 * AJAX wrapper for handle_association().
		 *
		 * Validates the request, delegates to handle_association(), and dies with the result.
		 */
		public function ajax_handle_association() {
			if ( true !== apply_filters( 'wporg_is_valid_association_request', false ) ) {
				status_header( 400 );
				die( '-1 Not a valid association request.' );
			}

			$result = $this->handle_association( wp_unslash( $_POST ) );

			if ( is_wp_error( $result ) ) {
				$status = $result->get_error_data()['status'] ?? 500;
				status_header( $status );
				die( '-1 ' . $result->get_error_message() );
			}

			die( '1' );
		}

		/**
		 * Primary handler for association requests.
		 *
		 * Funnels incoming requests to appropriate sub-handler based on
		 * $data['source'] value.
		 *
		 * @param array $data The request data.
		 * @return true|WP_Error
		 */
		public function handle_association( array $data ) {
			if ( ! bp_is_active( 'groups' ) ) {
				return new WP_Error( 'groups_inactive', 'Group component not activated.', [ 'status' => 500 ] );
			}

			$source = $data['source'];

			switch ( $source ) {
				case 'wordcamp':
				case 'meetups':
				case 'polyglots':
					// These sources may send the field as `user_id`/`association`, so handle that format if not specified otherwise.
					$data['users'] ??= (array) $data['user_id'];
					$data['badge'] ??= $data['association'];

					// Fall through, the generic-badge uses `users`/`badge` for the fields, as set above.
				case 'generic-badge':
					return $this->handle_badge_association( $data );
				default:
					return new WP_Error( 'unknown_source', 'Unrecognized association source.', [ 'status' => 400 ] );
			}
		}

		/**
		 * Handles incoming associations for the generic '{assign|remove}_badge()' functions. See pub/profile-helpers.php.
		 *
		 * @param array $data {
		 *     @type array  $users   User ID(s)/login(s)/nicename(s).
		 *     @type string $badge   Slug for group/association.
		 *     @type string $command Either 'add' or 'remove'.
		 * }
		 * @return true|WP_Error
		 */
		private function handle_badge_association( array $data ) {
			$users    = $data['users'] ?? [];
			$command  = $data['command'] ?? '';
			$badge    = sanitize_key( $data['badge'] ?? '' );
			$group_id = BP_Groups_Group::group_exists( $badge );

			if ( ! $badge || ! $group_id ) {
				return new WP_Error( 'no_association', 'Association does not exist: ' . $badge, [ 'status' => 400 ] );
			}

			if ( 'add' !== $command && 'remove' !== $command ) {
				return new WP_Error( 'bad_command', 'Unknown association command', [ 'status' => 400 ] );
			}

			if ( empty( $users ) ) {
				return new WP_Error( 'no_users', 'User(s) not specified', [ 'status' => 400 ] );
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
					return new WP_Error( 'unknown_user', 'Association requested for unrecognized user: ' . sanitize_text_field( $user ), [ 'status' => 400 ] );
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
					if ( groups_is_user_member( $user_id, $group_id ) ) {
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

			return true;
		}

	} /* /class WPOrg_Profiles_Association_Handler */
} /* if class_exists */

if ( class_exists( 'WPOrg_Profiles_Association_Handler' ) ) {
	WPOrg_Profiles_Association_Handler::get_instance();
}

