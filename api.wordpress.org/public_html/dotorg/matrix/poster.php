<?php

namespace Dotorg\Matrix;

const MATRIX_INTEGRATIONS_ENABLED = false;

class Poster {
	const HOMESERVER_NAME = 'community.wordpress.org';
	const HOMESERVER_URL  = 'https://wporg.automattrix.com';

	/**
	 * Function to send a message to a matrix room, respecting the MATRIX_INTEGRATIONS_ENABLED constant
	 *
	 * Wrapper for _send() which actually does the sending
	 *
	 * @param string      $where Room ID or Room alias reference (full or local part).
	 * @param string      $message Text message to post.
	 * @param string|null $who Bot account that should post this message.
	 * @return void|bool Returns null on bad input, true on success, false on failure
	 */
	public static function send( string $where, string $message, string $who = null ) {
		if ( ! MATRIX_INTEGRATIONS_ENABLED ) {
			return;
		}

		return self::_send( $where, $message, $who );
	}

	/**
	 * Function to send a message to a matrix room, regardless of MATRIX_INTEGRATIONS_ENABLED constant
	 *
	 * Blind wrapper for _send() which actually does the sending
	 *
	 * @param string      $where Room ID or Room alias reference (full or local part).
	 * @param string      $message Text message to post.
	 * @param string|null $who Bot account that should post this message.
	 * @return void|bool Returns null on bad input, true on success, false on failure
	 */
	public static function force_send( string $where, string $message, string $who = null ) {
		return self::_send( $where, $message, $who );
	}

	/**
	 * Function that figures out the room id & endpoint that would be used to post the message
	 *
	 * @param string      $where Room ID or Room alias reference (full or local part).
	 * @param string      $message Text message to post.
	 * @param string|null $who Bot account that should post this message.
	 * @return void|bool Returns null on bad input, true on success, false on failure
	 */
	private static function _send( string $where, string $message, string $who = null ) {
		if ( empty( $where ) || empty( trim( $message ) ) ) {
			return;
		}

		$room_id = self::get_room_id( $where );
		if ( false === $room_id ) {
			return;
		}

		// which HTTP endpoint to use?
		// constants defined in "secrets.php".
		switch ( $who ) {
			// case 'specificbot':
			// $http_endpoint = MATRIX_INTEGRATIONS_SPECIFICBOT_POSTING_ENDPOINT;
			// break;
			default:
				$http_endpoint = MATRIX_INTEGRATIONS_MATRIXBOT_POSTING_ENDPOINT;
		}

		return self::post_message( $room_id, $message, $http_endpoint );
	}

	/**
	 * Returns a room id from one of the acceptable values supplied for room:
	 * room alias part such as "core", "#core"
	 * room alias such as "#core:community.wordpress.org"
	 * room ID "!cHPvPsHiObbVCkAdiy:community.wordpress.org"
	 *
	 * @param string $where Room ID or Room alias reference (full or local part).
	 * @return string|bool Room ID or false upon error.
	 */
	private static function get_room_id( string $where ) {
		if ( '!' === $where[0] ) {
			return $where;
		}

		if ( '#' !== $where[0] ) {
			$room_alias = '#' . explode( ':', $where )[0] . ':' . self::HOMESERVER_NAME;
		} else {
			$room_alias = explode( ':', $where )[0] . ':' . self::HOMESERVER_NAME;
		}

		$resolved_room_id = self::resolve_room_alias( $room_alias );
		if ( false === $resolved_room_id ) {
			return false;
		}

		return $resolved_room_id;
	}

	/**
	 * Resolves matrix room alias "#core:community.wordpress.org" into
	 * room id "!cHPvPsHiObbVCkAdiy:community.wordpress.org"
	 *
	 * @param string $room_alias Room alias example: "#orbit:community.wordpress.org".
	 * @return string|bool room id on success and false on error
	 */
	public static function resolve_room_alias( string $room_alias ) {
		$cache_key = "matrix_room_alias_$room_alias";
		$room_id   = wp_cache_get( $cache_key, 'matrix' );

		if ( false === $room_id ) {
			$response = wp_remote_post(
				self::HOMESERVER_URL . '/_matrix/client/v3/directory/room/' . rawurlencode( $room_alias ),
				array(
					'method'  => 'GET',
					'timeout' => 120,
				)
			);

			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
				error_log( __FUNCTION__ . "() error resolving room alias '$room_alias' - $error_message" );
				return false;
			}
			$status_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $status_code ) {
				error_log( __FUNCTION__ . "() HTTP Error: $status_code" );
				return false;
			}

			$decoded = json_decode( $response['body'], true );
			if ( is_null( $decoded ) ) {
				error_log( __FUNCTION__ . '() empty body returned' );
				return false;
			}

			if ( isset( $decoded['room_id'] ) ) {
				$room_id = $decoded['room_id'];
				wp_cache_set( $cache_key, $room_id, 'matrix', DAY_IN_SECONDS );
			}
		}

		return $room_id;
	}

	/**
	 * Posts the message by sending a POST request to maubot's HTTP endpoint.
	 *
	 * @param string $room_id RoomID to post to.
	 * @param string $message Text message to post.
	 * @param string $access_token Bot user account access token.
	 * @return bool true on success, false on failure
	 */
	private static function post_message( string $room_id, string $message, string $http_endpoint ): bool {
		// allow short-circuit via filter.
		$filtered_args = apply_filters(
			'dotorg_matrix_poster_post_message',
			array(
				'room_id'       => $room_id,
				'message'       => $message,
				'http_endpoint' => $http_endpoint,
			)
		);
		$room_id        = $filtered_args['room_id'];
		$message        = $filtered_args['message'];
		$http_endpoint  = $filtered_args['http_endpoint'];

		if ( empty( $room_id ) || empty( $message ) || empty( $http_endpoint ) ) {
			return false;
		}

		$response = wp_remote_post(
			$http_endpoint,
			array(
				'method'  => 'POST',
				'timeout' => 120,
				'headers' => array(
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'room'    => $room_id,
						'message' => $message,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			error_log( __FUNCTION__ . "() error posting to matrix homeserver using thin client - $error_message" );
			return false;
		}
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			error_log( __FUNCTION__ . "() HTTP Error: $status_code - " . wp_remote_retrieve_body( $response ) );
			return false;
		}

		return true;
	}
}
