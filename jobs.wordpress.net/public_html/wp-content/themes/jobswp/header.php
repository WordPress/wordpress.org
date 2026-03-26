<?php
/**
 * The Header for our theme.
 *
 * Displays all of the <head> section and everything up till <main>
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

<?php do_action( 'before' ); ?>

<header class="site-header">
	<div class="site-header__inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-header__logo">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 122.5 122.5" width="36" height="36">
				<g fill="#1e1e1e">
					<path d="M8.7 61.3c0 20.4 11.9 38 29.1 46.3L12.6 39.1C10.1 46 8.7 53.5 8.7 61.3z"/>
					<path d="M96.7 58.7c0-6.4-2.3-10.8-4.2-14.2-2.6-4.2-5-7.8-5-12 0-4.7 3.6-9.1 8.6-9.1h.6C86.7 14.3 74.5 8.7 61.3 8.7c-17.7 0-33.2 9.1-42.3 22.8 1.2 0 2.3.1 3.3.1 5.3 0 13.6-.6 13.6-.6 2.7-.2 3.1 3.9.3 4.2 0 0-2.8.3-5.8.5l18.4 54.6 11-33.1-7.9-21.5c-2.7-.2-5.3-.5-5.3-.5-2.7-.2-2.4-4.3.3-4.2 0 0 8.5.6 13.5.6 5.3 0 13.6-.6 13.6-.6 2.7-.2 3.1 3.9.3 4.2 0 0-2.8.3-5.8.5L85.3 90l5.1-17C93 66.5 96.7 62.1 96.7 58.7z"/>
					<path d="M62.2 65.9l-15.2 44.3c4.5 1.3 9.3 2.1 14.3 2.1 5.9 0 11.5-1 16.8-2.8-.1-.2-.3-.5-.4-.7L62.2 65.9z"/>
					<path d="M107.4 36c.4 3.2.7 6.6.7 10.3 0 10.1-1.9 21.5-7.6 35.7l-14.6 42.3c22.3-13 37.3-37.2 37.3-64.9 0-8.4-1.4-16.4-3.8-23.9l-12 .5z"/>
					<path d="M61.3 0C27.4 0 0 27.4 0 61.3c0 33.8 27.4 61.3 61.3 61.3 33.8 0 61.3-27.4 61.3-61.3C122.5 27.4 95.1 0 61.3 0zM61.3 119.7c-32.2 0-58.4-26.2-58.4-58.4S29.1 2.8 61.3 2.8s58.4 26.2 58.4 58.4-26.2 58.5-58.4 58.5z"/>
				</g>
			</svg>
			<?php esc_html_e( 'WordPress Jobs', 'jobswp' ); ?>
		</a>
		<div class="site-header__right">
			<nav class="site-header__nav">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"<?php if ( is_front_page() ) echo ' class="active"'; ?>><?php esc_html_e( 'Jobs', 'jobswp' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>"><?php esc_html_e( 'FAQ', 'jobswp' ); ?></a>
				<a href="<?php echo esc_url( home_url( '/post-a-job/' ) ); ?>" class="btn btn-primary btn-sm"><?php esc_html_e( 'Post a Job', 'jobswp' ); ?></a>
			</nav>
			<button class="mobile-menu-toggle" aria-label="<?php esc_attr_e( 'Toggle menu', 'jobswp' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
					<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
				</svg>
			</button>
		</div>
	</div>
</header>
