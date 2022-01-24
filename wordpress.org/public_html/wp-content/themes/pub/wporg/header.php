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

\WordPressdotorg\skip_to( '#content' );

get_template_part( 'header', 'wporg' );
?>

<div id="page" class="site">
	<div id="content" class="site-content row gutters">
