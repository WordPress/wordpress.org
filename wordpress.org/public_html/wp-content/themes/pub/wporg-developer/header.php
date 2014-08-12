<?php
/**
 * The Header for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @package wporg-developer
 */

$GLOBALS['pagetitle'] = wp_title( '|', false, 'right' );

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
		</div><!-- .inner-wrap -->
	</header><!-- #masthead -->
	<div id="content" class="site-content">
