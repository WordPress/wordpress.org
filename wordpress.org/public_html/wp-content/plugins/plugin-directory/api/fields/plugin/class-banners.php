<?php

namespace WordPressdotorg\Plugin_Directory\API\Fields\Plugin;

use WordPressdotorg\Plugin_Directory\Template;

class Banners {

	/**
	 * Banners constructor.
	 */
	public function __construct() {
		register_rest_field( 'plugin', 'banners', [
			'get_callback' => [ $this, 'get_banner' ],
			'schema'       => [
				'description' => __( 'Plugin banner.', 'wporg-plugins' ),
				'type'        => 'object',
				'context'     => [ 'view' ],
				'properties'  => [ 'banner', 'banner_x2' ],
			],
		] );
	}

	/**
	 * @param array $object
	 * @return mixed
	 */
	public function get_banner( $object ) {
		return Template::get_plugin_banner( $object['id'] );
	}
}
