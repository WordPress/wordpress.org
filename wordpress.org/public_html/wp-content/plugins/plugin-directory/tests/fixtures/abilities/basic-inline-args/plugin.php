<?php
/**
 * Plugin Name: Basic Inline Args
 */

add_action( 'wp_abilities_api_init', function () {
	wp_register_ability(
		'basic/get-site-info',
		array(
			'label'               => __( 'Get site info', 'basic' ),
			'description'         => __( 'Returns the site title and URL.', 'basic' ),
			'category'            => 'site',
			'execute_callback'    => 'basic_get_site_info',
			'permission_callback' => '__return_true',
		)
	);
} );
