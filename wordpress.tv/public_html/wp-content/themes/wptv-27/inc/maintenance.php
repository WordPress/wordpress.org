<?php
/**
 * Maintenance helpers: rewrite rule flushing.
 *
 * The theme adds its own routes (/api/, /oembed/, /unisubs/), which only work
 * once WordPress regenerates its stored rewrite rules.
 *
 * @package WordPressTV
 */

namespace WordPressTV\Maintenance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Flushes rewrite rules when a site administrator visits any URL with ?flush_rules=1.
 *
 * Kept from wptv2, because WordPress.com offers no other way to flush rules
 * after a deploy. Site administrators can run it (edit_theme_options), since
 * they lack manage_options there; it only regenerates the rules. wptv2 limited
 * it to super admins plus one hardcoded user (docs/decisions.md).
 *
 * A plain ?flush_rules=1 link only shows a confirmation link with a nonce, so a
 * third-party page cannot trigger the flush.
 */
function maybe_flush_rewrite_rules() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is verified below.
	if ( empty( $_GET['flush_rules'] ) || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'wptv-flush-rules' ) ) {
		wp_die(
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( wp_nonce_url( add_query_arg( 'flush_rules', '1', home_url( '/' ) ), 'wptv-flush-rules' ) ),
				esc_html__( 'Flush rewrite rules', 'wptv' )
			),
			esc_html__( 'Flush rewrite rules', 'wptv' )
		);
	}

	flush_rewrite_rules();
	exit( 'Rewrite rules flushed.' );
}
// Core defers a flush requested before wp_loaded, so an earlier hook would exit before it ran.
add_action( 'wp_loaded', __NAMESPACE__ . '\maybe_flush_rewrite_rules' );

/**
 * Flushes rewrite rules when the theme is activated, so its routes work at once.
 *
 * Core also flushes on the first load after a theme switch; this makes it
 * explicit for the theme's own routes, registered earlier on init.
 */
function flush_on_activation() {
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', __NAMESPACE__ . '\flush_on_activation' );
