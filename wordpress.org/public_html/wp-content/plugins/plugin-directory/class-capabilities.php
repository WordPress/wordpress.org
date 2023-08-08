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
		$handled_caps = array(
			// All these caps must pass a WP_Post context.
			'plugin_admin_view',
			'plugin_admin_edit',
			'plugin_add_committer',
			'plugin_remove_committer',
			'plugin_add_support_rep',
			'plugin_remove_support_rep',
			'plugin_self_transfer',
			'plugin_self_close',
			'plugin_manage_releases',
		);
		if ( ! in_array( $cap, $handled_caps ) ) {
			return $required_caps;
		}

		// Protect against a cap call without a plugin context.
		$post = $context ? get_post( $context[0] ) : false;
		if ( ! $post ) {
			return array( 'do_not_allow' );
		}

		// The current user instance.
		$user = new \WP_User( $user_id );

		// Shortcut, if no user specified, we can't help.
		if ( ! $user_id || ! $user->exists() ) {
			return array( 'do_not_allow' );
		}

		// Start over, we'll specify all caps below.
		$required_caps = array();

		// Certain actions require the plugin to be published.
		if (
			'publish' !== $post->post_status &&
			in_array(
				$cap,
				array(
					'plugin_self_transfer',
					'plugin_self_close',
				)
			)
		) {
			$required_caps[] = 'do_not_allow';
		}

		// Disable (or restrict to reviewers) release management.
		if ( 'plugin_manage_releases' === $cap ) {
			if ( 'disabled' === $post->post_status || 'closed' === $post->post_status ) {
				// Plugin reviewers can approve for disabled/closed plugins.
				$required_caps[] = 'plugin_review';
			} elseif ( ! in_array( $post->post_status, [ 'publish', 'approved' ] ) ) {
				// A non-published plugin cannot have it's releases approved otherwise.
				$required_caps[] = 'do_not_allow';
			}
		}

		// If a plugin is in the Beta or Featured views, they're not able to self-manage certain things. Require reviewer.
		if (
			in_array(
				$cap,
				array(
					'plugin_self_close',
					'plugin_self_transfer',
					'plugin_add_committer',
					'plugin_remove_committer',
				)
			) &&
			is_object_in_term( $post->ID, 'plugin_section', array( 'beta', 'featured' ) )
		) {
			$required_caps[] = 'plugin_review';
		}

		// Only the Owner of a plugin is able to transfer plugins.
		if ( 'plugin_self_transfer' === $cap && $user_id != $post->post_author ) {
			$required_caps[] = 'do_not_allow';
		}

		// Committers
		$committers = Tools::get_plugin_committers( $post->post_name );
		// If there are no committers, use the plugin author if the plugin is published.
		if ( ! $committers && 'publish' === $post->post_status ) {
			$committers = array( get_user_by( 'ID', $post->post_author )->user_login );
		}

		if ( in_array( $user->user_login, $committers ) ) {
			$required_caps[] = 'exist';
		}

		// Contributors can view, but not edit.
		if ( 'plugin_admin_view' === $cap ) {
			$terms = get_the_terms( $post, 'plugin_contributors' );
			if ( is_array( $terms ) ) {
				$contributors = (array) wp_list_pluck( $terms, 'name' );
				if ( in_array( $user->user_nicename, $contributors, true ) ) {
					$required_caps[] = 'exist';
				}
			}
		}

		// Allow users with review caps to access, unless they've been explicitly denied above.
		if ( $user->has_cap( 'plugin_review' ) ) {
			$required_caps[] = 'plugin_review';
		}

		// If we've not found a matching user/cap, deny.
		if ( ! $required_caps ) {
			$required_caps[] = 'do_not_allow';
		}

		return array_unique( $required_caps );
	}

	/**
	 * Sets up custom roles and makes them available.
	 *
	 * @static
	 */
	public static function add_roles() {
		$reviewer = array(
			'read'                    => true,
			'plugin_set_category'     => true,
			'moderate_comments'       => true,
			'plugin_edit_pending'     => true,
			'plugin_review'           => true,
			'plugin_dashboard_access' => true,
			'plugin_edit'             => true,
			'plugin_edit_others'      => true,
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
		remove_role( 'plugin_admin' );
		add_role( 'plugin_reviewer', 'Plugin Reviewer', $reviewer );
		add_role( 'plugin_admin', 'Plugin Admin', $admin );

		$wp_admin_role = get_role( 'administrator' );
		if ( $wp_admin_role ) {
			foreach ( $admin as $admin_cap => $value ) {
				$wp_admin_role->add_cap( $admin_cap );
			}
		}

		update_option( 'default_role', 'subscriber' );
	}
}
