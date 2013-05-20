<?php

add_action( 'after_setup_theme', 'wporg_p2_after_setup_theme' );
function wporg_p2_after_setup_theme() {
	register_nav_menu( 'wporg_header_subsite_nav', 'WP.org Header Sub-Navigation' );
}
