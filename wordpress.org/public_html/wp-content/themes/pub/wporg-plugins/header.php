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

\WordPressdotorg\skip_to( '#main' );

$menu_items = array(
	'/browse/favorites/' => __( 'My Favorites', 'wporg-plugins' ),
	'/browse/beta/'      => __( 'Beta Testing', 'wporg-plugins' ),
	'/developers/'       => __( 'Developers', 'wporg-plugins' ),
);

if ( FEATURE_2021_GLOBAL_HEADER_FOOTER ) {
	echo do_blocks( '<!-- wp:wporg/global-header /-->' );
} else {
	require WPORGPATH . 'header.php';
}

?>
<div id="page" class="site">
	<div id="content" class="site-content">
		<header id="masthead" class="site-header <?php echo is_home() ? 'home' : ''; ?>" role="banner">
			<div class="site-branding">
				<?php if ( is_home() ) : ?>
					<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Plugins', 'Site title', 'wporg-plugins' ); ?></a></h1>

					<p class="site-description">
						<?php
						$plugin_count = wp_count_posts( 'plugin' )->publish;
						printf(
							/* Translators: Total number of plugins. */
							esc_html( _n( 'Extend your WordPress experience! Browse %s free plugin.', 'Extend your WordPress experience! Browse %s free plugins.', $plugin_count, 'wporg-plugins' ) ),
							esc_html( number_format_i18n( $plugin_count ) )
						);
						?>
					</p>
					<?php get_search_form(); ?>
				<?php else : ?>
					<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Plugins', 'Site title', 'wporg-plugins' ); ?></a></p>

					<nav id="site-navigation" class="main-navigation" role="navigation">
						<button class="menu-toggle dashicons dashicons-arrow-down-alt2" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Primary Menu', 'wporg-plugins' ); ?>"></button>
						<div id="primary-menu" class="menu">
							<ul>
								<?php
								foreach ( $menu_items as $path => $text ) :
									$class = false !== strpos( $_SERVER['REQUEST_URI'], $path ) ? 'active' : ''; // phpcs:ignore
								?>
								<li class="page_item"><a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( home_url( $path ) ); ?>"><?php echo esc_html( $text ); ?></a></li>
								<?php endforeach; ?>
								<li><?php get_search_form(); ?></li>
							</ul>
						</div>
					</nav><!-- #site-navigation -->
				<?php endif; ?>
			</div><!-- .site-branding -->
		</header><!-- #masthead -->
