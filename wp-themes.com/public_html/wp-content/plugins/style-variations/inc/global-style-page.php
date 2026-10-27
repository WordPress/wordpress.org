<?php

namespace WordPressdotorg\Theme_Preview\Style_Variations\Global_Style_Page;

/**
 * Bypass the template loader to show the "card view" when query string is present.
 *
 * @param string $template The path of the template to include.
 *
 * @return string Updated template path.
 */
function inject_style_card_view( $template ) {
	if ( ! isset( $_GET['card_view'] ) ) {
		return $template;
	}

	return dirname( __DIR__ ) . '/views/card.php';
}

add_action( 'template_include', __NAMESPACE__ . '\inject_style_card_view', 100 );
