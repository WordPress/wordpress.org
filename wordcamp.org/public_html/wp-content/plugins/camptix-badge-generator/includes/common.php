<?php

namespace CampTix\Badge_Generator;
use \CampTix\Badge_Generator\HTML;

defined( 'WPINC' ) or die();

add_filter( 'camptix_menu_tools_tabs',   __NAMESPACE__ . '\add_badges_tab'     );
add_action( 'camptix_menu_tools_badges', __NAMESPACE__ . '\render_badges_page' );
add_action( 'admin_print_styles',        __NAMESPACE__ . '\print_admin_styles' );

/**
 * Add the Generate Badges tab to the CampTix Tools page
 *
 * @param array $sections
 *
 * @return array
 */
function add_badges_tab( $sections ) {
	$sections['badges'] = __( 'Generate Badges', 'wordcamporg' );

	return $sections;
}

/**
 * Render the main Generate Badges page
 */
function render_badges_page() {
	if ( ! current_user_can( REQUIRED_CAPABILITY ) ) {
		return;
	}

	$html_customizer_url = HTML\get_customizer_section_url();
	$notify_tool_url     = admin_url( 'edit.php?post_type=tix_ticket&page=camptix_tools&tix_section=notify' );
	$indesign_page_url   = admin_url( 'edit.php?post_type=tix_ticket&page=camptix_tools&tix_section=indesign_badges' );

	require_once( dirname( __DIR__ ) . '/views/common/page-generate-badges.php' );
}

/**
 * Print CSS styles for wp-admin
 */
function print_admin_styles() {
	$screen = get_current_screen();

	if ( 'tix_ticket_page_camptix_tools' !== $screen->id ) {
		return;
	}

	?>

	<!-- BEGIN CampTix Badge Generator -->
	<style type="text/css">
		<?php require_once( dirname( __DIR__ ) . '/css/common.css' ); ?>
	</style>
	<!-- END CampTix Badge Generator -->

	<?php
}
