<?php

namespace Wporg\TranslationEvents\Routes;

use GP_Route;
use Wporg\TranslationEvents\Templates;

abstract class Route extends GP_Route {
	public function tmpl( $template, $args = array(), $honor_api = true ) {
		$this->set_notices_and_errors();
		$this->header( 'Content-Type: text/html; charset=utf-8' );

		Templates::render( $template, $args );
	}
}
