<?php

namespace WordPressdotorg\Plugin_Directory\API\Fields\Plugin;

use WordPressdotorg\Plugin_Directory\Plugin_I18n;
use WordPressdotorg\Plugin_Directory\Template;

class Screenshots {

	/**
	 * Banners constructor.
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
		$descriptions = get_post_meta( $object['id'], 'screenshots', true ) ?: [];
		$screen_shots = get_post_meta( $object['id'], 'assets_screenshots', true ) ?: [];
		$response     = [];

		foreach ( $screen_shots as $image ) {
			$screen_shot = [
				'src' => esc_url( Template::get_asset_url( $object['id'], $image ) ),
			];

			if ( $descriptions && ! empty( $descriptions[ (int) $image['resolution'] ] ) ) {
				$caption                = $descriptions[ (int) $image['resolution'] ];
				$screen_shot['caption'] = Plugin_I18n::instance()->translate( 'screenshot-' . $image['resolution'], $caption, [ 'post_id' => $object['id'] ] );
			}

			$response[] = $screen_shot;
		}

		return $response;
	}
}
