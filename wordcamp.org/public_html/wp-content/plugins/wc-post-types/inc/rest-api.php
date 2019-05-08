<?php
/**
 * Functions related to the v2 REST API for WordCamp Post Types
 *
 * @package WordCamp\Post_Types\REST_API
 */

namespace WordCamp\Post_Types\REST_API;
use WP_Rest_Server, WP_Post_Type;

defined( 'WPINC' ) || die();

require_once( 'favorite-schedule-shortcode.php' );

/**
 * Add non-sensitive meta fields to WordCamp post type REST API endpoints.
 *
 * If we ever want to register meta for purposes other than exposing it in the API, then this function will
 * probably need to be re-thought and re-factored.
 *
 * @return void
 */
function expose_public_post_meta() {
	$meta_defaults = [
		'show_in_rest' => true,
		'single'       => true,
	];

	// Session.
	register_post_meta( 'wcb_session', '_wcpt_session_time', wp_parse_args( [ 'type' => 'integer' ], $meta_defaults ) );
	register_post_meta( 'wcb_session', '_wcpt_session_type', $meta_defaults );
	register_post_meta( 'wcb_session', '_wcpt_session_slides', $meta_defaults );
	register_post_meta( 'wcb_session', '_wcpt_session_video', $meta_defaults );

	// Sponsor.
	register_post_meta( 'wcb_sponsor', '_wcpt_sponsor_website', $meta_defaults );
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
	 * Speaker/organizer avatars.
	 *
	 * We can't expose a Speaker's e-mail address in the API response, but can we go ahead
	 * and derive their Gravatar URL and expose that instead.
	 */
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

	$avatar_schema = array(
		'description' => __( 'Avatar URLs for the speaker.', 'wordcamporg' ),
		'type'        => 'object',
		'context'     => array( 'embed', 'view', 'edit' ),
		'readonly'    => true,
		'properties'  => $avatar_properties,
	);

	register_rest_field(
		'wcb_speaker',
		'avatar_urls',
		array(
			'get_callback' => __NAMESPACE__ . '\get_avatar_urls_from_username_email',
			'schema'       => $avatar_schema,
		)
	);

	register_rest_field(
		'wcb_organizer',
		'avatar_urls',
		array(
			'get_callback' => __NAMESPACE__ . '\get_avatar_urls_from_username_email',
			'schema'       => $avatar_schema,
		)
	);

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
				'context'     => array( 'embed', 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => [
					'date' => [
						'type'    => 'string',
					],
					'time' => [
						'type'    => 'string',
					],
				],
			],
		]
	);
}

add_action( 'rest_api_init', __NAMESPACE__ . '\register_additional_rest_fields' );

/**
 * Validate simple meta query parameters in an API request and add them to the args passed to WP_Query.
 *
 * @param array $args    The prepared args for the WP_Query object.
 * @param array $request The args from the REST API request.
 *
 * @return array
 */
function prepare_meta_query_args( $args, $request ) {
	if ( isset( $request['wc_meta_key'], $request['wc_meta_value'] ) ) {
		$args['meta_key']   = $request['wc_meta_key'];
		$args['meta_value'] = $request['wc_meta_value'];
	}

	return $args;
}

add_filter( 'rest_wcb_session_query', __NAMESPACE__ . '\prepare_meta_query_args', 10, 2 );

/**
 * Add meta field schemas to Sessions collection parameters.
 *
 * This enables and validates simple meta query parameters for the Sessions endpoint. Specific meta keys are
 * safelisted by filtering for ones that have `show_in_rest` set to `true`.
 *
 * TODO: This is necessary because as of version 5.2, WP does not support meta queries on the posts endpoint.
 *       See https://core.trac.wordpress.org/ticket/47194
 *
 * The parameters registered here are prefixed with `wc_` because we don't want to have a collision with a
 * future implementation in Core, if there ever is one.
 *
 * @param array        $query_params
 * @param WP_Post_Type $post_type
 *
 * @return array
 */
function add_meta_collection_params( $query_params, $post_type ) {
	// Avoid exposing potentially sensitive data.
	$public_meta_fields = wp_list_filter( get_registered_meta_keys( 'post', $post_type->name ), [ 'show_in_rest' => true ] );

	$query_params['wc_meta_key'] = [
		'description' => __( 'Limit result set to posts with a value set for a specific meta key. Use in conjunction with the wc_meta_value parameter.', 'wordcamporg' ),
		'type'        => 'string',
		'enum'        => array_keys( $public_meta_fields ),
	];

	$query_params['wc_meta_value'] = [
		'description' => __( 'Limit result set to posts with a specific meta value. Use in conjunction with the wc_meta_key parameter.', 'wordcamporg' ),
		'type'        => 'string',
	];

	return $query_params;
}

add_filter( 'rest_wcb_session_collection_params', __NAMESPACE__ . '\add_meta_collection_params', 10, 2 );

/**
 * Get the URLs for an avatar based on an email address or username.
 *
 * @param array $post
 *
 * @return array
 */
function get_avatar_urls_from_username_email( $post ) {
	$post        = (object) $post;
	$avatar_urls = [];
	$email       = get_post_meta( $post->id, '_wcb_speaker_email', true );
	$user_id     = get_post_meta( $post->id, '_wcpt_user_id', true );

	if ( $email ) {
		$avatar_urls = rest_get_avatar_urls( $email );
	} elseif ( $user_id ) {
		$user = get_user_by( 'id', $user_id );

		if ( $user ) {
			$avatar_urls = rest_get_avatar_urls( $user->user_email );
		}
	}

	if ( empty( $avatar_urls ) ) {
		$avatar_urls = rest_get_avatar_urls( '' );
	}

	return $avatar_urls;
}


/**
 * Register route for sending schedule of favourite sessions via e-mail.
 *
 * This can be disabled in email_fav_sessions_disabled() from favorite-schedule-shortcode.php.
 *
 * @return void
 */
function register_fav_sessions_email() {
	register_rest_route(
		'wc-post-types/v1',
		'/email-fav-sessions/',
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
