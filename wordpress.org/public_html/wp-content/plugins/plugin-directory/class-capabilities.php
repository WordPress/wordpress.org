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
	 * @param array  $required_caps Returns the user's actual capabilities.
	 * @param string $cap           Capability name.
	 * @param int    $user_id       The user ID.
	 * @param array  $context       Adds the context to the cap. Typically the object ID.
	 */
	public static function map_meta_cap( $required_caps, $cap, $user_id, $context ) {
		switch( $cap ) {

			case 'plugin_edit':
			case 'plugin_add_committer':
			case 'plugin_remove_committer':
				$required_caps = array();
				$post = get_post( $context[0] );
				if ( ! $post instanceof \WP_Post ) {
					$required_caps[] = 'do_not_allow';
					break;
				}

				$user       = new \WP_User( $user_id );
				$committers = Tools::get_plugin_committers( $post->post_name );

				if ( $post->post_author === $user_id || in_array( $user->user_login, $committers, true ) ) {
					$required_caps[] = 'plugin_edit_own';

				} else {
					if ( 'pending' == $post->post_status ) {
						$required_caps[] = 'plugin_edit_pending';

					} else {
						$required_caps[] = 'plugin_edit_others';
					}
				}
				break;

			// Don't allow any users to alter the post meta for plugins.
			case 'add_post_meta':
			case 'edit_post_meta':
			case 'delete_post_meta':
				$post = get_post( $context );
				if ( $post && 'plugin' == $post->post_type ) {
					$required_caps[] = 'do_not_allow';
				}
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

	public static function add_roles() {
		$committer = array(
			'read' => true,
			'plugin_dashboard_access' => true,
			'plugin_edit_own' => true,
			'plugin_set_tags' => true,
			'plugin_add_committer' => true,
		);

		$reviewer = array_merge( $committer, array(
			'plugin_edit_pending' => true,
			'plugin_approve' => true,
			'plugin_reject' => true,
		) );

		$admin = array_merge( $reviewer, array(
			'plugin_add_committer' => true,
			'plugin_edit_others' => true,
			'plugin_disable' => true,
			'plugin_close' => true,
			'plugin_set_category' => true, // Special categories
		) );

		// Remove the roles first, incase we've changed the permission set.
		remove_role( 'plugin_committer' );
		remove_role( 'plugin_reviewer' );
		remove_role( 'plugin_admin' );
		add_role( 'plugin_committer', 'Plugin Committer', $committer );
		add_role( 'plugin_reviewer',  'Plugin Reviewer',  $reviewer );
		add_role( 'plugin_admin',     'Plugin Admin',     $admin );

		foreach( array( 'contributor', 'author', 'editor', 'administrator' ) as $role ) {
			$wp_role = get_role( $role );

			foreach ( $committer as $committer_cap ) {
				$wp_role->add_cap( $committer_cap );
			}

			if ( in_array( $role, array( 'editor', 'administrator' ) ) ) {
				foreach ( $admin as $admin_cap ) {
					$wp_role->add_cap( $admin_cap );
				}
			}
		}
	}
}

