<?php
/**
 * Redirector Route Class.
 *
 * Provides redirection routes.
 */
class GP_WPorg_Route_Redirector extends GP_Route {

	function redirect_languages( $path = '' ) {
		if ( empty( $path ) ) {
			$this->redirect( '/' );
		} else {
			$this->redirect( "/locale/$path" );
		}
	}
}
