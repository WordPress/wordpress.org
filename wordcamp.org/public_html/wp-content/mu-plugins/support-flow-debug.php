<?php
/*
 * Show SupportFlow's log to network admins
 */
function wporg_show_supportflow_log() {
    if ( current_user_can( 'manage_network' ) ) {
        add_filter( 'supportflow_show_log', '__return_true' );
    }
}
add_action( 'init', 'wporg_show_supportflow_log' );
