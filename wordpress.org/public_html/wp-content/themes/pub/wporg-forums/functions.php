<?php
/**
 * WPBBP functions and definitions
 *
 * @package WPBBP
 */

/**
 * Enqueue scripts and styles.
 *
 * @uses bbp_register_view() To register the view
 * @link http://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/style
 */
function wporg_support_scripts() {
	wp_enqueue_style( 'wporg-support', get_stylesheet_uri() );
	wp_enqueue_style( 'dashicons',     get_template_directory_uri() . '/css/dashicons.css' );
	wp_enqueue_style( 'wp4-style',     get_template_directory_uri() . '/css/wp4.css'       );
	wp_enqueue_style( 'open-sans', '//fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,400,300,600' );
}
add_action( 'wp_enqueue_scripts', 'wporg_support_scripts' );

/**
 * Customized breadcrumb arguments
 *  Root Text: WordPress Support
 *  Custom seperator `«` and `»`
 *
 * @uses bbp_before_get_breadcrumb_parse_args() To parse the custom arguments
 */
function wporg_support_breadcrumb() {
	// HTML
	$args['before']          = '';
	$args['after']           = '';

	// Separator
	$args['sep']             = is_rtl() ? __( '&laquo;', 'bbpress' ) : __( '&raquo;', 'bbpress' );
	$args['pad_sep']         = 1;
	$args['sep_before']      = '<span class="bbp-breadcrumb-sep">' ;
	$args['sep_after']       = '</span>';

	// Crumbs
	$args['crumb_before']    = '';
	$args['crumb_after']     = '';

	// Home
	$args['include_home']    = false;

	// Forum root
	$args['include_root']    = true;
	$args['root_text']       = 'WordPress Support';

	// Current
	$args['include_current'] = true;
	$args['current_before']  = '<span class="bbp-breadcrumb-current">';
	$args['current_after']   = '</span>';

	return $args;
}
add_filter('bbp_before_get_breadcrumb_parse_args', 'wporg_support_breadcrumb' );

/**
 * Register these bbPress views:
 *  View: All Topics
 *  @ToDo View: Not Resolved
 *  @ToDo View: modlook
 *
 * @uses bbp_register_view() To register the view
 */
function wporg_support_custom_views() {
	bbp_register_view( 'all-topics', __( 'All Topics' ), array( 'order' => 'DESC' ), false );
//	bbp_register_view( 'support-forum-no', __( 'Not Resolved' ), array( 'post_status' => 'closed' ), false );
//	bbp_register_view( 'taggedmodlook', __( 'Tagged modlook' ), array( 'topic-tag' => 'modlook' ) );
}
add_action( 'bbp_register_views', 'wporg_support_custom_views' );

/**
 * Custom Body Classes
 *
 * @uses get_body_class() To add the `wporg-support` class
 */
function wporg_support_body_class($classes) {
	$classes[] = 'wporg-support';
	return $classes;
}
add_filter( 'get_body_class','wporg_support_body_class' );