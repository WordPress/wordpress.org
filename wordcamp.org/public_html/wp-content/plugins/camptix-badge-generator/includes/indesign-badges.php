<?php

namespace CampTix\Badge_Generator\InDesign;
use \CampTix\Badge_Generator;
use \CampTix\Badge_Generator\HTML;

defined( 'WPINC' ) or die();

add_action( 'camptix_menu_tools_indesign_badges', __NAMESPACE__ . '\render_indesign_page' );

/**
 * Render the Indesign Badges page
 */
function render_indesign_page() {
	if ( ! current_user_can( Badge_Generator\REQUIRED_CAPABILITY ) ) {
		return;
	}

	$html_customizer_url = HTML\get_customizer_section_url();

	require_once( dirname( __DIR__ ) . '/views/indesign-badges/page-indesign-badges.php' );
}
