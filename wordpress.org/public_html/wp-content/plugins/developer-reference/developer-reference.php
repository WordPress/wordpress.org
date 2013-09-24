<?php
/**
 * Plugin Name: WP.org Developer Reference
 * Version: 1.0
 */

add_action( 'plugins_loaded', 'wporg_ref_plugins_loaded' );
function wporg_ref_plugins_loaded() {
	if ( ! function_exists( 'wpfuncref_return_type' ) )
		require __DIR__ . '/plugin.php';
}
