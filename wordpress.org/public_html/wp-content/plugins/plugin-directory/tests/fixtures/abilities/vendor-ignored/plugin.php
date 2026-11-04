<?php
/**
 * Plugin Name: Vendor Ignored
 */
require __DIR__ . '/vendor/autoload.php';
wp_register_ability( 'mine/own-ability', array( 'label' => 'Mine' ) );
