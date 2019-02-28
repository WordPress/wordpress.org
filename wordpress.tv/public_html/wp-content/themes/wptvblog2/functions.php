<?php
/**
 * WordPress.tv Blog Functions
 *
 * Most of the things in the blog theme are dependent on
 * the original WordPress.tv theme. We use the wptv_require_parent()
 * function to require() these files from the pseudo-parent theme.
 *
 * @package WordPressTV_Blog
 */

/**
 * Require Parent
 *
 * Runs require_once on the given filename in the pseudo-parent
 * theme. If you change this, ion't forget to check style.css which
 *
 * @imports the parent stylesheet too.
 *
 * @param string $filename Filename to include.
 */
function wptv_require_parent( $filename = '' ) {
	require_once dirname( get_template_directory() ) . '/wptv2/' . $filename;
}

/**
 * Enqueue styles.
 */
function wptv_blog_scripts() {
	wp_register_style( 'wptv', get_stylesheet_directory_uri() . '/../wptv2/style.css', [], 's' );
	wp_enqueue_style( 'wptv-blog', get_stylesheet_directory_uri() . '/style.css', [ 'wptv' ], 1 );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
remove_action( 'wp_enqueue_scripts', 'wptv_enqueue_scripts' );
add_action( 'wp_enqueue_scripts', 'wptv_blog_scripts' );

// Don't setup theme and don't load plugins.
add_filter( 'wptv_setup_theme', '__return_false' );
wptv_require_parent( 'functions.php' );

// Change $wptv->home_url to lead to WordPress.tv.
add_filter( 'wptv_home_url', function( $home_url, $path = '' ) {
	return esc_url( 'https://wordpress.tv' . $path );
}, 10, 2 );

// Care to navigate?
register_nav_menu( 'primary', __( 'Primary Menu', 'wptv' ) );

// Sidebar please.
add_action( 'widgets_init', 'register_sidebar' );

// Add a body class to  identify blog.
add_filter( 'body_class', function( $classes ) {
	$classes[] = 'wptv-blog';

	return $classes;
} );

// Excerpts ...
add_filter( 'excerpt_more', function( $more ) {
	return ' ...';
} );

/**
 * Collect stats.
 *
 * @param string $new_status New post status.
 * @param string $old_status Old post status.
 */
function wptv_blog_transition_post_status( $new_status, $old_status ) {
	if ( 'publish' !== $new_status || 'publish' === $old_status ) {
		return;
	}

	bump_stats_extras( 'wptv-activity', 'publish-post' );
}
add_action( 'transition_post_status', 'wptv_blog_transition_post_status', 10, 2 );
