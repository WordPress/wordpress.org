<?php

defined( 'WPINC' ) or die();

// mu-plugins in sub-directories
require_once( __DIR__ . '/wp-cli-commands/bootstrap.php' );
require_once( __DIR__ . '/camptix-tweaks/camptix-tweaks.php' );

// Private mu-plugins
$private_mu_plugins = glob( WP_CONTENT_DIR . '/mu-plugins-private/*.php' );
if ( is_array( $private_mu_plugins ) ) {
	foreach ( $private_mu_plugins as $plugin ) {
		if ( is_file( $plugin ) ) {
			require_once( $plugin );
		}
	}
}
