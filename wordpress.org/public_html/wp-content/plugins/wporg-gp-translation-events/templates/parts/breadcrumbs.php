<?php
namespace Wporg\TranslationEvents\Templates\Parts;

use Wporg\TranslationEvents\Urls;

/** @var array $extra_items */

$home_link  = gp_link_get( home_url(), __( 'Home', 'gp-translation-events' ) );
$breadcrumb = array(
	empty( $extra_items ) ? __( 'Events', 'gp-translation-events' ) : gp_link_get( Urls::events_home(), __( 'Events', 'gp-translation-events' ) ),
);

$breadcrumb = array_merge( array( $home_link ), $breadcrumb );
if ( ! empty( $extra_items ) ) {
	$breadcrumb = array_merge( $breadcrumb, $extra_items );
}

gp_breadcrumb( $breadcrumb );
