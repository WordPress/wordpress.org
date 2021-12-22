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

if ( FEATURE_2021_GLOBAL_HEADER_FOOTER ) {
	echo do_blocks( '<!-- wp:wporg/global-footer /-->' );
} else {
	require WPORGPATH . 'footer.php';
}
