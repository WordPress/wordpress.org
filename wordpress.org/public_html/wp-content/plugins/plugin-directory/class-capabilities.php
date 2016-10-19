<?php
namespace WordPressdotorg\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * Manages the capabilities for the WordPress.org plugins directory.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Capabilities {

	/**
	 * Filters a user's capabilities depending on specific context and/or privilege.
	 *
	 * @static
	 *
	 * @param array  $required_caps Returns the user's actual capabilities.
	 * @param string $cap           Capability name.
	 * @param int    $user_id       The user ID.
	 * @param array  $context       Adds the context to the cap. Typically the object ID.
	 * @return array Primitive caps.
	 */
	public static function map_meta_cap( $required_caps, $cap, $user_id, $context ) {
		$plugin_edit_cap = false;
		switch( $cap ) {
			case 'plugin_admin_edit':
			case 'plugin_add_committer':
			case 'plugin_remove_committer':
				$plugin_edit_cap = true;
				// Fall through

			case 'plugin_admin_view':
				// Committers + Contributors.
				// If no committers, post_author.
				$required_caps = array();
				$post = get_post( $context[0] );

				if ( ! $post ) {
					$required_caps[] = 'do_not_allow';
					break;
				}

				$user = new \WP_User( $user_id );
				if ( $user->has_cap( 'plugin_review' ) ) {
					$required_caps[] = 'plugin_review';
					break;
				}

				// Committers
				$committers = Tools::get_plugin_committers( $post->post_name );
				if ( ! $committers && 'publish' === $post->post_status ) {
					// post_author in the event no committers exist (yet?)
					$committers = array( get_user_by( 'ID', $post->post_author )->user_login );
				}

				if ( in_array( $user->user_login, $committers ) ) {
					$required_caps[] = 'exist'; // All users are allowed to exist, even when they have no role.
					break;
				}

				if ( ! $plugin_edit_cap ) {
					// Contributors can view, but not edit.
					$contributors = (array) wp_list_pluck( get_the_terms( $post, 'plugin_contributors' ), 'name' );
					if ( in_array( $user->user_nicename, $contributors, true ) ) {
						$required_caps[] = 'exist'; // All users are allowed to exist, even when they have no role.
						break;
					}
				}

				// Else;
				$required_caps[] = 'do_not_allow';
				break;

			case 'plugin_transition':
				/*
				 Handle the transition between
				 pending -> publish
				 publish -> rejected
				 publish -> closed
				 etc
				*/
				break;
		}

		return $required_caps;
	}

	/**
	 * Sets up custom roles and makes them available.
	 *
	 * @static
	 */
	public static function add_roles() {

		$reviewer = array(
			'read'                 => true,
			'plugin_set_category'  => true,
			'moderate_comments'    => true,
			'plugin_edit_pending'  => true,
			'plugin_review'        => true,
		);

		$admin = array_merge( $reviewer, array(
			'plugin_approve'     => true,
			'plugin_reject'      => true,
			'plugin_disable'     => true,
			'plugin_close'       => true,
			'plugin_set_section' => true, // Special categories.
			'manage_categories'  => true, // Let them assign these special categories.
		) );

		// Remove the roles first, incase we've changed the permission set.
		remove_role( 'plugin_reviewer' );
		remove_role( 'plugin_admin'    );
		add_role( 'plugin_reviewer',  'Plugin Reviewer', $reviewer );
		add_role( 'plugin_admin',     'Plugin Admin',    $admin    );

		$wp_admin_role = get_role( 'administrator' );
		if ( $wp_admin_role ) {
			foreach ( $admin as $admin_cap => $value ) {
				$wp_admin_role->add_cap( $admin_cap );
			}
		}

		update_option( 'default_role', 'subscriber' );
	}
}

