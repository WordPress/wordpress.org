<?php

if ( ! function_exists( 'wpcom_vip_load_plugin' ) ) {
	require_once WP_CONTENT_DIR . '/vip-plugins/vip-init.php';
}
wpcom_vip_load_plugin( 'taxonomy-list-widget' );
