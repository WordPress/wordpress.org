<?php
/**
 * Index Route Class.
 *
 * Provides the route for translate.wordpress.org/.
 */
class GP_WPorg_Route_Index extends GP_Route {

	public function get_index() {
		$this->redirect( gp_url( '/languages' ) );
	}
}
