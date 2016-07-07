<?php

$GLOBALS['pagetitle'] = get_bloginfo( 'name', 'display' );
require WPORGPATH . 'header.php';
?>
<header id="masthead" class="site-header" role="banner">
	<div class="site-branding">
		<?php if ( is_front_page() && is_home() ) : ?>
			<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
		<?php else : ?>
			<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>
		<?php endif; ?>

		<nav id="site-navigation" class="navigation-main clear" role="navigation">
			<div class="screen-reader-text skip-link"><a href="#content" title="<?php _e( 'Skip to content', 'p2-breathe' ); ?>"><?php _e( 'Skip to content', 'p2-breathe' ); ?></a></div>

			<?php wp_nav_menu( array( 'theme_location' => 'primary', 'fallback_cb' => false ) ); ?>
		</nav><!-- .navigation-main -->
	</div>
</header><!-- .site-header -->

<?php
$welcome = get_page_by_path( 'welcome' );

$cookie = 'welcome-' . get_current_blog_id();

$hash = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : '';

if ( $welcome && md5( $welcome->post_content ) !== $hash ) {
	setup_postdata( $welcome );
?>
<div class="make-welcome-wrapper">
	<span id="make-welcome-hide" class="dashicons dashicons-no" data-hash="<?php echo md5( $welcome->post_content ); ?>" data-cookie="<?php echo $cookie; ?>" title="<?php _e( 'Hide this message', 'p2-breathe' ); ?>"></span>
	<div class="make-welcome">
		<?php
		the_content();
		edit_post_link( __( 'Edit', 'o2' ), '<p class="make-welcome-edit">', '</p>', $welcome->ID );
		?>
	</div>
</div>
<?php
	wp_reset_postdata();
}
?>

<div id="page" class="hfeed site">
	<?php do_action( 'before' ); ?>

	<div id="main" class="site-main clear">
