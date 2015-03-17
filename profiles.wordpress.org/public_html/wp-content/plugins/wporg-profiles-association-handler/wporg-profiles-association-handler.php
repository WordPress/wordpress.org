<?php
/*
Plugin Name: WordPress.org Profiles Association Handler
Plugin URI: http://wordpress.org
Author: Scott Reilly
License: GPL2
Version: 1.0
Description: Handles the associations sent from other services in the .org ecosystem (WP, WordCamp, WP, etc).
*/

defined( 'ABSPATH' ) or die();

if ( ! class_exists( 'WPOrg_Profiles_Association_Handler' ) ) {

	class WPOrg_Profiles_Association_Handler {

		/**
		 * Constructor.
		 */
		public function __construct() {
			add_filter( 'bp_groups_global_tables', array( $this, 'change_table_names' ) );
			add_filter( 'bp_active_components',    array( $this, 'activate_groups_component' ) );
			add_action( 'plugins_loaded',          array( $this, 'plugins_loaded' ) );
		}

		/**
		 * Actions to run on the 'plugins_loaded' filter.
		 */
		public function plugins_loaded() {
			add_action( 'wp_ajax_nopriv_wporg_handle_association', array( $this, 'handle_association' ) );

			// Approve users added to groups
//			add_action( 'groups_join_group', array( $this, 'auto_accept_group_invite_for_user' ), 10, 2 );

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
		public function change_table_names( $tables ) {
			global $bp;

			return array(
				'table_name'           => $bp->table_prefix . 'wporg_groups',
				'table_name_members'   => $bp->table_prefix . 'wporg_groups_members',
				'table_name_groupmeta' => $bp->table_prefix . 'wporg_groups_groupmeta',
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
		 * Accept all invitations to a group on behalf of the user.
		 *
		 * @param int $group_id Group ID
		 * @param int $user_id  User ID
		 */
		public function auto_accept_group_invite_for_user( $group_id, $user_id ) {
			//TODO
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
					$association_id = $this->handle_wordcamp_association();
					break;
				case 'polyglots':
					$association_id = $this->handle_polyglots_association();
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
		 * Handles incoming associations for WordCamp.
		 *
		 * Payload: (beyond 'action' and 'source')
		 *  user_id:     User ID
		 *  association: Slug for group/association
		 *  command:     Either 'add' or 'remove'
		 */
		private function handle_wordcamp_association() {
			$user = get_user_by( 'id', $_POST['user_id'] );

			if ( ! $user ) {
				return '-1 Association reported for unrecognized user: ' . sanitize_text_field( $_POST['user_id'] );
			}

			$association = sanitize_key( $_POST['association'] );

			$associated_associations = array( 'wordcamp-organizer', 'wordcamp-speaker' );

			if ( ! in_array( $association, $associated_associations ) ) {
				return '-1 Unrecognized association type';
			}

			if ( ! $group_id = BP_Groups_Group::group_exists( $association ) ) {
				return '-1 Association does not exist: ' . $association;
			}

			if ( 'add' == $_POST['command'] ) {
				groups_join_group( $group_id, $user->ID );
				groups_accept_invite( $user->ID, $group_id );
			} elseif ( 'remove' == $_POST['command'] ) {
				groups_leave_group( $group_id, $user->ID );
			} else {
				return '-1 Unknown association command';
			}

			return 1;
		}

		/**
		 * Handles incoming associations for Polyglots/translate.wordpress.org.
		 *
		 * Payload: (beyond 'action' and 'source')
		 *  user_id:     User ID
		 *  association: Slug for group/association
		 *  command:     Either 'add' or 'remove'
		 */
		private function handle_polyglots_association() {
			$user = get_user_by( 'id', $_POST['user_id'] );

			if ( ! $user ) {
				return '-1 Association reported for unrecognized user: ' . sanitize_text_field( $_POST['user_id'] );
			}

			$association = sanitize_key( $_POST['association'] );

			$associated_associations = array( 'translation-editor', 'translation-contributor' );

			if ( ! in_array( $association, $associated_associations ) ) {
				return '-1 Unrecognized association type';
			}

			if ( ! $group_id = BP_Groups_Group::group_exists( $association ) ) {
				return '-1 Association does not exist: ' . $association;
			}

			if ( 'add' == $_POST['command'] ) {
				groups_join_group( $group_id, $user->ID );
				groups_accept_invite( $user->ID, $group_id );
			} elseif ( 'remove' == $_POST['command'] ) {
				groups_leave_group( $group_id, $user->ID );
			} else {
				return '-1 Unknown association command';
			}

			return 1;
		}

	} /* /class WPOrg_Profiles_Association_Handler */
} /* if class_exists */

if ( class_exists( 'WPOrg_Profiles_Association_Handler' ) ) {
	new WPOrg_Profiles_Association_Handler();
}

