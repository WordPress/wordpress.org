<?php
/**
 * Set up configuration for dynamic blocks.
 */

namespace WordPressdotorg\Theme\Plugins_2024\Block_Config;

add_filter( 'wporg_block_navigation_menus', __NAMESPACE__ . '\add_site_navigation_menus' );

/**
 * Provide a list of local navigation menus.
 */
function add_site_navigation_menus( $menus ) {
	return array(
		'plugins' => array(
			array(
				'label' => __( 'My Favorites', 'wporg-plugins' ),
				'url' => '/browse/favorites/',
			),
			array(
				'label' => __( 'Beta Testing', 'wporg-plugins' ),
				'url' => '/browse/beta/',
			),
            array(
				'label' => __( 'Developers', 'wporg-plugins' ),
				'url' => '/developers/',
			),
		),
	);
}
