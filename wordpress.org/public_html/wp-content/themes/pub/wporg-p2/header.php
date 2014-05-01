<?php
global $pagetitle;
$pagetitle = wp_title( '&laquo;', false, 'right' );
require WPORGPATH . 'header.php';
?>
<div id="headline">
        <div class="wrapper">
                <h2><a href="<?php echo home_url( '/' ); ?>"><?php bloginfo( 'name' ); ?></a></h2>
				<?php wp_nav_menu( array( 'theme_location' => 'wporg_header_subsite_nav', 'fallback_cb' => '__return_false' ) ); ?>
		</div>
</div>

<div id="header2">
<?php do_action( 'before' ); ?>
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

<?php is_home() && ! is_paged() && get_template_part( 'make-intro' ); ?>

<div id="wrapper">

