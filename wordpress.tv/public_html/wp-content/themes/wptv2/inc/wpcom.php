<?php

require_once WP_CONTENT_DIR . '/themes/vip/plugins/vip-init.php';
wpcom_vip_load_plugin( 'taxonomy-list-widget' );

if ( apply_filters( 'wptv_setup_theme', true ) ) {
	// Load plugins and setup theme
	require_once get_template_directory() . '/plugins/rewrite.php';
	require_once get_template_directory() . '/plugins/wordpresstv-rest/wordpresstv-rest.php';
	require_once get_template_directory() . '/plugins/wordpresstv-anon-upload/anon-upload.php';
}