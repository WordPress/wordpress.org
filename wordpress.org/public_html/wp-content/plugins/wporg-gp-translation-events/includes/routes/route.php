<?php

namespace Wporg\TranslationEvents\Routes;

use GP_Route;

abstract class Route extends GP_Route {
	public function __construct() {
		parent::__construct();
		$this->template_path = __DIR__ . '/../../templates/';
	}
}
