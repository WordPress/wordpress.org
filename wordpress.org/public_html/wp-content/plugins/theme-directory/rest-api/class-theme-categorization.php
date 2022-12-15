<?php

namespace WordPressdotorg\Theme_Directory\Rest_API;

use WP_Error;
use WP_REST_Controller, WP_REST_Server, WP_REST_Response;

defined( 'WPINC' ) || die();

/**
 *
 * @see WP_REST_Controller
 */
class Theme_Categorization_Controller extends WP_REST_Controller {

	/**
	 * Constructor.
	 *
	 */
	public function __construct( ) {
		$this->namespace         = 'themes/v1';
		$this->rest_base         = 'theme';

		$this->register_routes();
	}

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @see register_rest_route()
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<theme_slug>[^/]+)/commercial/?',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'save_external_support_url' ],
				'permission_callback' => [ $this, 'can_update_permissions_check' ],
				'args'                => [
					'theme_slug' => [
						'validate_callback' => [ $this, 'validate_theme_slug_callback' ],
						'required'          => true,
					],
					'supportURL' => [
						'validate_callback' => [ $this, 'validate_url' ],
						'required'          => true,
					],
				],
			]
		);
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<theme_slug>[^/]+)/community/?',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'save_external_repository_url' ],
				'permission_callback' => [ $this, 'can_update_permissions_check' ],
				'args'                => [
					'theme_slug' => [
						'validate_callback' => [ $this, 'validate_theme_slug_callback' ],
						'required'          => true,
					],
					'repositoryURL' => [
						'validate_callback' => [ $this, 'validate_url' ],
						'required'          => true,
					],
				],
			]
		);
	}

	/**
	 * Retrieve the WP_Post object representing a given theme.
	 *
	 * @static
	 * @global \WP_Post $post WordPress post object.
	 *
	 * @param int|string|\WP_Post $theme_slug The slug of the theme to retrieve.
	 * @return \WP_Post|bool
	 */
	public function get_theme_post( $theme_slug = null ) {
		if ( $theme_slug instanceof \WP_Post ) {
			return $theme_slug;
		}

		// Handle int $theme_slug being passed. NOT numeric slugs
		if (
			is_int( $theme_slug ) &&
			( $post = get_post( $theme_slug ) ) &&
			( $post->ID === $theme_slug )
		) {
			return $post;
		}

		// Use the global $post object when appropriate
		if (
			! empty( $GLOBALS['post']->post_type ) &&
			'repopackage' === $GLOBALS['post']->post_type
		) {
			// Default to the global object.
			if ( is_null( $theme_slug ) || 0 === $theme_slug ) {
				return get_post( $GLOBALS['post']->ID );
			}

			// Avoid hitting the database if it matches.
			if ( $theme_slug == $GLOBALS['post']->post_name ) {
				return get_post( $GLOBALS['post']->ID );
			}
		}
		
		$theme_slug = sanitize_title_for_query( $theme_slug );
		if ( ! $theme_slug ) {
			return false;
		}

		$post    = false;
		$post_id = wp_cache_get( $theme_slug, 'theme-slugs' );
		if ( 0 === $post_id ) {
			// Unknown theme slug.
			return false;
		} elseif ( $post_id ) {
			$post = get_post( $post_id );
		}

		if ( ! $post ) {
			$posts = get_posts( [
				'name'           => $theme_slug,
				'post_type'      => 'repopackage',
				'post_status'    => [ 'publish', 'pending', 'draft', 'future', 'suspend' ],
				'posts_per_page' => 1,
			] );

			if ( ! $posts ) {
				$post = false;
				wp_cache_add( 0, $theme_slug, 'theme-slugs' );
			} else {
				$post = reset( $posts );
				wp_cache_add( $post->ID, $theme_slug, 'theme-slugs' );
			}
		}

		return $post;
	}

	/**
	 * Validates theme slug.
	 *
	 * @param string $value Submitted field value.
	 * @return bool True if slug is value, else false.
	 */
	public function validate_theme_slug_callback( $value ) {
		return is_string( $value ) && $value && self::get_theme_post( $value );
	}

	/**
	 * Validates a URL.
	 *
	 * @param string $value Submitted field value.
	 * @return bool True if URL is valid, else false.
	 */
	public function validate_url( $value ) {
		$value = trim( strip_tags( stripslashes( $value ) ) );

		if ( ! $value ) {
			return true;
		}

		return (bool) filter_var( $value, FILTER_VALIDATE_URL );
	}

	/**
	 * Checks if current user can edit the categorization configuration for a theme.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool|\WP_Error True if the token exists, WP_Error upon failure.
	 */
	public function can_update_permissions_check( $request ) {
		return current_user_can( 'theme_configure_categorization_options', self::get_theme_post( $request['theme_slug'] ) );
	}

	/**
	 * Saves the submitted support URL.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Rest_Response|WP_Error WP_Rest_Response object on success, or WP_Error object on failure.
	 */
	public function save_external_support_url( $request ) {
		return $this->save_url( 'external_support_url', 'supportURL', $request );
	}

	/**
	 * Saves the submitted repository URL.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Rest_Response|WP_Error WP_Rest_Response object on success, or WP_Error object on failure.
	 */
	public function save_external_repository_url( $request ) {
		return $this->save_url( 'external_repository_url', 'repositoryURL', $request );
	}

	/**
	 * Saves a URL as post meta.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	protected function save_url( $meta_key, $request_key, $request ) {
		$url  = $request[ $request_key ] ?? '';
		$post = self::get_theme_post( $request['theme_slug'] );

		if ( ! $post ) {
			return;
		}

		$fail = false;

		// Sanitize URL.
		if ( $url ) {
			$url = trim( strip_tags( stripslashes( $url ) ) );
		}

		// Delete post meta if new value is empty, else save.
		if ( ! $url ) {
			delete_post_meta( $post->ID, $meta_key );
		} else {
			update_post_meta( $post->ID, $meta_key, wp_slash( esc_url_raw( $url, [ 'http', 'https' ] ) ) );
		}

		if ( $fail ) {
			return new WP_Error( 'failed', __( 'The operation failed. Please try again.', 'wporg-themes' ) );
		}

		return new WP_REST_Response( [ $request_key => $url ], \WP_Http::OK );
	}
}

new Theme_Categorization_Controller();

