<?php
/**
 * The Header template for pages in our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\MainTheme;

global $menu_items;

get_template_part( 'header', 'wporg' );
?>
<div id="page" class="site">
	<div id="content" class="site-content row gutters">
		<header id="masthead" class="site-header col-12" role="banner">
			<div class="site-branding">
				<p class="site-title"><a href="<?php echo esc_url( get_permalink( get_post()->post_parent ) ); ?>" rel="bookmark"><?php echo get_the_title( get_post()->post_parent ); ?></a></p>

				<?php if ( ! empty( $menu_items ) ) : ?>
				<nav id="site-navigation" class="main-navigation" role="navigation">
					<button class="menu-toggle dashicons dashicons-arrow-down-alt2" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Primary Menu', 'wporg' ); ?>"></button>
					<div id="primary-menu" class="menu">
						<ul>
							<?php
							$active_item = false;
							// The last found menu item that patches is the one that's shown as active.
							foreach ( $menu_items as $path => $text ) {
								// phpcs:ignore WordPress.VIP
								if ( false !== strpos( $_SERVER['REQUEST_URI'], $path ) ) {
									$active_item = $path;
								}
							}

							foreach ( $menu_items as $path => $text ) :
								$class = ( $path === $active_item ) ? 'active' : ''; // phpcs:ignore
								?>
								<li class="page_item"><a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( trailingslashit( home_url( $path ) ) ); ?>"><?php echo esc_html( $text ); ?></a></li>
							<?php endforeach; ?>
						</ul>
					</div>
				</nav><!-- #site-navigation -->
				<?php endif; ?>
			</div><!-- .site-branding -->
		</header><!-- #masthead -->
