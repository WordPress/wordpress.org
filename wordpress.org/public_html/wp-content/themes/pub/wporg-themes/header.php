<?php
/**
 * The header for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @package wporg-themes
 */
 
// include 'http://wordpress.org/header.php'; // do the header change when not testing locally, all below is copy of header

?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" dir="ltr" lang="en-US">
<head profile="http://gmpg.org/xfn/11">
<meta charset="utf-8" />
<!--
<meta property="fb:page_id" content="6427302910" />
-->
<meta name="google-site-verification" content="7VWES_-rcHBcmaQis9mSYamPfNwE03f4vyTj4pfuAw0" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>WordPress &#8250; Free WordPress Themes</title>
<link href="//s.w.org/wp-includes/css/dashicons.css?20140409" rel="stylesheet" type="text/css" />
<link href='//fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,400,300,600&subset=latin,cyrillic-ext,greek-ext,greek,vietnamese,latin-ext,cyrillic' rel='stylesheet' type='text/css'>

<link media="only screen and (max-device-width: 480px)" href="//s.w.org/style/iphone.css?1" type="text/css" rel="stylesheet" />

<link rel="shortcut icon" href="//s.w.org/favicon.ico" type="image/x-icon" />


<!--[if lte IE 8]>
<style type="text/css">
@import url("//s.w.org/style/ie.css?1");
</style>
<![endif]-->

<meta name="generator" content="bbPress 1.1" />
<link rel='stylesheet' id='forum-wp4-css'  href='//s.w.org/style/forum-wp4.css?ver=11' type='text/css' media='' />
<link rel='stylesheet' id='style-wp4-css'  href='//s.w.org/bb-theme/plugins/style-wp4.css?ver=19' type='text/css' media='' />
<!--[if IE 7]>
<link rel='stylesheet' id='forum-ie7-css'  href='//s.w.org/style/forum-ie7.css?ver=7' type='text/css' media='' />
<![endif]-->
	<link rel='stylesheet' href='https://wordpress.org/extend/themes-plugins/bb-ratings/bb-ratings.css?4' type='text/css' />
<link rel="alternate" type="application/rss+xml" title="Free WordPress Themes &raquo; Recent Posts" href="https://wordpress.org/themes/rss/" />
<link rel="alternate" type="application/rss+xml" title="Free WordPress Themes &raquo; Recent Topics" href="https://wordpress.org/themes/rss/topics" /><script type="text/javascript" src="//s.w.org/wp-includes/js/jquery/jquery.js?v=1.10.2"></script>
<script>document.cookie='devicePixelRatio='+((window.devicePixelRatio === undefined) ? 1 : window.devicePixelRatio)+'; path=/';</script>
<script type="text/javascript">
var toggleMenu = function(){
    var m = document.getElementById('wporg-header-menu'),
        c = m.className;
	    m.className = c.match( ' active' ) ? c.replace( ' active', '' ) : c + ' active';
}
</script>
<?php wp_head(); ?>
</head>

<body id="wordpress-org" <?php body_class(); ?>>
<div id="wporg-header">
	<div class="wrapper">
		<a id="mobile-menu-button" class="" href="#" onclick="toggleMenu();"></a>
		<h1><a href="//wordpress.org">WordPress.org</a></h1>
		<div id="head-search">
			<form action="//wordpress.org/search/do-search.php" method="get">
				<input class="text" name="search" type="text" value="" maxlength="150" placeholder="Search WordPress.org" /> <input type="submit" class="button" value="" />
			</form>
		</div>
		<div style="clear:both"></div>

<ul id="wporg-header-menu">
<li><a href='//wordpress.org/showcase/' title='See some of the sites built on WordPress.'>Showcase</a></li>
<li><a href='' title='Find just the right look for your website.' class="current">Themes</a><div class="uparrow"></div></li>
<li><a href='//wordpress.org/plugins/' title='Plugins can extend WordPress to do almost anything you can imagine.'>Plugins</a></li>
<li><a href='//wordpress.org/mobile/' title='Take your website on the go!'>Mobile</a></li>
<li><a href='//wordpress.org/support/' title='Forums, documentation, help.'>Support</a><ul class="nav-submenu"><li><a href='//wordpress.org/support/' title='Support and discussion forums.'>Forums</a></li><li><a href='//codex.wordpress.org/Main_Page' title='Documentation, tutorials, best practices.'>Documentation</a></li></ul><div class="uparrow"></div></li>
<li><a href='//make.wordpress.org/' title='Contribute your knowledge.'>Get Involved</a></li>
<li><a href='//wordpress.org/about/' title='About the WordPress Organization, and where we&#039;re going.'>About</a></li>
<li><a href='//wordpress.org/news/' title='Come here for the latest scoop.'>Blog</a></li>
<li><a href='//wordpress.org/hosting/' title='Find a home for your blog.'>Hosting</a></li>
<li id="download" class="button download-button"><a href='//wordpress.org/download/' title='Get it. Got it? Good.'>Download WordPress</a></li>
</ul>
		<div style="clear:both"></div>
	</div>
</div>

<div id="download-mobile">
	<div class="wrapper">
		<span class="download-ready">Ready to get started?</span><a class="button download-button" href="//wordpress.org/download/" title="Get it. Got it? Good.">Download WordPress</a>
	</div>
</div>

<div id="headline">
	<div class="wrapper">
		<h2 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h2>

		<p class="login">
	Welcome, <a href='//profiles.wordpress.org/otto42'>Samuel Wood (Otto)</a>	 | <a href="https://wordpress.org/themes/bb-admin/">Admin</a>	| <a href="https://wordpress.org/themes/bb-login.php?action=logout">Log Out</a></p>

	</div>
</div>
