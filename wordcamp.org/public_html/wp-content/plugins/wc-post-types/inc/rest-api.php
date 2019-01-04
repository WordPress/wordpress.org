<?php
/**
 * Functions related to the v2 REST API for WordCamp Post Types
 *
 * @package WordCamp\Post_Types\REST_API
 */

namespace WordCamp\Post_Types\REST_API;
use WP_Rest_Server;

defined( 'WPINC' ) || die();

require_once( 'favorite-schedule-shortcode.php' );

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
		$avatar_sizes      = rest_get_avatar_sizes();

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
				'get_callback' => function ( $speaker_post ) {
					$speaker_post = (object) $speaker_post;
					$avatar_urls  = [];

					if ( $speaker_email = get_post_meta( $speaker_post->id, '_wcb_speaker_email', true ) ) {
						$avatar_urls = rest_get_avatar_urls( $speaker_email );
					} elseif ( $speaker_user_id = get_post_meta( $speaker_post->id, '_wcpt_user_id', true ) ) {
						$speaker = get_user_by( 'id', $speaker_user_id );

						if ( $speaker ) {
							$avatar_urls = rest_get_avatar_urls( $speaker->user_email );
						}
					}

					if ( empty( $avatar_urls ) ) {
						$avatar_urls = rest_get_avatar_urls( '' );
					}

					return $avatar_urls;
				},
				'schema'       => array(
					'description' => __( 'Avatar URLs for the speaker.', 'wordcamporg' ),
					'type'        => 'object',
					'context'     => array( 'embed', 'view', 'edit' ),
					'readonly'    => true,
					'properties'  => $avatar_properties,
				),
			)
		);
	} // End if().

	/**
	 * Session date and time
	 */
	register_rest_field(
		'wcb_session',
		'session_date_time',
		[
			'get_callback' => function ( $object ) {
				$object = (object) $object;
				$raw    = absint( get_post_meta( $object->id, '_wcpt_session_time', true ) );
				$return = [
					'date' => '',
					'time' => '',
				];

				if ( $raw ) {
					$return['date'] = date_i18n( get_option( 'date_format' ), $raw );
					$return['time'] = date_i18n( get_option( 'time_format' ), $raw );
				}

				return $return;
			},
			'schema'       => [
				'description' => __( 'Date and time of the session', 'wordcamporg' ),
				'type'        => 'object',
				'context'     => array( 'embed', 'view' ),
				'readonly'    => true,
				'properties'  => [
					'date' => [
						'type'    => 'string',
						'context' => array( 'embed', 'view' ),
					],
					'time' => [
						'type'    => 'string',
						'context' => array( 'embed', 'view' ),
					],
				],
			],
		]
	);
}


add_action( 'rest_api_init', __NAMESPACE__ . '\register_additional_rest_fields' );


/**
 * Register route for sending schedule of favourite sessions via e-mail.
 *
 * This can be disabled in email_fav_sessions_disabled() from favorite-schedule-shortcode.php.
 *
 * @return void
 */
function register_fav_sessions_email() {
	register_rest_route(
		'wc-post-types/v1',     // REST namespace + API version
		'/email-fav-sessions/', // URL slug
		array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => 'send_favourite_sessions_email',
			'args'     => array(
				'email-address' => array(
					'required'          => true,
					'validate_callback' => function( $value, $request, $param ) {
						return is_email( $value );
					},
					'sanitize_callback' => function( $value, $request, $param ) {
						return sanitize_email( $value );
					},
				),

				'session-list' => array(
					'required'          => true,
					'validate_callback' => function( $value, $request, $param ) {
						$session_ids = explode( ',', $value );
						$session_count = count( $session_ids );
						for ( $i = 0; $i < $session_count; $i++ ) {
							if ( ! is_numeric( $session_ids[ $i ] ) ) {
								return false;
							}
						}
						return true;
					},
					'sanitize_callback' => function( $value, $request, $param ) {
						$session_ids = explode( ',', $value );
						return implode( ',', array_filter( $session_ids, 'is_numeric' ) );
					},
				),
			),
		)
	);
}
add_action( 'rest_api_init', __NAMESPACE__ . '\register_fav_sessions_email' );

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
			add_query_arg(
				[
					// Ensure that taxonomy data gets included in the embed.
					'_embed'  => true,
					'context' => 'view',
				],
				get_rest_url( null, "/wp/v2/sessions/$session_id" )
			),
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
