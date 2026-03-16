<?php
/**
 * Plugin Name: Theme Directory Path Registration
 * Description: Registers the wporg-theme-directory repo themes path so WordPress discovers the theme.
 */

register_theme_directory( ABSPATH . 'wp-content/wporg-theme-directory/source/wp-content/themes' );
