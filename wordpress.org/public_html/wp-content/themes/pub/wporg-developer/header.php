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

<div id="page" <?php body_class( 'hfeed site devhub-wrap' ); ?>>
	<?php do_action( 'before' ); ?>
	<header id="masthead" class="site-header" role="banner">
		<div class="inner-wrap">
			<div class="site-branding">
				<?php $tag = is_front_page() ? 'span' : 'h1'; ?>
				<<?php echo $tag; ?> class="site-title">
					<a href="<?php echo esc_url( DevHub\get_site_section_url() ); ?>" rel="home"><?php echo DevHub\get_site_section_title(); ?></a>
				</<?php echo $tag; ?>>
			</div>
			<div class="devhub-menu">
				<?php wp_nav_menu( array(
					'theme_location'  => 'devhub-menu',
					'container_class' => 'menu-container',
				) ); ?>
			</div>
		</div><!-- .inner-wrap -->
	</header><!-- #masthead -->
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
