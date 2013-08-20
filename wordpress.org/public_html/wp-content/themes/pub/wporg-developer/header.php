<?php
global $pagetitle;
$pagetitle = wp_title( '&laquo;', false, 'right' ) . ' ' . get_bloginfo( 'name' );
wp_enqueue_style( 'wporg-developer', get_bloginfo( 'stylesheet_url' ), array(), 1, 'screen' );
require WPORGPATH . 'header.php';
?>
<div id="headline">
        <div class="wrapper">
                <h2><a href="<?php echo home_url( '/' ); ?>"><?php bloginfo( 'name' ); ?></a></h2>
        </div>
</div>

<div id="wrapper">
