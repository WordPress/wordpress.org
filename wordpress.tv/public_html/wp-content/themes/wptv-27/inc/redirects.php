<?php
/**
 * Static redirects for URLs that would otherwise return 404.
 *
 * Route contract (docs/routes.md). The `?guid=` redirect lives with the
 * /unisubs/ endpoint (inc/endpoints/unisubs.php), as in wptv2.
 *
 * @package WordPressTV
 */

namespace WordPressTV\Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirects legacy URLs. Runs on template_redirect, priority 1, only on 404s.
 */
function redirect_legacy_urls() {
	if ( ! is_404() || empty( $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only compared and passed to wp_safe_redirect().
	$request_uri = wp_unslash( $_SERVER['REQUEST_URI'] );

	// The Learn WordPress category was renamed from social-learning to learn-wordpress-online-workshops.
	if ( str_starts_with( $request_uri, '/category/social-learning' ) ) {
		wp_safe_redirect( str_replace( '/social-learning', '/learn-wordpress-online-workshops', $request_uri ), 301 );
		exit;
	}

	// /upload is the old address of the video submission form.
	if ( 'upload' === trim( $request_uri, '/' ) ) {
		wp_safe_redirect( '/submit-video/', 301 );
		exit;
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\redirect_legacy_urls', 1 );
