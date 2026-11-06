<?php
/**
 * Plugin Name: Loop Registration
 */
add_action( 'wp_abilities_api_init', function () {
	$abilities = array(
		'loop/first'  => array(
			'label'       => __( 'First', 'loop' ),
			'description' => __( 'The first ability.', 'loop' ),
			'category'    => 'loop-tools',
		),
		'loop/second' => array(
			'label'       => __( 'Second', 'loop' ),
			'description' => __( 'The second ability.', 'loop' ),
		),
	);
	foreach ( $abilities as $name => $args ) {
		wp_register_ability( $name, $args );
	}
} );
