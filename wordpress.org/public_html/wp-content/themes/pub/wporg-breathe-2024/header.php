<?php

\WordPressdotorg\skip_to( '#primary' );

echo do_blocks( '<!-- wp:wporg/global-header /-->' );

echo do_blocks( '<!-- wp:pattern {"slug":"wporg-breathe/local-nav"} /-->' );

do_action( 'wporg_breathe_after_header' );

?>

<div id="page" class="hfeed site">
	<?php do_action( 'before' ); ?>

	<div id="main" class="site-main clear">
