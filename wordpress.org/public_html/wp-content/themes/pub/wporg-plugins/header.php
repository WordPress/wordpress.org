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

$menu_items = array(
	'/browse/favorites/' => __( 'Favorites', 'wporg-plugins' ),
	'/browse/beta/'      => __( 'Beta Testing', 'wporg-plugins' ),
	'/about/'            => __( 'Developers', 'wporg-plugins' ),
);

$GLOBALS['pagetitle'] = __( 'Plugin Directory &mdash; Free WordPress Plugins', 'wporg-plugins' );
require WPORGPATH . 'header.php';
?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'wporg-plugins' ); ?></a>

	<header id="masthead" class="site-header" role="banner">
		<div class="site-branding">
			<?php if ( is_front_page() && is_home() ) : ?>
				<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php _ex( 'Plugins','Site title', 'wporg-plugins' ); ?></a></h1>
			<?php else : ?>
				<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php _ex( 'Plugins','Site title', 'wporg-plugins' ); ?></a></p>
			<?php endif; ?>

			<?php if ( is_home() ) : ?>
				<p class="site-description"><?php _e( 'Plugins extend and expand the functionality of WordPress.', 'wporg-plugins' ); ?></p>
				<?php get_search_form(); ?>
			<?php else : ?>
				<nav id="site-navigation" class="main-navigation" role="navigation">
					<button class="menu-toggle dashicons dashicons-arrow-down-alt2" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Primary Menu', 'wporg-plugins' ); ?>"></button>
					<div id="primary-menu" class="menu">
						<ul>
							<?php
							foreach ( $menu_items as $path => $text ) :
								$class = false !== strpos( $_SERVER['REQUEST_URI'], $path ) ? 'class="active" ' : '';
							?>
							<li class="page_item"><a <?php echo $class; ?>href="<?php echo esc_url( home_url( $path ) ); ?>"><?php echo esc_html( $text ); ?></a></li>
							<?php endforeach; ?>
							<li><?php get_search_form(); ?></li>
						</ul>
					</div>
				</nav><!-- #site-navigation -->
			<?php endif; ?>
		</div><!-- .site-branding -->
	</header><!-- #masthead -->

	<div id="content" class="site-content">
