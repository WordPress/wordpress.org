<?php
/**
 * The Header for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @package jobswp
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php wp_title( '|', true, 'right' ); ?></title>
<link rel="profile" href="http://gmpg.org/xfn/11">
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">
<!-- Google Tag Manager -->
<link rel="dns-prefetch" href="//www.googletagmanager.com"/>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-P24PF4B');</script>
<!-- End Google Tag Manager -->

<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-P24PF4B" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<div id="page" class="hfeed site">
	<?php do_action( 'before' ); ?>
	<header id="masthead" class="site-header" role="banner">
	<div class="container">
		<div class="site-branding grid_4">
			<div id="logo"><a href="/"><strong>jobs</strong>.wordpress.net</a></div>
		</div>

		<nav id="site-navigation" class="main-navigation grid_8" role="navigation">
			<h1 class="menu-toggle"><?php _e( 'Menu', 'jobswp' ); ?></h1>
			<div class="screen-reader-text skip-link"><a href="#content" title="<?php esc_attr_e( 'Skip to content', 'jobswp' ); ?>"><?php _e( 'Skip to content', 'jobswp' ); ?></a></div>

			<?php wp_nav_menu( array( 'theme_location' => 'primary' ) ); ?>
		</nav><!-- #site-navigation -->
	</div>
	</header><!-- #masthead -->

	<div id="subhead">
		<div class="container">
			<div class="grid_3 wporg-link" >
				<a href="https://wordpress.org" title="<?php esc_attr_e( 'Return to WordPress.org', 'jobswp' ); ?>">&laquo; Return to WordPress.org</a>
			</div>
			<div class="grid_9">
				<?php get_search_form(); ?>
			</div>
		</div>
	</div>

	<div id="content" class="site-content container">
