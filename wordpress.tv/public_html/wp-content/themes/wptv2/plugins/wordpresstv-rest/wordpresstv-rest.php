<?php

/**
 * WordPress.tv REST APi
 *
 */
class WordPressTV_REST_API {

	/**
	 * Constructor fired during init.
	 *
	 * @global WP $wp
	 */
	function __construct() {
		global $wp;

		add_action( 'template_redirect', array( $this, 'template_redirect' ), 2 );
		add_rewrite_rule( 'api/(.*)', 'index.php?wptvapi=$matches[1]', 'top' );
		// @see rewrite.php in plugins to flush rules

		// ?unisubs and ?guid query variables.
		$wp->add_query_var( 'wptvapi' );
	}

	/**
	 * Returns a 404 status header and exits.
	 */
	function fourohfour() {
		status_header( 404 );
		exit( 'Invalid request.' );
	}

	function template_redirect() {
		global $wp_query, $post, $wptv;

		if ( ! get_query_var( 'wptvapi' ) ) {
			return;
		}

		$matches = array();
		if ( ! preg_match( '/^(.+)\.(json|array)$/i', get_query_var( 'wptvapi' ), $matches ) ) {
			$this->error( 'Invalid request.' );
		}

		$method   = $matches[1];
		$format   = $matches[2];
		$response = array();

		switch ( $method ) {
			case 'videos':
				if ( isset( $_REQUEST['posts_per_page'] ) ) {
					query_posts( array_merge( $wp_query->query, array( 'posts_per_page' => intval( $_REQUEST['posts_per_page'] ) ) ) );
				}

				$response['videos'] = array();
				while ( have_posts() ) {
					the_post();

					// Super lame hack to get a thumbnail from VideoPress :)
					ob_start();
					$wptv->the_video_image( 50, null, false, false );
					$thumbnail = esc_url_raw( trim( ob_get_contents() ) );
					ob_end_clean();

					$response['videos'][] = array(
						'title'     => $post->post_title,
						'permalink' => get_permalink( get_the_ID() ),
						'thumbnail' => $thumbnail,
					);
				}

				break;
			default:
				$this->error( 'Unknown method.' );
				break;
		}

		if ( ! empty( $response ) ) {
			switch ( $format ) {
				case 'json':
					echo json_encode( $response );
					break;
				case 'array':
					if ( defined( 'WPCOM_SANDBOXED' ) && WPCOM_SANDBOXED ) {
						print_r( $response );
					}
					break;
			}
			die();
		}

		$this->error( 'Empty response.' );
		die();
	}

	function error( $message, $http_code = 404 ) {
		status_header( $http_code );
		exit( $message );
	}
}

// Initialize the object.
add_action( 'init', 'wptv_rest_api_init', 5 );
function wptv_rest_api_init() {
	$wptv_rest_api = new WordPressTV_REST_API();
}
