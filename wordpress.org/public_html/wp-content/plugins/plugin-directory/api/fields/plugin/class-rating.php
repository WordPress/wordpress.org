<?php
namespace WordPressdotorg\Plugin_Directory\API\Fields\Plugin;

class Rating {

	/**
	 * Icons constructor.
	 */
	public function __construct() {
		register_rest_field( 'plugin', 'rating', [
			'get_callback' => [ $this, 'get_rating' ],
			'schema'       => [
				'description' => __( 'Plugin rating.', 'wporg-plugins' ),
				'type'        => 'float',
				'context'     => [ 'view' ],
				'properties'  => [],
			],
		] );
	}

	/**
	 * @param array $object
	 * @return float
	 */
	public function get_rating( $object ) {
		return (float) get_post_meta( $object['id'], 'rating', true ) ?: 0.0;
	}
}
