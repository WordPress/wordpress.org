<?php
global $pagetitle;
$pagetitle = wp_title( '&laquo;', false, 'right' ) . ' ' . get_bloginfo( 'name' );
require WPORGPATH . 'header.php';
?>
<div id="headline">
        <div class="wrapper">
                <h2><a href="<?php echo home_url( '/' ); ?>"><?php bloginfo( 'name' ); ?></a></h2>
        </div>
</div>

<div id="header2">
<?php do_action( 'before' ); ?>
<!--
	<div class="sleeve">
		<h1><a href="<?php echo home_url( '/' ); ?>"><?php bloginfo( 'name' ); ?></a></h1>
		<?php if ( get_bloginfo( 'description' ) ) : ?>
			<small><?php bloginfo( 'description' ); ?></small>
		<?php endif; ?>
		<a class="secondary" href="<?php echo home_url( '/' ); ?>"></a>

		<?php if ( current_user_can( 'publish_posts' ) ) : ?>
			<a href="" id="mobile-post-button" style="display: none;"><?php _e( 'Post', 'p2' ) ?></a>
		<?php endif; ?>
	</div>
-->
	<?php if ( has_nav_menu( 'primary' ) ) : ?>
	<div role="navigation" class="site-navigation main-navigation">
		<h1 class="assistive-text"><?php _e( 'Menu', 'p2' ); ?></h1>
		<div class="assistive-text skip-link"><a href="#main" title="<?php esc_attr_e( 'Skip to content', 'p2' ); ?>"><?php _e( 'Skip to content', 'p2' ); ?></a></div>

		<?php wp_nav_menu( array(
			'theme_location' => 'primary',
			'fallback_cb'    => '__return_false',
		) ); ?>
	</div>
	<div style="clear:both;"></div>
	<?php endif; ?>
</div>

<div id="wrapper">

	<?php get_sidebar(); ?>
