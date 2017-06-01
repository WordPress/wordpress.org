<?php
/**
 * Functions related to the v2 REST API for WordCamp Post Types
 *
 * @package WordCamp\Post_Types\REST_API
 */

namespace WordCamp\Post_Types\REST_API;
defined( 'WPINC' ) || die();

/**
 * Add non-sensitive meta fields to the speaker/session REST API endpoints
 *
 * If we ever want to register meta for purposes other than exposing it in the API, then this function will
 * probably need to be re-thought and re-factored.
 *
 * @uses wcorg_register_meta_only_on_endpoint()
 *
 * @return void
 */
function expose_public_post_meta() {
	$public_session_fields = array(
		'_wcpt_session_time' => array(
			'type'   => 'integer',
			'single' => true,
		),

		'_wcpt_session_type' => array(
			'single' => true,
		),

		'_wcpt_session_slides' => array(
			'single' => true,
		),

		'_wcpt_session_video' => array(
			'single' => true,
		),
	);

	wcorg_register_meta_only_on_endpoint( 'post', $public_session_fields, '/wp-json/wp/v2/sessions/' );

	$public_sponsor_fields = array(
		'_wcpt_sponsor_website' => array(
			'single' => true,
		),
	);

	wcorg_register_meta_only_on_endpoint( 'post', $public_sponsor_fields, '/wp-json/wp/v2/sponsors/' );
}

add_action( 'init', __NAMESPACE__ . '\expose_public_post_meta' );

/**
 * Additional fields to include in API responses for WordCamp post types.
 *
 * Note that this is for special cases. To expose specific post meta values, see `expose_public_post_meta()`.
 *
 * @return void
 */
function register_additional_rest_fields() {
	/**
	 * Speaker avatars.
	 *
	 * We can't expose a Speaker's e-mail address in the API response, but can we go ahead
	 * and derive their Gravatar URL and expose that instead.
	 */
	if ( get_option( 'show_avatars' ) ) {
		$avatar_properties = array();
		$avatar_sizes = rest_get_avatar_sizes();

		foreach ( $avatar_sizes as $size ) {
			$avatar_properties[ $size ] = array(
				/* translators: %d: avatar image size in pixels */
				'description' => sprintf( __( 'Avatar URL with image size of %d pixels.' ), $size ),
				'type'        => 'string',
				'format'      => 'uri',
				'context'     => array( 'embed', 'view', 'edit' ),
			);
		}

		register_rest_field(
			'wcb_speaker',
			'avatar_urls',
			array(
				'get_callback' => function ( $object ) {
					$object      = (object) $object;
					$avatar_urls = array_fill_keys( rest_get_avatar_sizes(), '' );

					if ( $speaker_email = get_post_meta( $object->id, '_wcb_speaker_email', true ) ) {
						$avatar_urls = rest_get_avatar_urls( $speaker_email );
					} elseif ( $speaker_user_id = get_post_meta( $object->id, '_wcpt_user_id', true ) ) {
						$speaker = get_user_by( 'id', $speaker_user_id );

						if ( $speaker ) {
							$avatar_urls = rest_get_avatar_urls( $speaker->user_email );
						}
					}

					return $avatar_urls;
				},
				'schema'       => array(
					'description' => __( 'Avatar URLs for the speaker.' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
					'properties'  => $avatar_properties,
				),
			)
		);
	} // End if().
}

add_action( 'rest_api_init', __NAMESPACE__ . '\register_additional_rest_fields' );

/**
 * Link all sessions to the speaker in the `speakers` API endpoint
 *
 * This allows clients to request a speaker and get all their sessions embedded in the response, avoiding
 * extra HTTP requests
 *
 * @param \WP_REST_Response $response
 * @param \WP_Post          $post
 *
 * @return \WP_REST_Response
 */
function link_speaker_to_sessions( $response, $post ) {
	$sessions = get_posts( array(
		'post_type'      => 'wcb_session',
		'posts_per_page' => 100,
		'fields'         => 'ids',

		'meta_query' => array(
			array(
				'key'   => '_wcpt_speaker_id',
				'value' => $post->ID,
			),
		),
	) );

	foreach ( $sessions as $session_id ) {
		$response->add_link(
			'sessions',
			get_rest_url( null, "/wp/v2/sessions/$session_id" ),
			array( 'embeddable' => true )
		);
	}

	return $response;
}

add_filter( 'rest_prepare_wcb_speaker', __NAMESPACE__ . '\link_speaker_to_sessions', 10, 2 );

/**
 * Link all speakers to the session in the `sessions` API endpoint
 *
 * This allows clients to request a session and get all its speakers embedded in the response, avoiding extra
 * HTTP requests
 *
 * @param \WP_REST_Response $response
 * @param \WP_Post          $post
 *
 * @return \WP_REST_Response
 */
function link_session_to_speakers( $response, $post ) {
	$speaker_ids = get_post_meta( $post->ID, '_wcpt_speaker_id', false );

	foreach ( $speaker_ids as $speaker_id ) {
		$response->add_link(
			'speakers',
			get_rest_url( null, "/wp/v2/speakers/$speaker_id" ),
			array( 'embeddable' => true )
		);
	}

	return $response;
}

add_filter( 'rest_prepare_wcb_session', __NAMESPACE__ . '\link_session_to_speakers', 10, 2 );
