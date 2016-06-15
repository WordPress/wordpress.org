<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

$GLOBALS['pagetitle'] = __( 'Plugin Directory &mdash; Free WordPress Plugins', 'wporg-plugins' );
$description = get_bloginfo( 'description', 'display' );

require WPORGPATH . 'header.php';
?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'wporg-plugins' ); ?></a>

	<header id="masthead" class="site-header" role="banner">
		<div class="site-branding">
			<?php if ( is_front_page() && is_home() ) : ?>
				<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
			<?php else : ?>
				<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>
			<?php endif; ?>

			<?php if ( is_home() || is_search() ) :
				if ( $description || is_customize_preview() ) : ?>
				<p class="site-description"><?php echo $description; /* WPCS: xss ok. */ ?></p>
					<?php endif; ?>
				<?php get_search_form(); ?>
			<?php else : ?>
				<nav id="site-navigation" class="main-navigation" role="navigation">
					<button class="menu-toggle dashicons dashicons-arrow-down-alt2" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Primary Menu', 'wporg-plugins' ); ?>"></button>
					<div id="primary-menu" class="menu">
						<ul>
							<li class="page_item"><a href="<?php echo esc_url( home_url( 'browse/favorites' ) ); ?>"><?php esc_html_e( 'Favorites', 'wporg-plugins' ); ?></a></li>
							<li class="page_item"><a href="<?php echo esc_url( home_url( 'browse/beta' ) ); ?>"><?php esc_html_e( 'Beta Testing', 'wporg-plugins' ); ?></a></li>
							<li class="page_item"><a href="<?php echo esc_url( home_url( 'about' ) ); ?>"><?php esc_html_e( 'Developers', 'wporg-plugins' ); ?></a></li>
							<li><?php get_search_form(); ?></li>
						</ul>
					</div>
				</nav><!-- #site-navigation -->
			<?php endif; ?>
		</div><!-- .site-branding -->
	</header><!-- #masthead -->

	<div id="content" class="site-content">
