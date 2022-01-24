<?php

// Used within the global footer
__('Code is Poetry.', 'rosetta');

if ( FEATURE_2021_GLOBAL_HEADER_FOOTER ) {
	echo do_blocks( '<!-- wp:wporg/global-footer /-->' );
} else {
	require WPORGPATH . 'footer.php';
}
