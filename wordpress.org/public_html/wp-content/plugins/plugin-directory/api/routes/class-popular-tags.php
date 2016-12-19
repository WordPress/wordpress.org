<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WordPressdotorg\Plugin_Directory\API\Base;
use WP_REST_Server;

/**
 * An API Endpoint to expose a the plugin categories data.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Popular_Tags extends Base {

	function __construct() {
		register_rest_route( 'plugins/v1', '/popular-tags/?', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'popular_tags' ),
		) );
	}

	/**
	 * Endpoint to retrieve the popular categories for the plugin directory.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return array A formatted array of all plugin categories on the site.
	 */
	function popular_tags( $request ) {
		$terms = get_terms( 'plugin_tags', array(
			'hide_empty' => true,
			'orderby' => 'count',
			'order' => 'DESC',
			'number' => 1000
		) );

		$response = array();
		foreach ( $terms as $term ) {
			$response[ $term->slug ] = array(
				'name'  => html_entity_decode( $term->name ),
				'slug'  => $term->slug,
				'count' => $term->count,
			);
		}

		return $response;
	}

}

