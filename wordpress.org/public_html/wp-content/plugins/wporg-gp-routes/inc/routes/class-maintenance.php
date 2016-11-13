<?php

namespace WordPressdotorg\GlotPress\Routes\Routes;

use GP_Route;

/**
 * Maintenance Route Class.
 */
class Maintenance extends GP_Route {

	public function show_maintenance_message() {
		wp_die( 'Briefly unavailable for scheduled maintenance. Check back in ~30 minutes.', 'Maintenance', [ 'response' => 503 ] );
	}
}
