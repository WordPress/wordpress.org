<?php

namespace WordCamp\Gutenberg_Tweaks;

defined( 'WPINC' ) || die();

add_filter( 'classic_editor_network_default_settings', __NAMESPACE__ . '\classic_editor_default_settings' );


/**
 * Configure the default settings for the Classic Editor
 */
function classic_editor_default_settings( $defaults ) {
	$defaults['editor']      = 'block';
	$defaults['allow-users'] = true;

	return $defaults;
}
