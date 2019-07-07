<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\Theme;

?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="http://gmpg.org/xfn/11">

	<?php wp_head(); ?>
</head>

<body id="wordpress-org" <?php body_class(); ?>>
<div id="wporg-header">
	<div class="wrapper">
		<a id="mobile-menu-button" class="" href="#" onclick="toggleMenu();"></a>
		<h1><a href="//wordpress.org">WordPress.org</a></h1>
		<div id="head-search">
			<form action="//wordpress.org/search/do-search.php" method="get">
				<label for="global-search" class="screen-reader-text">Search WordPress.org for:</label>
				<input id="global-search" class="text" name="search" type="text" value="" maxlength="150" placeholder="Search WordPress.org" /> <input type="submit" class="button" value="" />
			</form>
		</div>
		<div style="clear:both"></div>

		<ul id="wporg-header-menu">
			<li><a href='//wordpress.org/showcase/' title='See some of the sites built on WordPress.'>Showcase</a></li>
			<li><a href='//wordpress.org/themes/' title='Find just the right look for your website.'>Themes</a></li>
			<li><a href='//wordpress.org/plugins/' title='Plugins can extend WordPress to do almost anything you can imagine.'>Plugins</a></li>
			<li><a href='//wordpress.org/mobile/' title='Take your website on the go!'>Mobile</a></li>
			<li><a href='//wordpress.org/support/' title='Forums, documentation, help.'>Support</a>
				<ul class="nav-submenu">
					<li><a href='//wordpress.org/support/' title='Support and discussion forums.'>Forums</a></li>
					<li><a href='//codex.wordpress.org/Main_Page' title='Documentation, tutorials, best practices.'>Documentation</a></li>
				</ul>
				<div class="uparrow"></div>
			</li>
			<li><a href='//make.wordpress.org/' title='Contribute your knowledge.'>Get Involved</a></li>
			<li><a href='//wordpress.org/about/' title='About the WordPress Organization, and where we&#039;re going.'>About</a></li>
			<li><a href='//wordpress.org/news/' title='Come here for the latest scoop.'>Blog</a></li>
			<li><a href='//wordpress.org/hosting/' title='Find a home for your blog.'>Hosting</a></li>
			<li id="download" class="button download-button"><a href='//wordpress.org/download/' title='Get it. Got it? Good.'>Get WordPress</a></li>
		</ul>
		<div style="clear:both"></div>
	</div>
</div>
