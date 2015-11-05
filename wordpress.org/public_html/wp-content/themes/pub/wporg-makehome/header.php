<?php
$GLOBALS['pagetitle'] = wp_get_document_title();
require( WPORGPATH . 'header.php' );
?>

<div id="headline">
		<div class="wrapper">
				<h2><a href="<?php echo esc_url( home_url() ); ?>"><?php bloginfo( 'name' ); ?></a></h2>
		</div>
</div>
