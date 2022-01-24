<?php
/**
 * The Header template for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Openverse\Theme
 */

namespace WordPressdotorg\Openverse\Theme;

\WordPressdotorg\skip_to( '#content' );

if ( FEATURE_2021_GLOBAL_HEADER_FOOTER ) {
	echo do_blocks( '<!-- wp:wporg/global-header /-->' );
} else {
	get_template_part( 'header', 'wporg' );
}

?>
<div id="page" class="site">
	<div id="content" class="site-content">
