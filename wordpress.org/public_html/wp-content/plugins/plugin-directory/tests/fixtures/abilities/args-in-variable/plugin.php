<?php
/**
 * Plugin Name: Args In Variable
 */
add_action( 'wp_abilities_api_init', function () {
	$args = array(
		'label'       => __( 'Variable args', 'vars' ),
		'description' => __( 'The args are built before the call.', 'vars' ),
	);
	wp_register_ability( 'vars/variable-args', $args );

	// Args from a helper; the label is not a literal.
	wp_register_ability( 'vars/helper-args', vars_ability_args( 'helper' ) );

	// Label built with sprintf(), so it can't be resolved.
	wp_register_ability( 'vars/sprintf-label', array(
		'label'    => sprintf( __( 'Hello %s', 'vars' ), 'world' ),
		'category' => 'greetings',
	) );
} );
