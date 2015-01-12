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
		$this->add_filter( 'routes' );
		$this->add_filter( 'projects', array( 'args' => 2 ) );
	}

	function before_login_form() {
		echo '<span class="secondary">' . __( 'Log in with your wordpress.org forums account. If you don&#8217;t have one, you can <a href="http://wordpress.org/support/register.php">register at the forums.</a>' ) . '</span>';
	}

	function gp_url_profile( $url, $nicename ) {
		return 'https://profiles.wordpress.org/' . $nicename;
	}

	function routes( $routes ) {
		unset( $routes['get:/profile/(.+?)'] );
		return $routes;
	}

	function projects( $sub_projects, $parent_id ) {
		if ( 1 != $parent_id && 13 != $parent_id ) { // 1 = WordPress, 13 = BuddyPress
			return $sub_projects;
		}

		/*
		 * Natural sorting for sub projects.
		 *
		 * 3.0.x
		 * 4.0.x
		 * 3.1.x
		 * 4.1.x
		 *  =>
		 * 4.1.x
		 * 4.0.x
		 * 3.1.x
		 * 3.0.x
		 */
		usort( $sub_projects, function( $a, $b ) {
			return - strcasecmp( $a->name, $b->name );
		});

		return $sub_projects;
	}
}
GP::$plugins->wporg_specifics = new GP_WPorg_Specifics;
