<?php

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use WP_CLI;
use WP_CLI_Command;

class Stats_Print extends WP_CLI_Command  {

	public function __invoke( $args, $assoc_args ) {
		$stats = new Stats();
		$stats( true );
	}
}