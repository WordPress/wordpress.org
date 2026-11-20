<?php
/**
 * Plugin Name: Syntax Variations
 */

function syntax_register_abilities() {
	// Short array syntax, double-quoted strings and esc_html__().
	wp_register_ability( "syntax/short-array", [
		'label'       => esc_html__( 'Short array', 'syntax' ),
		'description' => "Uses [] and double quotes.",
		'category'    => 'content',
	] );

	// _x() with context, plain-string description, no category.
	wp_register_ability( 'syntax/with-context', array(
		'label'       => _x( 'With context', 'ability label', 'syntax' ),
		'description' => 'Uses _x().',
	) );

	// Case-insensitive function name, as PHP allows.
	WP_Register_Ability( 'syntax/mixed-case', array( 'label' => 'Mixed case' ) );
}
add_action( 'wp_abilities_api_init', 'syntax_register_abilities' );
