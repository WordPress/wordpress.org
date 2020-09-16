<?php
/**
 * The Header for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @package wporg-developer
 */

$GLOBALS['pagetitle'] = wp_get_document_title();

require WPORGPATH . 'header.php';
?>

<header id="masthead" class="site-header<?php if ( is_front_page() ) { echo ' home'; } ?>" role="banner">
	<?php if ( function_exists( 'wporg_is_handbook' ) && wporg_is_handbook() && ! is_search() ) : ?>
		<a href="#" id="secondary-toggle" onclick="return false;"><strong><?php _e( 'Menu', 'wporg' ); ?></strong></a>
	<?php endif; ?>
	<div class="site-branding">
		<h1 class="site-title">
			<a href="<?php echo esc_url( DevHub\get_site_section_url() ); ?>" rel="home"><?php echo DevHub\get_site_section_title(); ?></a>
		</h1>

		<?php if ( is_front_page() ) : ?>
		<p class="site-description"><?php _e( 'The freedom to build.', 'wporg' ); ?></p>
		<?php endif; ?>

		<nav id="site-navigation" class="main-navigation" role="navigation">
			<button class="menu-toggle dashicons dashicons-arrow-down-alt2" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Primary Menu', 'wporg' ); ?>"></button>
			<?php
			$active_menu = is_post_type_archive( 'command' ) || is_singular( 'command' ) ? 'devhub-cli-menu' : 'devhub-menu';
			wp_nav_menu( array(
				'theme_location'  => $active_menu,
				'container_class' => 'menu-container',
				'container_id'    => 'primary-menu',
			) ); ?>
		</nav>
	</div>
</header><!-- #masthead -->

<div id="page" class="hfeed site devhub-wrap">
	<a href="#main" class="screen-reader-text"><?php _e( 'Skip to content', 'wporg' ); ?></a>

	<?php do_action( 'before' ); ?>
	<?php
	if ( DevHub\should_show_search_bar() ) : ?>
		<div id="inner-search">
			<?php get_search_form(); ?>
			<div id="inner-search-icon-container">
				<div id="inner-search-icon">
					<div class="dashicons dashicons-search"><span class="screen-reader-text"><?php _e( 'Search', 'wporg' ); ?></span></div>
				</div>
			</div>
		</div>

	<?php endif; ?>
	<div id="content" class="site-content">
