<?php

/**
 * Plugin Name: bbPress Network Templates
 * Description: Customized bbPress templates that all sites across the network should use.
 * Author:      WordCamp Central
 * Author URI:  http://wordcamp.org
 * Version:     0.1
 */

function bbpnt_get_templates_dir() {
    return __DIR__ . '/templates/';
}

function bbpnt_register_template_dir() {
    if ( function_exists( 'bbp_register_template_stack' ) ) {
        bbp_register_template_stack( 'bbpnt_get_templates_dir', 9 );
    }
}
add_filter( 'plugins_loaded', 'bbpnt_register_template_dir' );
