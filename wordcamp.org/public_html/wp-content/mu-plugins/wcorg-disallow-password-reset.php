<?php
/**
 * Plugin Name: Disallow Password Change/Reset
 * Plugin Description: Disallows password change and reset across the WordCamp.org network
 */

add_filter( 'allow_password_reset', '__return_false' );
add_filter( 'show_password_fields', '__return_false' );