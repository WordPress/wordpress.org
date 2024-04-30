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

		<?php

if ( ! is_front_page() && ( is_archive() || is_search() ) ) {
	//echo esc_html( $wp_query->request );
	echo '<style>
		body {
			/* TODO: Seems whatever is using this should have a default 10px applied? */
			--wp--preset--spacing--10: 10px;
		}
		.wporg-plugins__filters {
			--wporg--oldtheme--primary-color: #2371a6;
			--wp--custom--button--color--text: #ffffff;
			--wp--custom--button--hover--color--text: #ffffff;
			--wp--custom--wporg-query-filters--border--color: #e5e5e5;
			--wp--custom--wporg-query-filters--option--active--color--background: #f0f0f0;
			--wp--custom--wporg-query-filters--toggle--active--color--background: var(--wporg--oldtheme--primary-color);
			--wp--custom--wporg-query-filters--toggle--hover--color--text: var(--wporg--oldtheme--primary-color);
			--wp--custom--button--color--background: var(--wporg--oldtheme--primary-color);
			--wp--custom--button--hover--color--background: var(--wporg--oldtheme--primary-color);
		}
		.wporg-plugins__filters .wporg-query-filter__modal-actions input.wporg-query-filter__modal-action-clear {
			color: var(--wporg--oldtheme--primary-color);
		}
		.wporg-plugins__filters .wp-block-search__inside-wrapper {
			border: none;
		}
	</style>';

	echo do_blocks( '<!-- wp:group {"align":"wide","className":"wporg-filter-bar wporg-plugins__filters wporg-plugins__filters__no-count","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
		<div class="wp-block-group alignwide wporg-filter-bar wporg-plugins__filters wporg-plugins__filters__no-count">
			<!-- wp:group {"className":"wporg-plugins__filters__search","layout":{"type":"flex","flexWrap":"wrap"}} -->
			<div class="wp-block-group wporg-plugins__filters__search">
				<!-- wp:search {"showLabel":false,"placeholder":"' . esc_attr__( 'Search plugins', 'wporg-plugins' ) . '","width":100,"widthUnit":"%","buttonText":"' . esc_attr__( 'Search plugins', 'wporg-plugins' ) . '","buttonPosition":"button-inside","buttonUseIcon":true,"className":"is-style-secondary-search-control"} /-->
				<!-- wp:wporg/query-total /-->
			</div> <!-- /wp:group -->

			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"className":"wporg-query-filters","layout":{"type":"flex","flexWrap":"nowrap"}} -->
			<div class="wp-block-group wporg-query-filters">
				<!-- wp:wporg/query-filter {"key":"plugin_category"} /-->
				<!-- wp:wporg/query-filter {"key":"business_model","multiple":false} /-->
				<!-- wp:wporg/query-filter {"key":"sort","multiple":false} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->'
	);
}
