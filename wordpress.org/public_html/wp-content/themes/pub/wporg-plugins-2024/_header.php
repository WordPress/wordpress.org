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

$local_nav_items = array(
	'' => __( 'All', 'wporg-plugins' ),
	'community' => __( 'Community', 'wporg-plugins' ),
	'commercial' => __( 'Commercial', 'wporg-plugins' ),
);

global $wp_query;
$is_beta = 'beta' === $wp_query->get( 'browse' );
$is_favs = 'favorites' === $wp_query->get( 'browse' );
// The filter bar should not be shown on:
// - singular: not relevant on pages or individual plugins.
// - beta: likely unnecessary, these are probably all "community".
// - favorites: not necessary.
$show_filter_bar = ! ( is_singular() || $is_beta || $is_favs );

echo do_blocks( '<!-- wp:wporg/global-header /-->' ); // phpcs:ignore

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
							foreach ( $menu_items as $path => $text ) : // phpcs:ignore
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

		<?php if ( $show_filter_bar ) : ?>
		<div class="wporg-filter-bar">
			<nav class="wporg-filter-bar__navigation" aria-label="<?php esc_html_e( 'Plugin filters', 'wporg-plugins' ); ?>">
				<ul>
				<?php
				foreach ( $local_nav_items as $slug => $label ) {
					$class = '';
					if (
						// URL contains this filter.
						( $slug === ( $_GET['plugin_business_model'] ?? false ) ) ||
						// Set the All item active if no business model is selected.
						( ! $slug && empty( $_GET['plugin_business_model'] ) )
					) {
						$class = 'is-active';
					}

					if ( $slug ) {
						$url = add_query_arg( array( 'plugin_business_model' => $slug ) );
					} else {
						$url = remove_query_arg( 'plugin_business_model' );
					}

					// Reset pagination.
					$url = remove_query_arg( 'paged', $url );
					$url = preg_replace( '!/page/\d+/?!i', '/', $url );

					printf(
						'<li class="page_item"><a class="%1$s" href="%2$s">%3$s</a></li>',
						esc_attr( $class ),
						esc_url( $url ),
						esc_html( $label )
					);
				}
				?>
				</ul>
			</nav>
		</div>
		<?php endif; ?>
