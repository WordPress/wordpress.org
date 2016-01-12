<?php
/**
 * Plugin name: GlotPress: WordPress.org Customizations
 * Description: Provides general customizations for translate.wordpress.org.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

class WPorg_GP_Customizations {

	function __construct() {
		add_filter( 'gp_url_profile', array( $this, 'worg_profile_url' ), 10, 2 );
		add_filter( 'gp_projects', array( $this, 'natural_sort_projects' ), 10, 2 );
		add_action( 'gp_before_request', array( $this, 'disable_translation_propagation_on_import' ), 10, 2 );
	}

	/**
	 * Disables propagation of translations on translation imports by users.
	 *
	 * @param string $class_name         Class name of route handler.
	 * @param string $last_method_called Method name of route handler.
	 */
	public function disable_translation_propagation_on_import( $class_name, $last_method_called ) {
		if ( 'GP_Route_Translation' === $class_name && 'import_translations_post' === $last_method_called ) {
			add_filter( 'enable_propagate_translations_across_projects', '__return_false' );
		}
	}

	/**
	 * Returns the profile.wordpress.org URL of a user.
	 *
	 * @param string $url      Current profile URL.
	 * @param string $nicename The URL-friendly user name.
	 * @return string profile.wordpress.org URL
	 */
	public function worg_profile_url( $url, $nicename ) {
		return 'https://profiles.wordpress.org/' . $nicename;
	}

	/**
	 * Natural sorting for sub-projects, and attach whitelisted meta.
	 *
	 * @param GP_Project[] $sub_projects List of sub-projects.
	 * @param int          $parent_id    Parent project ID.
	 * @return [<description>]GP_Project[] Filtered sub-projects.
	 */
	public function natural_sort_projects( $sub_projects, $parent_id ) {
		if ( in_array( $parent_id, array( 1, 13, 58 ) ) ) { // 1 = WordPress, 13 = BuddyPress, 58 = bbPress
			usort( $sub_projects, function( $a, $b ) {
				return - strcasecmp( $a->name, $b->name );
			} );
		}

		if ( in_array( $parent_id, array( 17, 523 ) ) ) { // 17 = Plugins, 523 = Themes
			usort( $sub_projects, function( $a, $b ) {
				return strcasecmp( $a->name, $b->name );
			} );
		}

		// Attach wp-themes meta keys
		if ( 523 == $parent_id ) {
			foreach ( $sub_projects as $project ) {
				$project->non_db_field_names = array_merge( $project->non_db_field_names, array( 'version', 'screenshot' ) );
				$project->version = gp_get_meta( 'wp-themes', $project->id, 'version' );
				$project->screenshot = esc_url( gp_get_meta( 'wp-themes', $project->id, 'screenshot' ) );
			}
		}

		return $sub_projects;
	}
}

function wporg_gp_customizations() {
	global $wporg_gp_customizations;

	if ( ! isset( $wporg_gp_customizations ) ) {
		$wporg_gp_customizations = new WPorg_GP_Customizations();
	}

	return $wporg_gp_customizations;
}
add_action( 'plugins_loaded', 'wporg_gp_customizations' );
