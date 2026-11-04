<?php
// The plugin's own registration chokepoint: names arrive from the registry.
function regf_register_ability_with_log( string $name, array $args ) {
	error_log( "Registering $name" );
	return wp_register_ability( $name, $args );
}

add_action( 'wp_abilities_api_init', function () {
	$registry = apply_filters( 'regf_abilities_registry', array() );
	foreach ( $registry as $name => $row ) {
		regf_register_ability_with_log( $name, call_user_func( $row['args_builder'] ) );
	}
} );
