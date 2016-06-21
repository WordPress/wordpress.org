<?php

$GLOBALS['pagetitle'] = get_bloginfo( 'name', 'display' );
require WPORGPATH . 'header.php';
?>
<div id="page" class="hfeed site">
	<?php do_action( 'before' ); ?>
	<header id="masthead" class="site-header" role="banner">
		<div class="hgroup">
			<?php
			$header_image = get_header_image();
			if ( ! empty( $header_image ) ) :
				?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home">
					<img src="<?php echo esc_url( add_query_arg( 'w', 276, $header_image ) ); ?>" class="header-image" alt="" />
				</a>
			<?php endif; ?>
			<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
			<h2 class="site-description"><?php bloginfo( 'description' ); ?></h2>
		</div><!-- .hgroup -->
		<nav id="site-navigation" class="navigation-main" role="navigation">
			<div class="screen-reader-text skip-link"><a href="#content" title="<?php esc_attr_e( 'Skip to content', 'p2-breathe' ); ?>"><?php _e( 'Skip to content', 'p2-breathe' ); ?></a></div>

			<?php wp_nav_menu( array( 'theme_location' => 'primary', 'fallback_cb' => false ) ); ?>
		</nav><!-- .navigation-main -->
	</header><!-- .site-header -->

	<div id="main" class="site-main">