<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WordPressdotorg\Plugin_Directory\API\Base;
use WP_REST_Server;
use WP_Query;

/**
 * An API Endpoint to Query plugins based on a select few query parameters.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Query_Plugins extends Base {

	protected $valid_query_fields = array(
		'paged',
		'posts_per_page',
		'browse',
		'favorites_user',
		'plugin_category',
		's',
		'author_name'
	);

	function __construct() {
		register_rest_route( 'plugins/v1', '/query-plugins/?', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'query' ),
		) );
	}

	/**
	 * Endpoint to query plugins.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return array A formatted array of plugin results.
	 */
	function query( $request ) {
		$response = array(
			'info' => array(
				'page'    => 0,
				'pages'   => 0,
				'results' => 0,
			),
			'plugins' => array(),
		);

		$query = array_intersect_key( $request->get_params(), array_flip( $this->valid_query_fields ) );

		if ( ! $query ) {
			return $response;
		}

		$query['post_type'] = 'plugin';

		$wp_query = new WP_Query( $query );

		$response['info']['page']    = (int) $wp_query->get_query_var( 'paged' ) ?: 1;
		$response['info']['pages']   = (int) $wp_query->max_num_pages            ?: 0;
		$response['info']['results'] = (int) $wp_query->found_posts              ?: 0;

		foreach ( $wp_query->posts as $post ) {
			$response['plugins'][] = $post->post_name;
		}

		return $response;
	}

}
