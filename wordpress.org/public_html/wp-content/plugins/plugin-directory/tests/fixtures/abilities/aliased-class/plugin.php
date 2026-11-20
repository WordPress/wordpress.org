<?php
/**
 * Plugin Name: Aliased Class
 */
namespace Aliased;

use Aliased\Abilities\Summarize as Summarize_Ability;
use function Aliased\Helpers\label_for as label;

// Modelled on the AI plugin: the ability class is imported under another name, and its
// file only declares the class, so nothing else causes it to be read.
add_action( 'wp_abilities_api_init', function () {
	wp_register_ability(
		'aliased/summarize',
		array(
			'label'         => label( 'summarize' ),
			'description'   => __( 'Summarizes a post.', 'aliased' ),
			'ability_class' => Summarize_Ability::class,
		)
	);
} );
