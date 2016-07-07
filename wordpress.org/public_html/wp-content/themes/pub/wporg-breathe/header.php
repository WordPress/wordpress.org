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
	</div>

	<nav id="site-navigation" class="navigation-main clear" role="navigation">
		<div class="screen-reader-text skip-link"><a href="#content" title="<?php _e( 'Skip to content', 'p2-breathe' ); ?>"><?php _e( 'Skip to content', 'p2-breathe' ); ?></a></div>

		<?php wp_nav_menu( array( 'theme_location' => 'primary', 'fallback_cb' => false ) ); ?>
	</nav><!-- .navigation-main -->
</header><!-- .site-header -->

<?php
$welcome = get_page_by_path( 'welcome' );

$cookie = 'welcome-' . get_current_blog_id();

$hash = isset( $_COOKIE[ $cookie ] ) ? $_COOKIE[ $cookie ] : '';

$content_hash = $welcome ? md5( $welcome->post_content ) : '';

if ( $welcome && ( empty( $hash ) || $content_hash !== $hash ) ) {
	$columns = preg_split( '|<hr\s*/?>|', $welcome->post_content );
	if ( count( $columns ) === 2 ) {
		$welcome->post_content = "<div class='first-column'>\n\n{$columns[0]}</div><div class='second-column'>\n\n{$columns[1]}</div>";
	}
	setup_postdata( $welcome );
?>
<div class="make-welcome-wrapper">
	<span id="make-welcome-hide" class="dashicons dashicons-no" data-hash="<?php echo $content_hash; ?>" data-cookie="<?php echo $cookie; ?>" title="<?php _e( 'Hide this message', 'p2-breathe' ); ?>"></span>
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
