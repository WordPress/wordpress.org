<?php
global $pagetitle;
$pagetitle = wp_title( '&laquo;', false, 'right' ) . ' ' . get_bloginfo( 'name' );
wp_enqueue_style( 'wporg-learn', get_bloginfo( 'stylesheet_url' ), array(), 2, 'screen' );
wp_enqueue_style( 'wporg-learn-fonts', '//fonts.googleapis.com/css?family=Roboto+Condensed:700', array(), 1, 'screen' );
wp_enqueue_style( 'buttons' );
require WPORGPATH . 'header.php';
?>
<div id="headline">
        <div class="wrapper">
                <h2><a href="<?php echo home_url( '/' ); ?>"><?php bloginfo( 'name' ); ?></a></h2>
        </div>
</div>

<div id="header2">
<?php do_action( 'before' ); ?>
</div>

<div id="wrapper" class="wp-core-ui">
