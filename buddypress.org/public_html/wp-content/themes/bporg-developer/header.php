<?php
/**
 * The Header for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @package bporg-developer
 * @since 1.0.0
 */
?>
<!DOCTYPE html><html>

<?php get_template_part( 'template-parts/header', 'head' ); ?>

<body id="wordpress-org" <?php body_class(); ?>>

<?php wp_body_open(); ?>

<?php get_template_part( 'template-parts/header', 'accessibility' ); ?>

<div id="header">
	<div id="header-inner">

		<?php get_template_part( 'template-parts/header', 'nav' ); ?>

		<div id="network-title">
			<a href="https://buddypress.org"><?php bloginfo( 'name' ); ?></a>
		</div>

	</div>
	<div style="clear:both"></div>
</div>

<header id="masthead" class="site-header<?php if ( is_front_page() ) { echo ' home'; } ?>" role="banner">
	<?php if ( get_query_var( 'is_handbook' ) ) : ?>
	<a href="#" id="secondary-toggle" onclick="return false;"><strong><?php _e( 'Menu', 'bporg-developer' ); ?></strong></a>
	<?php endif; ?>
	<div class="site-branding">
		<h1 class="site-title">
			<a href="<?php echo esc_url( DevHub\bporg_developer_get_site_section_url() ); ?>" rel="home"><?php echo DevHub\bporg_developer_get_site_section_title(); ?></a>
		</h1>

		<?php if ( is_front_page() ) : ?>

            <p class="site-description"><?php bloginfo( 'description', 'display' ); ?></p>

        <?php else : ?>

            <nav id="site-navigation" class="main-navigation" role="navigation">
                <button class="menu-toggle dashicons dashicons-arrow-down-alt2" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Primary Menu', 'bporg-developer' ); ?>"></button>
                <?php
                $active_menu = is_post_type_archive( 'command' ) || is_singular( 'command' ) ? 'devhub-cli-menu' : 'devhub-menu';
                wp_nav_menu( array(
                    'theme_location'  => $active_menu,
                    'container_class' => 'menu-container',
                    'container_id'    => 'primary-menu',
                ) ); ?>
            </nav>

		<?php endif; ?>
	</div>
</header><!-- #masthead -->

<div id="page" class="hfeed site devhub-wrap">
	<?php do_action( 'before' ); ?>
	<?php
	if ( DevHub\should_show_search_bar() ) : ?>
		<div id="inner-search">
			<?php get_search_form(); ?>
			<div id="inner-search-icon-container">
				<div id="inner-search-icon">
					<div class="dashicons dashicons-search"><span class="screen-reader-text"><?php _e( 'Search', 'bporg-developer' ); ?></span></div>
				</div>
			</div>
		</div>

	<?php endif; ?>
	<div id="content" class="site-content">
