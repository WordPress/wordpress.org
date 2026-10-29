<?php
/**
 * The Header template for pages in our theme.
 *
 * Displays all of the <head> section and the wp.org header.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\Theme;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() renders the block markup defined here; escaping it would print the markup.
echo do_blocks( '<!-- wp:wporg/global-header /-->' );
