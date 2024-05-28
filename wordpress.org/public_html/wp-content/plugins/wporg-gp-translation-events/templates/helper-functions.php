<?php
namespace Wporg\TranslationEvents\Templates;

use Wporg\TranslationEvents\Urls;

/**
 * Get event breadcrumb.
 *
 * @param array $extra_items   Array of additional items to add to the breadcrumb.
 *
 * @return string   HTML of the breadcrumb.
 */
function gp_breadcrumb_translation_events( $extra_items = array() ) {
	$home_link  = gp_link_get( home_url(), __( 'Home', 'gp-translation-events' ) );
	$breadcrumb = array(
		empty( $extra_items ) ? __( 'Events', 'gp-translation-events' ) : gp_link_get( Urls::events_home(), __( 'Events', 'gp-translation-events' ) ),
	);
	$breadcrumb = array_merge( array( $home_link ), $breadcrumb );
	if ( ! empty( $extra_items ) ) {
		$breadcrumb = array_merge( $breadcrumb, $extra_items );
	}
	return gp_breadcrumb( $breadcrumb );
}
