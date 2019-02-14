<?php
/**
 * The Header template for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\Theme;

$GLOBALS['wporg_global_header_options'] = array(
	'in_wrapper' => '<a class="skip-link screen-reader-text" href="#content">' . esc_html( 'Skip to content', 'wporg' ) . '</a>',
);
get_template_part( 'header', 'wporg' );
?>
<div id="page" class="site">
	<div id="content" class="site-content row gutters">
