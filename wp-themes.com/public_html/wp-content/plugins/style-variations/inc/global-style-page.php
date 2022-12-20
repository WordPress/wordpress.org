<?php

namespace WordPressdotorg\Theme_Preview\Style_Variations\Global_Style_Page;

/**
 * Uses custom page if query string is present to display style variation cards.
 */
function redirect_to_style_page() {
	if ( ! isset( $_GET['card_view'] ) ) {
		return;
	}

	include dirname( __DIR__ ) . '/views/card.php';
	exit;

}

add_action( 'template_redirect', __NAMESPACE__ . '\redirect_to_style_page' );
