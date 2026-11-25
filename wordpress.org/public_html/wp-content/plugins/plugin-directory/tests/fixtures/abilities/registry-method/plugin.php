<?php
/**
 * Plugin Name: Registry Method
 */
add_action( 'wp_abilities_api_init', function () {
	WP_Abilities_Registry::get_instance()->register( 'registry/direct', array( 'label' => 'Direct registry call' ) );
} );
