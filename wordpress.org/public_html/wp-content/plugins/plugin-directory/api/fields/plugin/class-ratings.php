<?php
namespace WordPressdotorg\Plugin_Directory\API\Fields\Plugin;

class Ratings {

	/**
	 * Icons constructor.
	 */
	public function __construct() {
		register_rest_field( 'plugin', 'ratings', [
			'get_callback' => [ $this, 'get_ratings' ],
			'schema'       => [
				'description' => __( 'Plugin ratings.', 'wporg-pluginss' ),
				'type'        => 'object',
				'context'     => [ 'view' ],
				'properties'  => [
					'1' => [ 'type' => 'integer' ],
					'2' => [ 'type' => 'integer' ],
					'3' => [ 'type' => 'integer' ],
					'4' => [ 'type' => 'integer' ],
					'5' => [ 'type' => 'integer' ],
				],
			],
		] );
	}

	/**
	 * @param array $object
	 * @return array
	 */
	public function get_ratings( $object ) {
		$data = get_post_meta( $object['id'], 'ratings', true );
		if ( is_array( $data ) ) {
			$ratings = array_map( 'absint', $data );
		} else {
			$ratings = array();
		}

		return $ratings ?: [];
	}
}
