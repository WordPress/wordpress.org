<?php
/**
 * Redirector Route Class.
 *
 * Provides redirection routes.
 */
class WPorg_GP_Route_Redirector extends GP_Route {

	public function redirect_languages( $path = '' ) {
		if ( empty( $path ) ) {
			$this->redirect( '/' );
		} else {
			$this->redirect( "/locale/$path" );
		}
	}

	public function redirect_index() {
		$this->redirect( '/' );
	}
}
