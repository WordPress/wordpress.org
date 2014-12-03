<?php
/**
 * WordPress.tv Rewrites
 *
 * Some WordPress.tv plugins will add new rewrite rules during init, this plugin
 * will allow a $_GET request to flush rules.
 */
function wptv_maybe_flush_rewrite_rules() {
	global $wp_rewrite;

	// Visit http://wordpress.tv/?flush_rules=1 as a super admin or kovshenin (4637740)
	// to flush the rules for /unisubs/ endpoint to work.
	if ( ( is_super_admin() || get_current_user_id() == 4637740 ) && !empty( $_GET['flush_rules'] ) ) {
		$wp_rewrite->flush_rules();
		exit( 'Rewrite rules flushed.' );
	}
}
add_action( 'init', 'wptv_maybe_flush_rewrite_rules', 99 );
