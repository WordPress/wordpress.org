<?php
namespace WordPressdotorg\Plugin_Directory\API\Fields\Plugin;

use WordPressdotorg\Plugin_Directory\Template;

class Icons {

	/**
	 * Icons constructor.
	 */
	public function __construct() {
		register_rest_field( 'plugin', 'icons', [
			'get_callback' => [ $this, 'get_icon' ],
			'schema'       => [
				'description' => __( 'Plugin icon.', 'wporg-plugins' ),
				'type'        => 'object',
				'context'     => [ 'view' ],
				'properties'  => [ 'svg', 'icon', 'icon_x2', 'generated' ],
			],
		] );
	}

	/**
	 * @param array $object
	 * @return mixed
	 */
	public function get_icon( $object ) {
		return Template::get_plugin_icon( $object['id'] );
	}
}
