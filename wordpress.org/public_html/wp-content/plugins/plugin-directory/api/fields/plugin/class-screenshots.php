<?php

namespace WordPressdotorg\Plugin_Directory\API\Fields\Plugin;

use WordPressdotorg\Plugin_Directory\Plugin_I18n;
use WordPressdotorg\Plugin_Directory\Template;

class Screenshots {

	/**
	 * Screenshots constructor.
	 */
	public function __construct() {
		register_rest_field( 'plugin', 'screenshots', [
			'get_callback' => [ $this, 'get_screenshots' ],
			'schema'       => [
				'description' => __( 'Plugin screenshots.', 'wporg-plugins' ),
				'type'        => 'object',
				'context'     => [ 'view' ],
				'properties'  => [ 'src', 'caption' ],
			],
		] );
	}

	/**
	 * @param array $object
	 * @return array
	 */
	public function get_screenshots( $object ) {
		// Reduce the Screenshots to the required fields.
		return array_values( array_map(
			function( $image ) {
				return [
					'src'     => $image['src'],
					'caption' => $image['caption'],
				];
			},
			Template::get_screenshots( $object['id'] )
		) );
	}
}
