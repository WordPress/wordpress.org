<?php
/**
 * Plugin Name: Namespaced
 */
require __DIR__ . '/src/Abilities.php';
add_action( 'wp_abilities_api_init', array( \Acme\Abilities\Abilities::class, 'register' ) );
