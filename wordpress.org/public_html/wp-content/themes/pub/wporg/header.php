<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\Theme;

require WPORGPATH . 'header.php';
?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'wporg' ); ?></a>

	<div id="content" class="site-content">
		<header id="masthead" class="site-header" role="banner">
			<div class="site-branding">
				<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>

				<nav id="site-navigation" class="main-navigation" role="navigation">
					<button class="menu-toggle dashicons dashicons-arrow-down-alt2" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Primary Menu', 'wporg' ); ?>"></button>
					<?php wp_nav_menu( array( 'theme_location' => 'primary', 'menu_id' => 'primary-menu', 'depth' => 1 ) ); ?>
				</nav><!-- #site-navigation -->
			</div><!-- .site-branding -->
		</header><!-- #masthead -->
