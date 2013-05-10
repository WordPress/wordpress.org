<?php
/**
 * The Header for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="main">
 *
 * @package WCBS
 * @since WCBS 1.0
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<title><?php
	/*
	 * Print the <title> tag based on what is being viewed.
	 */
	global $page, $paged;

	wp_title( '|', true, 'right' );

	// Add the blog name.
	bloginfo( 'name' );

	// Add the blog description for the home/front page.
	$site_description = get_bloginfo( 'description', 'display' );
	if ( $site_description && ( is_home() || is_front_page() ) )
		echo " | $site_description";

	// Add a page number if necessary:
	if ( $paged >= 2 || $page >= 2 )
		echo ' | ' . sprintf( __( 'Page %s', 'wcbs' ), max( $paged, $page ) );

	?></title>
<link rel="profile" href="http://gmpg.org/xfn/11" />
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />
<!--[if lt IE 9]>
<script src="<?php echo get_template_directory_uri(); ?>/js/html5.js" type="text/javascript"></script>
<![endif]-->

<?php /* Typekit now in SafeCSS addon plugin.
	<script type="text/javascript" src="http://use.typekit.com/spx4bwt.js"></script>
	<script type="text/javascript">try{Typekit.load();}catch(e){}</script>
*/ ?>

<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>

<div id="page" class="hfeed site">
	<?php do_action( 'before' ); ?>
	<header id="masthead" class="site-header" role="banner">
		<hgroup>
			<h1 class="site-title"><a href="<?php echo home_url( '/' ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
			<h2 class="site-description"><?php bloginfo( 'description' ); ?></h2>
		</hgroup>

		<nav role="navigation" class="site-navigation main-navigation">
			<h1 class="assistive-text"><?php _e( 'Menu', 'wcbs' ); ?></h1>
			<div class="assistive-text skip-link"><a href="#content" title="<?php esc_attr_e( 'Skip to content', 'wcbs' ); ?>"><?php _e( 'Skip to content', 'wcbs' ); ?></a></div>

			<?php wp_nav_menu( array( 'theme_location' => 'primary' ) ); ?>
		</nav>
	</header><!-- #masthead .site-header -->
	
	<?php // After Header Widget Areas for homepage and all other pages ?>
	<?php if ( is_front_page () ) { ?>
		<div id="after-header-widgets" class="widget-area front-page">
			<?php if ( ! dynamic_sidebar( 'after-header-homepage' ) ) : ?>
			<?php endif; ?>
		</div><!-- #after-header-widgets .widget-area .front-page -->
	<?php } else { ?>
		<div id="after-header-widgets" class="widget-area">
			<?php if ( ! dynamic_sidebar( 'after-header' ) ) : ?>
			<?php endif; ?>
		</div><!-- #after-header-widgets .widget-area -->
	<?php } ?>
	
	<div id="main">
	
		<?php // Before Content Widget Areas for homepage and all other pages ?>
		<?php if ( is_front_page () ) { ?>
			<div id="before-content-widgets" class="widget-area front-page">
				<?php if ( ! dynamic_sidebar( 'before-content-homapage' ) ) : ?>
				<?php endif; ?>
			</div><!-- #before-content-widgets .widget-area .front-page -->
		<?php } else { ?>
			<div id="before-content-widgets" class="widget-area">
				<?php if ( ! dynamic_sidebar( 'before-content' ) ) : ?>
				<?php endif; ?>
			</div><!-- #before-content-widgets .widget-area -->
		<?php } ?>