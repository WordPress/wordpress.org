<?php
/**
 * The header for our theme.
 *
 * @package wporg-themes
 */


$GLOBALS['pagetitle'] = wp_title( '&laquo;', false, 'right' );
require WPORGPATH . 'header.php';
?>

<div id="headline">
	<div class="wrapper">
		<h2 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h2>
	</div>
</div>
