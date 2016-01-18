<?php
/**
 * The main template file.
 *
 * @package wporg-login
 */

get_header();

if ( ! empty( $_GET['screen'] ) ) {
	$screen = preg_replace( '/[^a-z0-9-]/', '', $_GET['screen'] );
} else if ( preg_match( '/^\/oauth([\/\?]{1}.*)?$/', $_SERVER['REQUEST_URI'] ) ) {
	$screen = 'oauth';
} else {
	$screen = 'login';
}

$partial = __DIR__ . '/partials/' . $screen . '.php';

if ( file_exists( $partial ) ) {
	require_once( $partial );
}

get_footer();