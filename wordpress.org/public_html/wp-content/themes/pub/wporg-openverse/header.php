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

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() renders the block markup defined here; escaping it would print the markup.
echo do_blocks( '<!-- wp:wporg/global-header /-->' );

?>
<div id="page" class="site">
	<div id="content" class="site-content">
