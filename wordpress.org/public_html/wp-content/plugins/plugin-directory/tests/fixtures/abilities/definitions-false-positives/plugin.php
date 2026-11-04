<?php
/**
 * Plugin Name: Definitions False Positives
 */
add_action( 'wp_abilities_api_init', function () {
	foreach ( fp_definitions() as $name => $args ) {
		wp_register_ability( $name, $args );
	}
} );

function fp_definitions() {
	return array(
		'fp/real' => array( 'label' => 'Real', 'description' => 'A real ability.' ),
	);
}

// Core block names with label and description: core/ is never a plugin's ability.
$block_variations = array(
	'core/paragraph' => array( 'label' => 'Lead paragraph', 'description' => 'A larger intro paragraph.' ),
);

// Label only: not enough to be an ability definition.
$menu = array(
	'fp/menu-item' => array( 'label' => 'Menu item' ),
);

// Names core would reject.
$bad = array(
	'fp/Bad_Name'  => array( 'label' => 'Bad', 'description' => 'Invalid name.' ),
	'no-namespace' => array( 'label' => 'Bad', 'description' => 'Invalid name.' ),
);

// Index access and commented-out definitions aren't array literals.
$label = $definitions['fp/indexed']['label'];
// 'fp/commented' => array( 'label' => 'Commented', 'description' => 'Commented out.' ),
