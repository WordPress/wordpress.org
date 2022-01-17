<?php

\WordPressdotorg\skip_to( '#headline' );

if ( FEATURE_2021_GLOBAL_HEADER_FOOTER ) {
	echo do_blocks( '<!-- wp:wporg/global-header /-->' );
} else {
	require( WPORGPATH . 'header.php' );
}

?>

<div id="headline">
		<div class="wrapper">
				<h2><a href="<?php echo esc_url( home_url() ); ?>"><?php bloginfo( 'name' ); ?></a></h2>
		</div>
</div>
