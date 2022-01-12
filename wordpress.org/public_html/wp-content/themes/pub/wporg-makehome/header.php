<?php

if ( FEATURE_2021_GLOBAL_HEADER_FOOTER ) {
	echo do_blocks( '<!-- wp:wporg/global-header /-->' );
} else {
	global $wporg_global_header_options;
	if ( !isset( $wporg_global_header_options['in_wrapper'] ) ) {
		$wporg_global_header_options['in_wrapper'] = '';
	}
	$wporg_global_header_options['in_wrapper'] .= '<a class="skip-link screen-reader-text" href="#headline">' . esc_html( 'Skip to content', 'make-wporg' ) . '</a>';
	require( WPORGPATH . 'header.php' );
}

?>

<div id="headline">
		<div class="wrapper">
				<h2><a href="<?php echo esc_url( home_url() ); ?>"><?php bloginfo( 'name' ); ?></a></h2>
		</div>
</div>
