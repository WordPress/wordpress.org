<?php

$private_mu_plugins = glob( WP_CONTENT_DIR . '/mu-plugins-private/*.php' );
if ( is_array( $private_mu_plugins ) ) {
	foreach ( $private_mu_plugins as $plugin ) {
		if ( is_file( $plugin ) ) {
			require_once( $plugin );
		}
	}
}
