<?php
/**
 * Plugin Name: Not Registrations
 */

// Polyfill-style declaration must not count as a call.
if ( ! function_exists( 'wp_register_ability' ) ) {
	function wp_register_ability( $name, $args ) {}
}

// wp_register_ability( 'ignored/in-line-comment', array() );

/*
 * wp_register_ability( 'ignored/in-block-comment', array() );
 */

$doc = "Call wp_register_ability( 'ignored/in-string', array() ) to register.";

// Method calls on other objects aren't the global function.
$registry->wp_register_ability( 'ignored/method-call', array() );
Some_Class::wp_register_ability( 'ignored/static-call', array() );

// Names core would reject.
wp_register_ability( 'NoNamespace', array() );
wp_register_ability( 'Upper/Case', array() );
wp_register_ability( 'too/many/segments', array() );
wp_register_ability( 'under_score/name', array() );

// Names that can't be resolved: an undefined constant and an unassigned variable.
wp_register_ability( MY_PLUGIN_PREFIX . '/dynamic', array() );
wp_register_ability( $name, array() );

// Built from literals, so this one is real.
wp_register_ability( sprintf( '%s/formatted', 'prefix' ), array() );

// The one real registration.
wp_register_ability( 'real/registration', array( 'label' => 'Real' ) );
