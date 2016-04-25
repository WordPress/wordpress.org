<?php

namespace WordPressdotorg\GlotPress\Routes\Routes;

use GP_Route;

/**
 * Redirector Route Class.
 *
 * Provides redirection routes.
 */
class Redirector extends GP_Route {

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
