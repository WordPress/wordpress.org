<?php
/**
 * The Footer for our theme.
 *
 * @package WPBBP
 */
?>

	</div><!-- #content -->
</div><!-- #page -->

<?php

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- do_blocks() renders the block markup defined here; escaping it would print the markup.
echo do_blocks( '<!-- wp:wporg/global-footer /-->' );
