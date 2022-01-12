	</div><!-- gp-content -->

<?php

if ( FEATURE_2021_GLOBAL_HEADER_FOOTER ) {
	echo do_blocks( '<!-- wp:wporg/global-footer /-->' );

	// Intentionally calling this in addition to `wp_footer()` from the block. See `header.php` for details.
	gp_footer();

} else {
	require WPORGPATH . 'footer.php';
}
