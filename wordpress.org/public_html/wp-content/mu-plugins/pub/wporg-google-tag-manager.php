<?php
namespace WordPressdotorg\Plugin\GoogleTagManager;

/**
 * Plugin Name: WordPress.org Google Tag Manager.
 */

/**
 * Output the <head> tags.
 */
function wp_head() {
	?>
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-P24PF4B');</script>
	<?php
}
add_action( 'wp_head',    __NAMESPACE__ . '\wp_head', 5 );
add_action( 'login_head', __NAMESPACE__ . '\wp_head', 5 );

/**
 * Output the no-js variant.
 */
function wp_body_open() {
	?>
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-P24PF4B" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
	<?php
}
add_action( 'wp_body_open', __NAMESPACE__ . '\wp_body_open' );

/**
 * Add a dns-prefetch for the tag manager hostname.
 */
function wp_resource_hints( $uris, $type ) {
	if ( 'dns-prefetch' === $type ) {
		$uris[] = '//www.googletagmanager.com';
	}
	return $uris;
}
add_filter( 'wp_resource_hints', __NAMESPACE__ . '\wp_resource_hints', 10, 2 );
