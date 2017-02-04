<?php

add_theme_support( 'automatic-feed-links' );

add_action( 'init', 'learn_register_menus' );
function learn_register_menus() {
	register_nav_menus(
		array( 'fp-below-header-menu' => 'Front Page Below Header Menu' )
	);
}

add_action( 'widgets_init', 'learn_register_sidebars' );
function learn_register_sidebars() {
	register_sidebar( array(
		'name' => 'Main Sidebar',
		'id' => 'sidebar-1',
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => "</aside>",
		'before_title' => '<h4 class="widget-title">',
		'after_title' => '</h4>',
	) );
}

/**
 * Omit page name from front page title.
 *
 * @param array $parts The document title parts.
 * @return array The document title parts.
 */
function learn_remove_frontpage_name_from_title( $parts ) {
	if ( is_front_page() ) {
		$parts['title'] = '';
	}

	return $parts;
}
add_filter( 'document_title_parts', 'learn_remove_frontpage_name_from_title' );

function learn_scripts() {
	wp_enqueue_style( 'wporg-learn-fonts', '//fonts.googleapis.com/css?family=Roboto+Condensed:700', array(), 1, 'screen' );
	wp_enqueue_style( 'buttons' );
}
add_action( 'wp_enqueue_scripts', 'learn_scripts', 9 );
