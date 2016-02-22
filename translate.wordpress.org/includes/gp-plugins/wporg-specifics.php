<?php

/**
 * @author Nikolay
 */
class GP_WPorg_Specifics extends GP_Plugin {
	var $id = 'wporg-specifics';

	function __construct() {
		parent::__construct();
		$this->add_action( 'before_login_form' );
		$this->add_filter( 'gp_url_profile', array( 'args' => 2 ) );
		$this->add_action( 'init' );
		$this->add_filter( 'projects', array( 'args' => 2 ) );
		$this->add_action( 'before_request', array( 'args' => 2 ) );
		$this->add_action( 'project_created' );
		$this->add_action( 'project_saved' );
	}

	function project_created() {
		$this->update_projects_last_updated();
	}

	function project_saved() {
		$this->update_projects_last_updated();
	}

	/**
	 * Stores the timestamp of the last update for projects.
	 *
	 * Used by the Rosetta Roles plugin to invalidate local caches.
	 */
	function update_projects_last_updated() {
		gp_update_option( 'wporg_projects_last_updated', time() );
	}

	function before_request( $class_name, $last_method_called ) {
		if ( 'GP_Route_Translation' === $class_name && 'import_translations_post' === $last_method_called ) {
			add_filter( 'enable_propagate_translations_across_projects', '__return_false' );
		}
	}

	function before_login_form() {
		echo '<span class="secondary">' . __( 'Log in with your wordpress.org forums account. If you don&#8217;t have one, you can <a href="https://wordpress.org/support/register.php">register at the forums.</a>' ) . '</span>';
	}

	function gp_url_profile( $url, $nicename ) {
		return 'https://profiles.wordpress.org/' . $nicename;
	}

	function init() {
		GP::$router->remove( '/profile/(.+?)' );
	}

	/**
	 * Natural sorting for sub projects, and attach whitelisted meta
	 */
	function projects( $sub_projects, $parent_id ) {
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
GP::$plugins->wporg_specifics = new GP_WPorg_Specifics;
