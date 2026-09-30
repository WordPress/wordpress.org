<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the id=main div and all content after
 *
 * @package p2-breathe
 */
?>

		</div><!-- #main -->
	</div><!-- #page -->

<?php

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() renders the block markup defined here; escaping it would print the markup.
echo do_blocks( '<!-- wp:wporg/global-footer /-->' );
