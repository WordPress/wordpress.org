<?php
/**
 * WordPress.tv Header Template
 *
 * @global $wptv
 */

global $wptv;
?><!DOCTYPE html>
<!--[if IE 6]>    <html class="ie6" <?php language_attributes(); ?>> <![endif]-->
<!--[if IE 7]>    <html class="ie7" <?php language_attributes(); ?>> <![endif]-->
<!--[if IE 8]>    <html class="ie8" <?php language_attributes(); ?>> <![endif]-->
<!--[if gt IE 8]><!--> <html <?php language_attributes(); ?>> <!--<![endif]-->

<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<title><?php wp_title( '|', true, 'right' ); ?></title>

	<link rel="alternate" type="application/rss+xml" title="<?php esc_attr_e( 'WordPress.tv RSS Feed', 'wptv' ); ?>" href="http://wordpress.tv/feed/" />
	<link rel="alternate" type="application/rss+xml" title="<?php esc_attr_e( 'WordPress.tv Blog RSS Feed', 'wptv' ); ?>" href="http://blog.wordpress.tv/feed/" />
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />

	<script type="text/javascript" src="http://use.typekit.com/mgi6udv.js"></script>
	<script type="text/javascript">try{Typekit.load();}catch(e){}</script>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<div id="page">
	<div id="header">
		<div class="sleeve">

			<h1><a rel="home" href="<?php echo $wptv->home_url( '/' ); ?>"><img src="<?php echo get_template_directory_uri(); ?>/i/wptv-2x.png" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" height="40" width="210" /></a></h1>

			<form id="searchform" name="searchform" method="get" action="<?php echo $wptv->home_url( '/' ); ?>">
				<label for="searchbox" class="screen-reader-text"><?php esc_attr_e( 'Search WordPress.tv', 'wptv' ); ?></label>
				<input type="search" placeholder="<?php esc_attr_e( 'Search WordPress.tv', 'wptv' ); ?>" id="searchbox" name="s" value="<?php the_search_query(); ?>"  />
				<input type="submit" value="<?php esc_attr_e( 'Search', 'wptv' ); ?>" />
			</form>

			<div id="menu">
				<?php wp_nav_menu( array( 'theme_location' => 'primary' ) ); ?>
			</div>

		</div><!-- .sleeve -->
	</div><!-- #header -->
