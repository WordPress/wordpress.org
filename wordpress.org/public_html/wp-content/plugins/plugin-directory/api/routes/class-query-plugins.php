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
		'author_name',
		'installed_plugins',
		'plugin_tags',
		'locale',
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
		global $wp_query;

		$response = array(
			'info'    => array(
				'page'    => 0,
				'pages'   => 0,
				'results' => 0,
			),
			'plugins' => array(),
		);

		$query = array_intersect_key( $request->get_params(), array_flip( $this->valid_query_fields ) );

		// Add some sane pagination limits to prevent insane queries.
		if ( isset( $query['paged'] ) ) {
			$query['paged'] = min( $query['paged'], 999 );
		}
		if ( isset( $query['posts_per_page'] ) ) {
			$query['posts_per_page'] = min( $query['posts_per_page'], 250 );

			// 0 and -1 are not valid. Just drop the parameter.
			if ( $query['posts_per_page'] <= 0 ) {
				unset( $query['posts_per_page'] );
			}
		}

		// Block Directory searches
		$block_search = $request->get_param( 'block' );
		if ( $block_search ) {
			$query['s'] = $block_search;
			$query['block_search'] = true;
		}

		if ( ! $query ) {
			return $response;
		}

		// Support returning API data in different locales, even on wordpress.org (for api.wordpress.org usage)
		if ( ! empty( $request['locale'] ) && ! in_array( strtolower( $request['locale'] ), array( 'en_us', 'en' ) ) ) {
			switch_to_locale( $request['locale'] );
		}

		/*
		 * Allow an API search bypass for exact-post matches.
		 * - `slug:example-plugin` will only return THAT plugin, nothing else.
		 * - `block:example-plugin/my-block` will return Block directory plugins, or
		 *    regular plugins that supply that block if there were no matches in the block directory.
		 * 
		 * TODO: This might have been useful as a general search filter for the website too.
		 */
		if ( !empty( $query['s'] ) ) {
			if ( 'slug:' === substr( $query['s'], 0, 5 ) ) {
				$query['name'] = substr( $query['s'], 5 );

				unset( $query['s'] );
			}

			if ( isset( $query['s'] ) && 'block:' === substr( $query['s'], 0, 6 ) ) {
				$query['meta_query'][] = [
					'key' => 'block_name',
					'value' => substr( $query['s'], 6 )
				];

				$query['tax_query'][] = [
					'taxonomy' => 'plugin_section',
					'field' => 'slug',
					'terms' => 'block',
				];

				// Prioritise block plugins, but try again without the restriction.
				$try_again_without_tax_query = true;

				unset( $query['s'], $query['block_search'] );
			}

		}

		$query['post_type']   = 'plugin';
		$query['post_status'] = 'publish';

		// Use the main query so that is_main_query() is triggered for the filters.
		$wp_query->query( $query );

		// Maybe retry without the the block-specific query if no plugins were found.
		if ( ! $wp_query->found_posts && isset( $try_again_without_tax_query ) ) {
			unset( $query['tax_query'] );
			$wp_query->query( $query );
		}

		$response['info']['page']    = (int) $wp_query->get( 'paged' ) ?: 1;
		$response['info']['pages']   = (int) $wp_query->max_num_pages ?: 0;
		$response['info']['results'] = (int) $wp_query->found_posts ?: 0;

		foreach ( $wp_query->posts as $post ) {
			$response['plugins'][] = $post->post_name ?: get_post( $post->ID )->post_name;
		}

		return $response;
	}

}
