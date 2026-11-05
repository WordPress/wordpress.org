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
 */
function maybe_flush_rewrite_rules() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Idempotent maintenance action, restricted to site administrators.
	if ( empty( $_GET['flush_rules'] ) || ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	flush_rewrite_rules();
	exit( 'Rewrite rules flushed.' );
}
add_action( 'init', __NAMESPACE__ . '\maybe_flush_rewrite_rules', 99 );

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
