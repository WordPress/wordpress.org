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

if ( FEATURE_2021_GLOBAL_HEADER_FOOTER ) {
	echo do_blocks( '<!-- wp:wporg/global-footer /-->' );
} else {
	require WPORGPATH . 'footer.php';
}
