<?php
/**
 * Get event breadcrumb.
 *
 * @param array $extra_items   Array of additional items to add to the breadcrumb.
 *
 * @return string   HTML of the breadcrumb.
 */
function gp_breadcrumb_translation_events( $extra_items = array() ) {
	$breadcrumb = array(
		empty( $extra_items ) ? __( 'Events', 'gp-translation-events' ) : gp_link_get( gp_url( '/events' ), __( 'Events', 'gp-translation-events' ) ),
	);
	if ( ! empty( $extra_items ) ) {
		$breadcrumb = array_merge( $breadcrumb, $extra_items );
	}
	return gp_breadcrumb( $breadcrumb );
}
