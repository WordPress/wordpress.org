<?php

/**
 * Reinitializes handbooks.
 *
 * Necessary when defining new handbooks.
 *
 * @param array  $data Data for defining handbooks.
 * @param string $type Optional. The type of info provided in $data. Either
 *                     'config' (for 'handbooks_config' filter) or 'post_types'
 *                     (for 'handbook_post_types').
 */
function reinit_handbooks( $data, $type = 'config' ) {
	WPorg_Handbook_Init::reset( true );

	$hook = 'config' === $type ? 'handbooks_config' : 'handbook_post_types';

	add_filter( $hook, function( $x ) use ( $data ) { return $data; } );

	WPorg_Handbook_Init::init();

	foreach ( WPorg_Handbook_Init::get_handbook_objects() as $handbook ) {
		$handbook->register_post_type();
	}
}

/**
 * Data provider for default handbook config options.
 *
 * @return array
 */
function dataprovider_get_default_config() {
	return [
		[ 'cron_interval', '15_minutes' ],
		[ 'label', '' ],
		[ 'manifest', '' ],
		[ 'slug', '' ],
	];
}
