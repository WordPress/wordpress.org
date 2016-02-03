<?php
/**
 * W.org login functions and definitions.
 *
 * @package wporg-login
 */



/**
 * Registers support for various WordPress features.
 */
function wporg_login_setup() {
	load_theme_textdomain( 'wporg-login' );
}
add_action( 'after_setup_theme', 'wporg_login_setup' );



/**
 * Extend the default WordPress body classes.
 *
 * @param array $classes A list of existing body class values.
 * @return array The filtered body class list.
 */
function wporg_login_body_class( $classes ) {
//	$classes[] = 'wporg-responsive';
	$classes[] = 'wporg-login';
	return $classes;
}
add_filter( 'body_class', 'wporg_login_body_class' );



/**
 * Remove the toolbar.
 */
function wporg_login_init() {
	show_admin_bar( false );
}
add_action( 'init', 'wporg_login_init' );


/**
 * Replace cores login CSS with our own.
 */
function wporg_login_replace_css() {
	$css_file = '/stylesheets/login.css'; 
	wp_deregister_style( 'login' );
	wp_register_style( 'login', get_stylesheet_directory_uri() . $css_file, array( 'buttons', 'dashicons', 'open-sans' ), filemtime( __DIR__ . $css_file ) );
}
add_action( 'login_init', 'wporg_login_replace_css' );

/**
 * Add Google Analytics tracking to login pages.
 */
function wporg_login_analytics() {
?>
<script type="text/javascript">
var _gaq = _gaq || [];
_gaq.push(['_setAccount', 'UA-52447-1']);
_gaq.push(['_setDomainName', 'wordpress.org']);
_gaq.push(['_trackPageview']);
(function() {
	var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
})();
function recordOutboundLink(link, category, action) {
	_gaq.push(['_trackEvent', category, action])
	setTimeout('document.location = "' + link.href + '"', 100);
}
</script>
<?php
}
add_action( 'wp_footer', 'wporg_login_analytics' );
