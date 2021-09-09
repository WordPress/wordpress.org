<?php
namespace WordPressdotorg\Theme_Preview\Namespace_Transients;
/**
 * Plugin Name: Namespace transients
 * Description: Namespaces some transients that need to differ between themes.
 */

function namespace_transients() {
	$transients_to_key = array(
		// Gutenberg uses the global_styles transient, but doesn't vary by theme.
		'global_styles',
		// Gutenberg also has it's own global styles transient
		'gutenberg_global_styles',
	);
	foreach ( $transients_to_key as $transient ) {
		add_filter( "pre_transient_{$transient}",     __NAMESPACE__ . '\get', 10, 2 );
		add_filter( "pre_set_transient_{$transient}", __NAMESPACE__ . '\set', 10, 3 );
	}
}
namespace_transients();

/**
 * Namespace a transient to be unique per theme.
 */
function get( $value, $transient ) {
	return get_transient( get_option( 'stylesheet' ) . '_' . $transient ) ?: '';
}

/**
 * Namespace a transient set to be unique per theme.
 */
function set( $value, $expiration, $transient ) {
	set_transient( get_option( 'stylesheet' ) . '_' . $transient, $value, $expiration );

	// Return false to cache false in the original transient, can't avoid it being set.
	return false;
}