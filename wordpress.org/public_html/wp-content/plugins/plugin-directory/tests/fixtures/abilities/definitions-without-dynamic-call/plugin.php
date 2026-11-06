<?php
/**
 * Plugin Name: Definitions Without Dynamic Call
 */

// Looks like ability definitions, but this plugin only ever registers literal names,
// so these are something else (a settings screen, say) and must not be listed.
$settings_sections = array(
	'nodyn/general'  => array( 'label' => 'General', 'description' => 'General settings.' ),
	'nodyn/advanced' => array( 'label' => 'Advanced', 'description' => 'Advanced settings.' ),
);

wp_register_ability( 'nodyn/real', array( 'label' => 'Real', 'description' => 'The only real one.' ) );
