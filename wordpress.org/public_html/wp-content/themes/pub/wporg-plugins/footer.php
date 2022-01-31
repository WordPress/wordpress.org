<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

?>

	</div><!-- #content -->
</div><!-- #page -->
<?php

echo do_blocks( '<!-- wp:wporg/global-footer /-->' );
