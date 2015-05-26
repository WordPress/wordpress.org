<?php

/**
 * Set the content width based on the theme's design and stylesheet.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 692;
}

/**
 * Sets up theme defaults and registers support for various WordPress features.
 */
function rosetta_after_setup_theme() {
	add_theme_support( 'automatic-feed-links' );

	add_theme_support( 'html5', array(
		'search-form', 'comment-form', 'comment-list', 'gallery', 'caption'
	) );

	add_theme_support( 'custom-header', array(
		'default-image' => false,
		'header-text'   => false,
		'width'         => 466,
		'height'        => 303,
		'flex-height'   => true,
		'flex-width'    => true,
	) );

	register_nav_menu( 'rosetta_main', __( 'Main Menu', 'rosetta' ) );

	remove_action( 'wp_head', 'locale_stylesheet' );
}
add_action( 'after_setup_theme', 'rosetta_after_setup_theme' );

function rosetta_comment_form_support_hint() {
	printf(
		'<p>%s</p>',
		/* translators: feel free to add links to places, where one can get support in your language. */
		__( '<strong>Please, do not post support requests here!</strong> They will probably be ignored.', 'rosetta' )
	);
}
add_action( 'comment_form_top', 'rosetta_comment_form_support_hint' );

function rosetta_wp_page_menu_args( $args ) {
	$args['show_home'] = true;
	return $args;
}
add_filter( 'wp_page_menu_args', 'rosetta_wp_page_menu_args' );

function rosetta_admin_footer_nav_menus() {
	echo '<script> wpNavMenu.options.globalMaxDepth = 0; </script>';
}
add_action( 'admin_footer-nav-menus.php', 'rosetta_admin_footer_nav_menus' );

function rosetta_body_class( $classes ) {
	$classes[] = 'wporg-responsive';
	$classes[] = 'wporg-international';
	return $classes;
}
add_filter( 'body_class', 'rosetta_body_class' );

function is_locale_css() {
	global $rosetta;
	return file_exists( WP_LANG_DIR . '/css/' . $rosetta->locale . '.css' );
}

function get_locale_css_url() {
	global $rosetta;
	return set_url_scheme( WP_LANG_URL . '/css/' . $rosetta->locale . '.css?' . filemtime( WP_LANG_DIR . '/css/' . $rosetta->locale . '.css' ) );
}

// Makes final space a non-breaking one, to prevent orphaned word.
function rosetta_orphan_control( $string ) {
	return substr_replace( $string, '&nbsp;', strrpos( $string, ' ' ), 1 );
}
add_filter( 'no_orphans', 'rosetta_orphan_control' );

/**
 * Custom template tags for this theme.
 */
require get_template_directory() . '/inc/template-tags.php';
