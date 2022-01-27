<?php
/**
 * Use the WordPress.org global header & footer on wp-login.php
 */
add_action( 'login_header', function() {
	echo do_blocks( '<!-- wp:wporg/global-header -->');
	echo '<div class="wrapper">';
} );

add_action( 'login_footer', function() {
	echo '</div>';
	do_action( 'wp_footer' );
	// OR use the wporg footer: echo do_blocks( '<!-- wp:wporg/global-footer -->'); exit;
}, 1000 );

/**
 * A series of hooks to capture the wp-login.php header, and allow our header to show instead.
 */
add_action( 'login_init', function() {
	// Capture the forced login header
	ob_start();

	// Remove all actions from login_head, which includes the Script/Style print functions.
	remove_all_actions( 'login_head' );

	add_action( 'login_header', function() {
		// Fetch the forced header, discarding it, other than some elements from within.
		$header = ob_get_clean();

		// Remove the WordPress login styles.
		wp_dequeue_style( 'login' );

		add_filter( 'wp_body_open', function() use( $header ) {
			if ( preg_match_all('!<(style|script)[^>]*>(.*?)</\\1>!is', $header, $m ) ) {
				echo implode( "\n", $m[0] );
			}
		} );
	}, -999 );
}, -999 );

add_filter( 'body_class', function( $classes ) {
	global $action;

	$classes[] = 'login-action-' . $action;
	$classes[] = 'no-js';
	$classes[] = 'login';

	if ( is_rtl() ) {
		$classes[] = 'rtl';
	}

	$classes[] = ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );

	$classes = apply_filters( 'login_body_class', $classes, $action );

	return $classes;
} );