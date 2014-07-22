<?php
/**
 * Plugin Name: Disable Admin Pointers
 */

add_action( 'admin_init', 'wcorg_disable_admin_pointers' );
function wcorg_disable_admin_pointers() {
	remove_action( 'admin_enqueue_scripts', array( 'WP_Internal_Pointers', 'enqueue_scripts' ) );
}