<?php
/**
 * Template Name: Update PHP
 *
 * @package WPOrg
 * @subpackage Theme
 */

/*
Use the default template for pages. This specific template is only used to indicate that the content needs to be
manually injected, which happens in `helphub-update-php-strings.php`
 */
if ( 'en_US' == get_locale() ) {
	// Helphub will be active, so we need it's sidebar
	require get_stylesheet_directory() . '/page.php';
} else {
	// Helphub may be active, but we're loading in a non-en_US locale so shouldn't display the EN sidebar
	require get_stylesheet_directory() . '/page-full-width.php';
}
