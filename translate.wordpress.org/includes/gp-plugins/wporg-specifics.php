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
	 * Natural sorting for sub projects.
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

		return $sub_projects;
	}
}
GP::$plugins->wporg_specifics = new GP_WPorg_Specifics;
