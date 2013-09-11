<?php
$GLOBALS['pagetitle'] = wp_title( '&laquo;', false, 'right' ) . ' ' . get_bloginfo( 'name' );
require( WPORGPATH . 'header.php' );
?>

<div id="headline">
		<div class="wrapper">
				<h2><a href="<?php echo esc_url( home_url() ); ?>"><?php bloginfo( 'name' ); ?></a></h2>
		</div>
</div>
