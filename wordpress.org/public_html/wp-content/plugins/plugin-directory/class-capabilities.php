<?php
namespace WordPressdotorg\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Tools;
/**
 * Manages the capabilities for the WordPress.org plugins directory.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Capabilities {

	public static function map_meta_cap( $required_caps, $cap, $user_id, $context ) {
		switch( $cap ) {

			case 'plugin_add_committer':
			case 'plugin_remove_committer':
			case 'plugin_edit':
				$required_caps = array();
				$post = self::get_post_from_context( $context );
				if ( ! $post ) {
					$required_caps[] = 'do_not_allow';
					break;
				}

				$user = new \WP_User( $user_id );
				$committers = Tools::get_plugin_committers( $post->post_name );

				if ( $post->post_author === $user_id ) {
					$required_caps[] = 'plugin_edit_own';
				} elseif ( in_array( $user->user_login, $committers, true ) ) {
					$required_caps[] = 'plugin_edit_own';
				} else {
					if ( 'pending' == $post->post_status ) {
						$required_caps[] = 'plugin_edit_pending';
					} else {
						$required_caps[] = 'plugin_edit_others';
					}
				}
				break;

			// Don't allow any users to alter the post meta for plugins
			case 'add_post_meta':
			case 'edit_post_meta':
			case 'delete_post_meta':
				$post = get_post( $context );
				if ( $post && 'plugin' == $post->post_type ) {
					$required_caps[] = 'do_not_allow';
					break;
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

	protected static function get_post_from_context( $context ) {
		if ( ! $context ) {
			return false;
		}
		$context = $context[0];

		$post = false;
		if ( is_int( $context ) ) {
			$post = get_post( $context );
		} elseif ( $context instanceof \WP_Post ) {
			$post = $context;
		} elseif ( is_string( $context ) ) {
			$post = Plugin_Directory::get_plugin_post( $context );
		}
		if ( ! $post || 'plugin' != $post->post_type ) {
			return false;
		}
		return $post;
	}

	public static function add_roles() {
		$committer = array(
			'read' => true,
			'plugin_dashboard_access' => true,
			'plugin_edit_own' => true,
			'plugin_set_tags' => true,
			'plugin_add_committer' => true,
		);

		$reviewer = array(
			'read' => true,
			'plugin_dashboard_access' => true,
			'plugin_edit_pending' => true,
			'plugin_approve' => true,
			'plugin_reject' => true,
		);

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
	}
}

